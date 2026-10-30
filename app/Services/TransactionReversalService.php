<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Deposit;
use App\Models\LedgerEntry;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WasteType;
use App\Support\DepositInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionReversalService
{
    public function voidDeposit(Deposit $deposit, string $reason, User $actor): Deposit
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($deposit, $reason, $actor) {
            $this->lockActor($actor);
            $current = Deposit::query()->with('items')->lockForUpdate()->findOrFail($deposit->id);
            if ($current->status === 'void') {
                return $this->replayedOrFail($current, $reason, $actor);
            }

            $items = $current->items->sortBy('waste_type_id')->values();
            WasteType::whereIn('id', $items->pluck('waste_type_id'))->orderBy('id')->lockForUpdate()->get();
            $stocks = StockMovement::query()->whereIn('waste_type_id', $items->pluck('waste_type_id'))
                ->selectRaw('waste_type_id, SUM(weight_signed)::text AS stock_kg')->groupBy('waste_type_id')->pluck('stock_kg', 'waste_type_id');
            foreach ($items as $item) {
                $available = DepositInput::grams((string) ($stocks->get($item->waste_type_id) ?? '0'));
                $required = DepositInput::grams($item->weight_kg);
                if ($required > $available) {
                    throw ValidationException::withMessages([
                        'reason' => "Setoran tidak dapat dibatalkan karena stok {$item->wasteType()->value('name')} tinggal ".DepositInput::kilograms($available).' kg.',
                    ]);
                }
            }

            $credit = LedgerEntry::query()->where('deposit_id', $current->id)->where('entry_type', 'deposit_credit')->lockForUpdate()->sole();
            $reversal = new LedgerEntry;
            $reversal->forceFill([
                'member_id' => $current->member_id, 'deposit_id' => $current->id,
                'entry_type' => 'deposit_reversal', 'amount_signed' => -$credit->amount_signed,
                'reversal_of_id' => $credit->id, 'created_by' => $actor->id,
            ])->save();
            foreach ($items as $item) {
                $movement = new StockMovement;
                $movement->forceFill([
                    'waste_type_id' => $item->waste_type_id, 'deposit_item_id' => $item->id,
                    'movement_type' => 'deposit_reversal', 'weight_signed' => '-'.$item->weight_kg,
                    'created_by' => $actor->id,
                ])->save();
            }
            $current->forceFill([
                'status' => 'void', 'voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason,
            ])->save();
            $this->audit($actor, 'deposit.voided', 'deposits', $current->id, [
                'deposit_number' => $current->deposit_number, 'reason' => $reason,
                'amount_reversed' => $credit->amount_signed, 'item_count' => $items->count(),
            ]);

            return $current;
        }, 3);
    }

    public function voidSale(Sale $sale, string $reason, User $actor): Sale
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($sale, $reason, $actor) {
            $this->lockActor($actor);
            $current = Sale::query()->with('items')->lockForUpdate()->findOrFail($sale->id);
            if ($current->status === 'void') {
                return $this->replayedOrFail($current, $reason, $actor);
            }

            $items = $current->items->sortBy('waste_type_id')->values();
            WasteType::whereIn('id', $items->pluck('waste_type_id'))->orderBy('id')->lockForUpdate()->get();
            foreach ($items as $item) {
                $movement = new StockMovement;
                $movement->forceFill([
                    'waste_type_id' => $item->waste_type_id, 'sale_item_id' => $item->id,
                    'movement_type' => 'sale_reversal', 'weight_signed' => $item->weight_kg,
                    'created_by' => $actor->id,
                ])->save();
            }
            $current->forceFill([
                'status' => 'void', 'voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason,
            ])->save();
            $this->audit($actor, 'sale.voided', 'sales', $current->id, [
                'sale_number' => $current->sale_number, 'reason' => $reason,
                'amount_reversed' => $current->total_amount, 'item_count' => $items->count(),
            ]);

            return $current;
        }, 3);
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->status === 'active' && in_array($actor->role, ['admin', 'staff'], true), 403);
    }

    private function lockActor(User $actor): void
    {
        $currentActor = User::query()->sharedLock()->findOrFail($actor->id);
        $this->authorize($currentActor);
    }

    private function replayedOrFail(Deposit|Sale $transaction, string $reason, User $actor): Deposit|Sale
    {
        if ((int) $transaction->voided_by === $actor->id && hash_equals((string) $transaction->void_reason, $reason)) {
            return $transaction;
        }

        throw ValidationException::withMessages(['reason' => 'Transaksi ini sudah dibatalkan sebelumnya. Muat ulang halaman untuk melihat detail pembatalan.']);
    }

    private function audit(User $actor, string $action, string $entityType, int $entityId, array $metadata): void
    {
        $audit = new AuditLog;
        $audit->forceFill([
            'actor_id' => $actor->id, 'action' => $action, 'entity_type' => $entityType,
            'entity_id' => $entityId, 'metadata' => $metadata,
        ])->save();
    }
}
