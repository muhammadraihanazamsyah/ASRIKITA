<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\PostgreSqlTestCase;

class WithdrawalTest extends PostgreSqlTestCase
{
    private function fixture(string $role = 'staff', int $balance = 10000): array
    {
        $actor = User::factory()->create(['role' => $role, 'status' => 'active']);
        $member = Member::create(['member_code' => 'SIM-TRK', 'full_name' => 'DATA SIMULASI Penarikan', 'status' => 'active']);
        $this->seedBalance($member, $actor, $balance);
        $this->actingAs($actor);

        return [$actor, $member, ['member_id' => $member->id, 'amount' => 4000, 'notes' => 'Penarikan simulasi', 'idempotency_key' => (string) Str::uuid()]];
    }

    private function seedBalance(Member $member, User $actor, int $amount): void
    {
        $depositId = DB::table('deposits')->insertGetId([
            'deposit_number' => 'SET-'.Str::ulid(), 'member_id' => $member->id, 'staff_id' => $actor->id,
            'occurred_at' => now(), 'status' => 'posted', 'total_amount' => $amount, 'idempotency_key' => (string) Str::uuid(),
        ]);
        $entry = new LedgerEntry;
        $entry->forceFill(['member_id' => $member->id, 'deposit_id' => $depositId, 'entry_type' => 'deposit_credit', 'amount_signed' => $amount, 'created_by' => $actor->id])->save();
    }

    public function test_admin_and_staff_can_record_without_changing_stock(): void
    {
        [$actor, $member, $data] = $this->fixture();
        $this->get(route('withdrawals.create'))->assertOk()->assertSee($member->member_code)->assertSee('Rp 10.000');
        $this->post(route('withdrawals.store'), $data)->assertSessionHasNoErrors();
        $withdrawal = Withdrawal::sole();
        $this->assertSame($actor->id, (int) $withdrawal->recorded_by);
        $this->assertSame(6000, $member->balanceIdr());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(-4000, (int) LedgerEntry::where('entry_type', 'withdrawal_debit')->value('amount_signed'));
        $this->assertSame('withdrawal.posted', AuditLog::sole()->action);
        $this->get(route('withdrawals.show', $withdrawal))->assertOk()->assertSee('Debit penarikan');
        $this->get(route('balances.ledger', $member))->assertOk()->assertSee('Debit penarikan')->assertSee($withdrawal->withdrawal_number);
    }

    public function test_invalid_inactive_and_over_balance_requests_are_rejected(): void
    {
        [, $member, $data] = $this->fixture();
        foreach ([0, -1, 10001] as $amount) {
            $this->post(route('withdrawals.store'), array_replace($data, ['amount' => $amount, 'idempotency_key' => (string) Str::uuid()]))->assertSessionHasErrors('amount');
        }
        $member->update(['status' => 'inactive']);
        $this->post(route('withdrawals.store'), array_replace($data, ['idempotency_key' => (string) Str::uuid()]))->assertSessionHasErrors('member_id');
        $this->assertSame(0, Withdrawal::count());
    }

    public function test_idempotency_replays_same_request_and_rejects_changed_payload(): void
    {
        [, $member, $data] = $this->fixture();
        $service = app(WithdrawalService::class);
        $first = $service->post($data, auth()->user());
        $second = $service->post($data, auth()->user());
        $this->assertTrue($first->is($second));
        $this->assertSame(6000, $member->balanceIdr());
        $this->post(route('withdrawals.store'), array_replace($data, ['amount' => 3000]))->assertSessionHasErrors('idempotency_key');
        $this->assertSame(1, Withdrawal::count());
    }

    public function test_only_admin_can_cancel_once_and_reversal_restores_balance(): void
    {
        [$staff, $member, $data] = $this->fixture();
        $withdrawal = app(WithdrawalService::class)->post($data, $staff);
        $this->patch(route('withdrawals.destroy', $withdrawal), ['reason' => 'Koreksi simulasi'])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->patch(route('withdrawals.destroy', $withdrawal), ['reason' => 'Koreksi simulasi'])->assertSessionHasNoErrors();
        $this->assertSame(10000, $member->balanceIdr());
        $this->assertSame('cancelled', $withdrawal->fresh()->status);
        $this->assertSame(4000, (int) LedgerEntry::where('entry_type', 'withdrawal_reversal')->value('amount_signed'));
        $this->patch(route('withdrawals.destroy', $withdrawal), ['reason' => 'Ulang koreksi'])->assertSessionHasErrors('reason');
        $this->assertSame(2, AuditLog::count());
    }

    public function test_guest_member_and_inactive_operator_cannot_write(): void
    {
        [, $member, $data] = $this->fixture();
        auth()->logout();
        $this->post(route('withdrawals.store'), $data)->assertRedirect(route('login'));
        $memberUser = User::factory()->create(['role' => 'member', 'status' => 'active', 'member_id' => $member->id]);
        $this->actingAs($memberUser)->post(route('withdrawals.store'), $data)->assertForbidden();
        $inactive = User::factory()->create(['role' => 'staff', 'status' => 'disabled']);
        $this->actingAs($inactive)->post(route('withdrawals.store'), $data)->assertRedirect(route('login'));
        $this->assertSame(0, Withdrawal::count());
    }

    public function test_report_summary_and_csv_include_withdrawal(): void
    {
        [$actor, , $data] = $this->fixture('admin');
        $withdrawal = app(WithdrawalService::class)->post($data, $actor);
        $this->get(route('reports.index'))->assertOk();
        $csv = $this->get(route('reports.export', ['report' => 'withdrawals']))->assertOk()->assertDownload()->streamedContent();
        $this->assertStringContainsString($withdrawal->withdrawal_number, $csv);
    }
}
