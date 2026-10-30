<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Collector;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WasteType;
use App\Support\DepositInput;
use App\Support\SaleInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function post(array $input, User $actor): Sale
    {
        abort_unless($actor->status === 'active' && in_array($actor->role, ['admin', 'staff'], true), 403);
        $data = Validator::make($input, SaleInput::rules(), SaleInput::messages(), SaleInput::attributes())->validate();
        $data['idempotency_key'] = strtolower($data['idempotency_key']);
        $items = collect($data['items'])->map(fn ($item) => [
            'waste_type_id' => (int) $item['waste_type_id'],
            'grams' => DepositInput::grams((string) $item['weight_kg']),
            'price' => (int) $item['sell_price'],
        ])->sortBy('waste_type_id')->values()->all();

        return DB::transaction(function () use ($data, $items, $actor) {
            $currentActor = User::query()->sharedLock()->findOrFail($actor->id);
            abort_unless($currentActor->status === 'active' && in_array($currentActor->role, ['admin', 'staff'], true), 403);

            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['asrikita.sale.'.$data['idempotency_key']]);
            $existing = Sale::with('items')->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                $saved = $existing->items->map(fn ($item) => [
                    'waste_type_id' => (int) $item->waste_type_id,
                    'grams' => DepositInput::grams($item->weight_kg),
                    'price' => $item->sell_price_snapshot,
                ])->sortBy('waste_type_id')->values()->all();
                if ((int) $existing->staff_id !== $actor->id || (int) $existing->collector_id !== (int) $data['collector_id'] || $saved !== $items) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Formulir ini telah digunakan untuk penjualan berbeda. Buka formulir penjualan baru.']);
                }

                return $existing;
            }

            $collector = Collector::query()->lockForUpdate()->find($data['collector_id']);
            if (! $collector || ! $collector->is_active) {
                throw ValidationException::withMessages(['collector_id' => 'Pengepul tidak ditemukan atau sudah nonaktif.']);
            }

            $types = WasteType::whereIn('id', array_column($items, 'waste_type_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $stocks = StockMovement::query()->whereIn('waste_type_id', array_column($items, 'waste_type_id'))
                ->selectRaw('waste_type_id, SUM(weight_signed)::text AS stock_kg')->groupBy('waste_type_id')->pluck('stock_kg', 'waste_type_id');
            $total = 0;
            foreach ($items as &$item) {
                $type = $types->get($item['waste_type_id']);
                if (! $type || ! $type->is_active) {
                    throw ValidationException::withMessages(['items' => 'Salah satu jenis sampah tidak ditemukan atau sudah nonaktif. Muat ulang formulir.']);
                }
                $available = DepositInput::grams((string) ($stocks->get($type->id) ?? '0'));
                if ($item['grams'] > $available) {
                    throw ValidationException::withMessages(['items' => "Stok {$type->name} tidak mencukupi. Tersedia ".DepositInput::kilograms($available).' kg.']);
                }
                $item['amount'] = DepositInput::amount($item['grams'], $item['price']);
                if ($item['amount'] < 1) {
                    throw ValidationException::withMessages(['items' => 'Nilai setiap item setelah pembulatan harus minimal Rp 1. Periksa berat dan harga jual.']);
                }
                $total += $item['amount'];
            }
            unset($item);

            $sale = new Sale;
            $sale->forceFill([
                'sale_number' => 'JUAL-'.Str::ulid(), 'collector_id' => $collector->id,
                'staff_id' => $actor->id, 'occurred_at' => now(), 'status' => 'posted',
                'total_amount' => $total, 'idempotency_key' => $data['idempotency_key'],
            ])->save();

            foreach ($items as $item) {
                $detail = new SaleItem;
                $detail->forceFill([
                    'sale_id' => $sale->id, 'waste_type_id' => $item['waste_type_id'],
                    'weight_kg' => DepositInput::kilograms($item['grams']),
                    'sell_price_snapshot' => $item['price'], 'amount' => $item['amount'],
                ])->save();
                $movement = new StockMovement;
                $movement->forceFill([
                    'waste_type_id' => $detail->waste_type_id, 'sale_item_id' => $detail->id,
                    'movement_type' => 'sale_out', 'weight_signed' => '-'.$detail->weight_kg,
                    'created_by' => $actor->id,
                ])->save();
            }

            $audit = new AuditLog;
            $audit->forceFill([
                'actor_id' => $actor->id, 'action' => 'sale.posted', 'entity_type' => 'sales',
                'entity_id' => $sale->id, 'metadata' => ['sale_number' => $sale->sale_number, 'total_amount' => $total, 'item_count' => count($items)],
            ])->save();

            return $sale;
        }, 3);
    }
}
