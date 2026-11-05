<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Deposit;
use App\Models\DepositItem;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WasteType;
use App\Services\DepositService;
use App\Support\DepositInput;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\PostgreSqlTestCase;

class DepositTest extends PostgreSqlTestCase
{
    private function fixture(string $role = 'staff'): array
    {
        $actor = User::factory()->create(['role' => $role, 'status' => 'active']);
        $this->actingAs($actor);
        $member = Member::create(['member_code' => 'SIM-SET', 'full_name' => 'DATA SIMULASI Setoran', 'status' => 'active']);
        $first = WasteType::create(['name' => 'DATA SIMULASI PET', 'current_buy_price' => 500, 'is_active' => true]);
        $second = WasteType::create(['name' => 'DATA SIMULASI Kardus', 'current_buy_price' => 1500, 'is_active' => true]);
        $data = ['member_id' => $member->id, 'idempotency_key' => (string) Str::uuid(), 'items' => [
            ['waste_type_id' => $first->id, 'weight_kg' => '1.001', 'expected_price' => 500],
            ['waste_type_id' => $second->id, 'weight_kg' => '2.333', 'expected_price' => 1500],
        ]];

        return [$actor, $member, $first, $second, $data];
    }

    public function test_multi_item_deposit_updates_ledger_stock_audit_and_read_pages(): void
    {
        [$actor, $member, $first, $second, $data] = $this->fixture();
        $this->get(route('deposits.create'))->assertOk()->assertSee($member->member_code);
        $this->post(route('deposits.store'), $data + ['total_amount' => 1, 'staff_id' => 999, 'status' => 'void'])->assertSessionHasNoErrors()->assertRedirect();
        $deposit = Deposit::sole();
        $this->assertSame(4001, $deposit->total_amount);
        $this->assertSame($actor->id, (int) $deposit->staff_id);
        $this->assertSame('posted', $deposit->status);
        $this->assertSame([501, 3500], $deposit->items()->orderBy('waste_type_id')->pluck('amount')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(4001, $member->balanceIdr());
        $this->assertSame('1.001', $first->stockKg());
        $this->assertSame('2.333', $second->stockKg());
        $this->assertSame(1, LedgerEntry::count());
        $this->assertSame(2, StockMovement::count());
        $this->assertSame('deposit.posted', AuditLog::sole()->action);
        $this->assertSame($actor->id, (int) AuditLog::sole()->actor_id);
        $this->get(route('deposits.show', $deposit))->assertOk()->assertSee('Rp 4.001')->assertSee('1.001');
        $this->get(route('deposits.index', ['q' => 'sim-set']))->assertOk()->assertSee($deposit->deposit_number);
        $this->get(route('balances.members'))->assertOk()->assertSee('Rp 4.001');
        $this->get(route('balances.ledger', $member))->assertOk()->assertSee('Kredit setoran')->assertSee($deposit->deposit_number);
        $this->get(route('balances.stock'))->assertOk()->assertSee('2.333');
        $this->get(route('balances.movements', $first))->assertOk()->assertSee('Setoran masuk')->assertSee($deposit->deposit_number);
    }

    public function test_same_request_is_replayed_once_even_after_master_changes(): void
    {
        [, $member, $first, , $data] = $this->fixture('admin');
        $this->post(route('deposits.store'), $data)->assertSessionHasNoErrors();
        $deposit = Deposit::sole();
        $first->update(['current_buy_price' => 1000, 'is_active' => false]);
        $member->update(['status' => 'inactive']);
        $data['items'] = array_reverse($data['items']);
        $this->post(route('deposits.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('deposits.show', $deposit));
        $this->assertSame(1, Deposit::count());
        $this->assertSame(2, DepositItem::count());
        $this->assertSame(1, LedgerEntry::count());
        $this->assertSame(2, StockMovement::count());
        $this->assertSame(1, AuditLog::count());
        $this->assertSame(4001, $member->balanceIdr());
        $this->assertSame(500, $deposit->items()->where('waste_type_id', $first->id)->first()->buy_price_snapshot);
    }

    public function test_reusing_a_key_with_another_payload_or_operator_is_rejected(): void
    {
        [, , , , $data] = $this->fixture();
        $this->post(route('deposits.store'), $data)->assertSessionHasNoErrors();
        $different = $data;
        $different['items'][0]['weight_kg'] = '2.000';
        $this->post(route('deposits.store'), $different)->assertSessionHasErrors('idempotency_key');
        $this->actingAs(User::factory()->create(['role' => 'staff', 'status' => 'active']));
        $this->post(route('deposits.store'), $data)->assertSessionHasErrors('idempotency_key');
        $this->assertSame(1, Deposit::count());
        $this->assertSame(1, LedgerEntry::count());
    }

    public function test_inactive_missing_and_changed_master_data_are_rejected(): void
    {
        [, $member, $first, , $data] = $this->fixture();
        $member->update(['status' => 'inactive']);
        $this->post(route('deposits.store'), $data)->assertSessionHasErrors('member_id');
        $member->update(['status' => 'active']);
        $this->post(route('deposits.store'), array_replace($data, ['member_id' => 999999]))->assertSessionHasErrors('member_id');
        $first->update(['is_active' => false]);
        $this->post(route('deposits.store'), $data)->assertSessionHasErrors('items');
        $first->update(['is_active' => true, 'current_buy_price' => 600]);
        $this->post(route('deposits.store'), $data)->assertSessionHasErrors('items');
        $first->delete();
        $this->post(route('deposits.store'), $data)->assertSessionHasErrors('items');
        $this->assertSame(0, Deposit::count());
        $this->assertSame(0, LedgerEntry::count());
        $this->assertSame(0, StockMovement::count());
    }

    public function test_precision_bounds_duplicate_types_and_zero_value_are_rejected(): void
    {
        [, , $first, , $data] = $this->fixture();
        foreach (['0', '-1', '1.0001', '1,250', '1e3', 'NaN', '1000000.001'] as $weight) {
            $invalid = $data;
            $invalid['items'][0]['weight_kg'] = $weight;
            $this->post(route('deposits.store'), $invalid)->assertSessionHasErrors('items.0.weight_kg');
        }
        $invalid = $data;
        $invalid['items'][1]['waste_type_id'] = $first->id;
        $this->post(route('deposits.store'), $invalid)->assertSessionHasErrors('items.0.waste_type_id');
        $this->post(route('deposits.store'), array_replace($data, ['items' => []]))->assertSessionHasErrors('items');
        $this->post(route('deposits.store'), array_replace($data, ['idempotency_key' => 'invalid']))->assertSessionHasErrors('idempotency_key');
        $first->update(['current_buy_price' => 499]);
        $data['items'] = [['waste_type_id' => $first->id, 'weight_kg' => '0.001', 'expected_price' => 499]];
        $this->post(route('deposits.store'), $data)->assertSessionHasErrors('items');
        $this->assertSame(0, Deposit::count());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_all_writes_roll_back_when_audit_fails(): void
    {
        [$actor, $member, $first, , $data] = $this->fixture();
        $event = 'eloquent.creating: '.AuditLog::class;
        Event::listen($event, fn () => throw new \RuntimeException('Simulated failure'));
        try {
            app(DepositService::class)->post($data, $actor);
            $this->fail('Expected failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        foreach ([Deposit::class, DepositItem::class, LedgerEntry::class, StockMovement::class, AuditLog::class] as $model) {
            $this->assertSame(0, $model::count());
        }
        $this->assertSame(0, $member->balanceIdr());
        app(DepositService::class)->post($data, $actor);
        $this->assertSame(1, Deposit::count());
        $this->assertSame('1.001', $first->stockKg());
    }

    public function test_guests_members_disabled_users_and_missing_csrf_cannot_post(): void
    {
        [$actor, $member, $first, , $data] = $this->fixture();
        auth()->logout();
        $this->post(route('deposits.store'), $data)->assertRedirect(route('login'));
        $user = User::factory()->create(['role' => 'member', 'status' => 'active', 'member_id' => $member->id]);
        $this->actingAs($user);
        foreach (['deposits.index', 'deposits.create', 'balances.members', 'balances.stock'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get(route('balances.ledger', $member))->assertForbidden();
        $this->get(route('balances.movements', $first))->assertForbidden();
        $this->post(route('deposits.store'), $data)->assertForbidden();
        $actor->status = 'disabled';
        $actor->save();
        $this->actingAs($actor)->post(route('deposits.store'), $data)->assertRedirect(route('login'));
        $actor->status = 'active';
        $actor->save();
        $this->actingAs($actor);
        $this->app['env'] = 'local';
        $this->post(route('deposits.store'), $data)->assertStatus(419);
        $this->assertSame(0, Deposit::count());
    }

    public function test_empty_states_and_no_edit_or_delete_endpoints(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $this->get(route('deposits.create'))->assertOk()->assertSee('Belum ada nasabah aktif');
        $this->get(route('deposits.index'))->assertOk()->assertSee('Belum ada setoran');
        $this->get(route('deposits.show', 999))->assertNotFound();
        $this->put('/setoran/999', [])->assertStatus(405);
        $this->delete('/setoran/999')->assertStatus(405);
    }

    public function test_integer_calculation_and_display_remain_exact_at_supported_bounds(): void
    {
        $this->assertSame(1, DepositInput::amount(1, 500));
        $this->assertSame(0, DepositInput::amount(1, 499));
        $this->assertSame(1000000000000000, DepositInput::amount(1000000000, 1000000000));
        $this->assertSame('Rp 50.000.000.000.000.001', DepositInput::rupiah('50000000000000001'));
    }
}
