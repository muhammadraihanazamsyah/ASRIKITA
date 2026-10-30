<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Collector;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WasteType;
use App\Services\DepositService;
use App\Services\SaleService;
use App\Services\TransactionReversalService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\PostgreSqlTestCase;

class TransactionReversalTest extends PostgreSqlTestCase
{
    private function fixture(): array
    {
        $actor = User::factory()->create(['role' => 'staff', 'status' => 'active']);
        $this->actingAs($actor);
        $member = Member::create(['member_code' => 'SIM-BATAL', 'full_name' => 'DATA SIMULASI Pembatalan', 'status' => 'active']);
        $first = WasteType::create(['name' => 'DATA SIMULASI PET Batal', 'current_buy_price' => 1000, 'is_active' => true]);
        $second = WasteType::create(['name' => 'DATA SIMULASI Kardus Batal', 'current_buy_price' => 500, 'is_active' => true]);
        $deposit = app(DepositService::class)->post([
            'member_id' => $member->id, 'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['waste_type_id' => $first->id, 'weight_kg' => '5.000', 'expected_price' => 1000],
                ['waste_type_id' => $second->id, 'weight_kg' => '3.500', 'expected_price' => 500],
            ],
        ], $actor);
        $collector = Collector::create(['name' => 'DATA SIMULASI Pengepul Batal', 'is_active' => true]);

        return [$actor, $member, $first, $second, $collector, $deposit];
    }

    private function sale(User $actor, Collector $collector, WasteType $first, WasteType $second): Sale
    {
        return app(SaleService::class)->post([
            'collector_id' => $collector->id, 'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['waste_type_id' => $first->id, 'weight_kg' => '1.250', 'sell_price' => 1500],
                ['waste_type_id' => $second->id, 'weight_kg' => '0.500', 'sell_price' => 900],
            ],
        ], $actor);
    }

    public function test_voiding_deposit_reverses_balance_and_stock_and_records_reason(): void
    {
        [$actor, $member, $first, $second, , $deposit] = $this->fixture();
        $this->patch(route('deposits.destroy', $deposit), ['reason' => 'DATA SIMULASI salah timbang'])
            ->assertSessionHasNoErrors()->assertRedirect(route('deposits.show', $deposit));

        $deposit->refresh();
        $this->assertSame('void', $deposit->status);
        $this->assertSame($actor->id, (int) $deposit->voided_by);
        $this->assertSame('DATA SIMULASI salah timbang', $deposit->void_reason);
        $this->assertSame(0, $member->balanceIdr());
        $this->assertSame('0.000', $first->stockKg());
        $this->assertSame('0.000', $second->stockKg());
        $this->assertSame(2, LedgerEntry::count());
        $credit = LedgerEntry::where('entry_type', 'deposit_credit')->sole();
        $reversal = LedgerEntry::where('entry_type', 'deposit_reversal')->sole();
        $this->assertSame(-$credit->amount_signed, $reversal->amount_signed);
        $this->assertSame($credit->id, (int) $reversal->reversal_of_id);
        $this->assertSame(4, StockMovement::count());
        $this->assertSame('deposit.voided', AuditLog::where('entity_type', 'deposits')->latest('id')->first()->action);
        $this->get(route('deposits.show', $deposit))->assertOk()->assertSee('Setoran telah dibatalkan')->assertSee($deposit->void_reason)->assertSee('Mutasi balik');
    }

    public function test_deposit_cannot_be_voided_when_its_stock_has_been_sold(): void
    {
        [$actor, $member, $first, $second, $collector, $deposit] = $this->fixture();
        $sale = $this->sale($actor, $collector, $first, $second);
        $this->patch(route('deposits.destroy', $deposit), ['reason' => 'DATA SIMULASI koreksi transaksi'])
            ->assertSessionHasErrors('reason');

        $this->assertSame('posted', $deposit->fresh()->status);
        $this->assertSame('posted', $sale->fresh()->status);
        $this->assertSame(6750, $member->balanceIdr());
        $this->assertSame('3.750', $first->stockKg());
        $this->assertSame('3.000', $second->stockKg());
        $this->assertSame(1, LedgerEntry::count());
        $this->assertSame(4, StockMovement::count());
        $this->assertSame(0, AuditLog::where('action', 'deposit.voided')->count());
    }

    public function test_voiding_sale_restores_stock_without_changing_member_balance(): void
    {
        [$actor, $member, $first, $second, $collector] = $this->fixture();
        $sale = $this->sale($actor, $collector, $first, $second);
        $balance = $member->balanceIdr();
        $this->patch(route('sales.destroy', $sale), ['reason' => 'DATA SIMULASI pengepul membatalkan'])
            ->assertSessionHasNoErrors()->assertRedirect(route('sales.show', $sale));

        $sale->refresh();
        $this->assertSame('void', $sale->status);
        $this->assertSame('5.000', $first->stockKg());
        $this->assertSame('3.500', $second->stockKg());
        $this->assertSame($balance, $member->balanceIdr());
        $this->assertSame(6, StockMovement::count());
        $this->assertSame(2, StockMovement::where('movement_type', 'sale_reversal')->count());
        $this->assertSame('sale.voided', AuditLog::where('entity_type', 'sales')->latest('id')->first()->action);
        $this->get(route('sales.show', $sale))->assertOk()->assertSee('Penjualan telah dibatalkan')->assertSee($sale->void_reason);
        $this->get(route('balances.movements', $first))->assertOk()->assertSee('Pembatalan penjualan')->assertSee($sale->sale_number);
    }

    public function test_identical_retry_is_safe_but_different_second_cancellation_is_rejected(): void
    {
        [$actor, , $first, $second, $collector] = $this->fixture();
        $sale = $this->sale($actor, $collector, $first, $second);
        $reason = 'DATA SIMULASI transaksi ganda';
        $this->patch(route('sales.destroy', $sale), ['reason' => $reason])->assertSessionHasNoErrors();
        $this->patch(route('sales.destroy', $sale), ['reason' => $reason])->assertSessionHasNoErrors();
        $this->assertSame(2, StockMovement::where('movement_type', 'sale_reversal')->count());
        $this->assertSame(1, AuditLog::where('action', 'sale.voided')->count());

        $this->patch(route('sales.destroy', $sale), ['reason' => 'DATA SIMULASI alasan berbeda'])->assertSessionHasErrors('reason');
        $other = User::factory()->create(['role' => 'staff', 'status' => 'active']);
        $this->actingAs($other)->patch(route('sales.destroy', $sale), ['reason' => $reason])->assertSessionHasErrors('reason');
        $this->assertSame($actor->id, (int) $sale->fresh()->voided_by);
    }

    public function test_reversal_rolls_back_when_audit_write_fails(): void
    {
        [$actor, $member, $first, , , $deposit] = $this->fixture();
        Event::listen('eloquent.creating: '.AuditLog::class, fn () => throw new \RuntimeException('Simulated reversal failure'));
        try {
            app(TransactionReversalService::class)->voidDeposit($deposit, 'DATA SIMULASI audit gagal', $actor);
            $this->fail('Expected failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated reversal failure', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }
        $this->assertSame('posted', $deposit->fresh()->status);
        $this->assertSame(6750, $member->balanceIdr());
        $this->assertSame('5.000', $first->stockKg());
        $this->assertSame(1, LedgerEntry::count());
        $this->assertSame(2, StockMovement::count());
    }

    public function test_reason_access_and_csrf_are_validated(): void
    {
        [$actor, $member, , , , $deposit] = $this->fixture();
        foreach (['', 'abc', str_repeat('x', 1001)] as $reason) {
            $this->patch(route('deposits.destroy', $deposit), ['reason' => $reason])->assertSessionHasErrors('reason');
        }
        auth()->logout();
        $this->patch(route('deposits.destroy', $deposit), ['reason' => 'DATA SIMULASI valid'])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'member', 'status' => 'active', 'member_id' => $member->id]));
        $this->patch(route('deposits.destroy', $deposit), ['reason' => 'DATA SIMULASI valid'])->assertForbidden();
        $actor->status = 'disabled';
        $actor->save();
        $this->actingAs($actor)->patch(route('deposits.destroy', $deposit), ['reason' => 'DATA SIMULASI valid'])->assertRedirect(route('login'));
        $actor->status = 'active';
        $actor->save();
        $this->actingAs($actor);
        $this->app['env'] = 'local';
        $this->patch(route('deposits.destroy', $deposit), ['reason' => 'DATA SIMULASI valid'])->assertStatus(419);
        $this->assertSame('posted', $deposit->fresh()->status);
    }
}
