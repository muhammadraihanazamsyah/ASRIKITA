<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Deposit;
use App\Models\DepositItem;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WasteType;
use App\Support\DepositInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DepositService
{
    public function post(array $input, User $actor): Deposit
    {
        abort_unless($actor->status === 'active' && in_array($actor->role, ['admin', 'staff'], true), 403);
        $data = Validator::make($input, DepositInput::rules(), DepositInput::messages(), DepositInput::attributes())->validate();
        $data['idempotency_key'] = strtolower($data['idempotency_key']);
        $items = collect($data['items'])->map(fn ($item) => [
            'waste_type_id' => (int) $item['waste_type_id'],
            'grams' => DepositInput::grams((string) $item['weight_kg']),
            'price' => (int) $item['expected_price'],
        ])->sortBy('waste_type_id')->values()->all();

        return DB::transaction(function () use ($data, $items, $actor) {
            $currentActor = User::query()->sharedLock()->findOrFail($actor->id);
            abort_unless($currentActor->status === 'active' && in_array($currentActor->role, ['admin', 'staff'], true), 403);

            // Serialize requests with this key across PHP workers, even before a row exists.
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['asrikita.deposit.'.$data['idempotency_key']]);
            $existing = Deposit::with('items')->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                $saved = $existing->items->map(fn ($item) => [
                    'waste_type_id' => (int) $item->waste_type_id,
                    'grams' => DepositInput::grams($item->weight_kg),
                    'price' => $item->buy_price_snapshot,
                ])->sortBy('waste_type_id')->values()->all();
                if ((int) $existing->staff_id !== $actor->id || (int) $existing->member_id !== (int) $data['member_id'] || $saved !== $items) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Formulir ini telah digunakan untuk setoran berbeda. Buka formulir setoran baru.']);
                }

                return $existing;
            }

            $member = Member::query()->lockForUpdate()->find($data['member_id']);
            if (! $member || $member->status !== 'active') {
                throw ValidationException::withMessages(['member_id' => 'Nasabah tidak ditemukan atau sudah nonaktif.']);
            }
            // Same ascending order for all future deposit/sale/reversal stock locks.
            $types = WasteType::whereIn('id', array_column($items, 'waste_type_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $total = 0;
            foreach ($items as &$item) {
                $type = $types->get($item['waste_type_id']);
                if (! $type || ! $type->is_active || $type->current_buy_price <= 0) {
                    throw ValidationException::withMessages(['items' => 'Salah satu jenis sampah tidak aktif atau harganya belum valid. Muat ulang formulir.']);
                }
                if ($type->current_buy_price !== $item['price']) {
                    throw ValidationException::withMessages(['items' => 'Harga sampah telah berubah. Muat ulang formulir dan periksa kembali total sebelum menyimpan.']);
                }
                $item['amount'] = DepositInput::amount($item['grams'], $type->current_buy_price);
                if ($item['amount'] < 1) {
                    throw ValidationException::withMessages(['items' => 'Nilai setiap item setelah pembulatan harus minimal Rp 1. Periksa berat dan harga.']);
                }
                $total += $item['amount'];
            }
            unset($item);

            $deposit = new Deposit;
            $deposit->forceFill([
                'deposit_number' => 'SET-'.Str::ulid(), 'member_id' => $member->id,
                'staff_id' => $actor->id, 'occurred_at' => now(), 'status' => 'posted',
                'total_amount' => $total, 'idempotency_key' => $data['idempotency_key'],
            ])->save();

            foreach ($items as $item) {
                $detail = new DepositItem;
                $detail->forceFill([
                    'deposit_id' => $deposit->id, 'waste_type_id' => $item['waste_type_id'],
                    'weight_kg' => DepositInput::kilograms($item['grams']),
                    'buy_price_snapshot' => $item['price'], 'amount' => $item['amount'],
                ])->save();
                $stock = new StockMovement;
                $stock->forceFill([
                    'waste_type_id' => $detail->waste_type_id, 'deposit_item_id' => $detail->id,
                    'movement_type' => 'deposit_in', 'weight_signed' => $detail->weight_kg, 'created_by' => $actor->id,
                ])->save();
            }
            $credit = new LedgerEntry;
            $credit->forceFill([
                'member_id' => $member->id, 'deposit_id' => $deposit->id, 'entry_type' => 'deposit_credit',
                'amount_signed' => $total, 'created_by' => $actor->id,
            ])->save();
            $audit = new AuditLog;
            $audit->forceFill([
                'actor_id' => $actor->id, 'action' => 'deposit.posted', 'entity_type' => 'deposits',
                'entity_id' => $deposit->id, 'metadata' => ['deposit_number' => $deposit->deposit_number, 'total_amount' => $total, 'item_count' => count($items)],
            ])->save();

            return $deposit;
        }, 3);
    }
}
