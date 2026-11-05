<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\WithdrawalInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public function post(array $input, User $actor): Withdrawal
    {
        $this->authorizeOperator($actor);
        $data = Validator::make($input, WithdrawalInput::rules(), WithdrawalInput::messages(), WithdrawalInput::attributes())->validate();
        $data['idempotency_key'] = strtolower($data['idempotency_key']);
        $data['notes'] = isset($data['notes']) ? (trim($data['notes']) ?: null) : null;

        return DB::transaction(function () use ($data, $actor) {
            $this->lockActor($actor, false);
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['asrikita.withdrawal.'.$data['idempotency_key']]);
            $existing = Withdrawal::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                if ((int) $existing->recorded_by !== $actor->id || (int) $existing->member_id !== (int) $data['member_id'] || (int) $existing->amount !== (int) $data['amount'] || $existing->notes !== $data['notes']) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Formulir ini telah digunakan untuk penarikan berbeda. Buka formulir penarikan baru.']);
                }

                return $existing;
            }

            $member = Member::query()->lockForUpdate()->find($data['member_id']);
            if (! $member || $member->status !== 'active') {
                throw ValidationException::withMessages(['member_id' => 'Nasabah tidak ditemukan atau sudah nonaktif.']);
            }
            $balance = (int) LedgerEntry::where('member_id', $member->id)->sum('amount_signed');
            if ((int) $data['amount'] > $balance) {
                throw ValidationException::withMessages(['amount' => 'Nominal penarikan melebihi saldo nasabah yang tersedia.']);
            }

            $withdrawal = new Withdrawal;
            $withdrawal->forceFill([
                'withdrawal_number' => 'TRK-'.Str::ulid(), 'member_id' => $member->id,
                'amount' => (int) $data['amount'], 'status' => 'posted', 'notes' => $data['notes'],
                'idempotency_key' => $data['idempotency_key'], 'recorded_by' => $actor->id,
            ])->save();
            $debit = new LedgerEntry;
            $debit->forceFill([
                'member_id' => $member->id, 'withdrawal_id' => $withdrawal->id,
                'entry_type' => 'withdrawal_debit', 'amount_signed' => -(int) $data['amount'], 'created_by' => $actor->id,
            ])->save();
            $this->audit($actor, 'withdrawal.posted', $withdrawal, ['amount' => (int) $data['amount'], 'balance_before' => $balance, 'balance_after' => $balance - (int) $data['amount']]);

            return $withdrawal;
        }, 3);
    }

    public function cancel(Withdrawal $withdrawal, string $reason, User $actor): Withdrawal
    {
        abort_unless($actor->status === 'active' && $actor->role === 'admin', 403);
        $reason = trim($reason);

        return DB::transaction(function () use ($withdrawal, $reason, $actor) {
            $this->lockActor($actor, true);
            $current = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id);
            if ($current->status === 'cancelled') {
                throw ValidationException::withMessages(['reason' => 'Penarikan ini sudah dibatalkan sebelumnya.']);
            }
            Member::query()->lockForUpdate()->findOrFail($current->member_id);
            $debit = LedgerEntry::where('withdrawal_id', $current->id)->where('entry_type', 'withdrawal_debit')->lockForUpdate()->sole();
            $reversal = new LedgerEntry;
            $reversal->forceFill([
                'member_id' => $current->member_id, 'withdrawal_id' => $current->id,
                'entry_type' => 'withdrawal_reversal', 'amount_signed' => -$debit->amount_signed,
                'reversal_of_id' => $debit->id, 'created_by' => $actor->id,
            ])->save();
            $current->forceFill(['status' => 'cancelled', 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancellation_reason' => $reason])->save();
            $this->audit($actor, 'withdrawal.cancelled', $current, ['amount_reversed' => (int) $current->amount, 'reason' => $reason]);

            return $current;
        }, 3);
    }

    private function authorizeOperator(User $actor): void
    {
        abort_unless($actor->status === 'active' && in_array($actor->role, ['admin', 'staff'], true), 403);
    }

    private function lockActor(User $actor, bool $adminOnly): void
    {
        $current = User::query()->sharedLock()->findOrFail($actor->id);
        abort_unless($current->status === 'active' && ($adminOnly ? $current->role === 'admin' : in_array($current->role, ['admin', 'staff'], true)), 403);
    }

    private function audit(User $actor, string $action, Withdrawal $withdrawal, array $metadata): void
    {
        $audit = new AuditLog;
        $audit->forceFill(['actor_id' => $actor->id, 'action' => $action, 'entity_type' => 'withdrawals', 'entity_id' => $withdrawal->id, 'metadata' => ['withdrawal_number' => $withdrawal->withdrawal_number] + $metadata])->save();
    }
}
