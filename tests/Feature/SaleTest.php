<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Collector;
use App\Models\Member;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WasteType;
use App\Services\DepositService;
use App\Services\SaleService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\PostgreSqlTestCase;

class SaleTest extends PostgreSqlTestCase
{
    private function fixture(string $role = 'staff'): array
    {
        $actor = User::factory()->create(['role' => $role, 'status' => 'active']);
        $this->actingAs($actor);
        $member = Member::create(['member_code' => 'SIM-JUAL', 'full_name' => 'DATA SIMULASI Stok Jual', 'status' => 'active']);
        $first = WasteType::create(['name' => 'DATA SIMULASI PET Jual', 'current_buy_price' => 1000, 'is_active' => true]);
        $second = WasteType::create(['name' => 'DATA SIMULASI Kardus Jual', 'current_buy_price' => 500, 'is_active' => true]);
        app(DepositService::class)->post([
            'member_id' => $member->id, 'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['waste_type_id' => $first->id, 'weight_kg' => '5.000', 'expected_price' => 1000],
                ['waste_type_id' => $second->id, 'weight_kg' => '3.500', 'expected_price' => 500],
            ],
        ], $actor);
        $collector = Collector::create(['name' => 'DATA SIMULASI Pengepul Jual', 'is_active' => true]);
        $data = ['collector_id' => $collector->id, 'idempotency_key' => (string) Str::uuid(), 'items' => [
            ['waste_type_id' => $first->id, 'weight_kg' => '1.001', 'sell_price' => 1500],
            ['waste_type_id' => $second->id, 'weight_kg' => '2.333', 'sell_price' => 1000],
        ]];

        return [$actor, $collector, $first, $second, $data];
    }

    public function test_multi_item_sale_reduces_stock_and_creates_snapshot_audit_and_read_pages(): void
    {
        [$actor, $collector, $first, $second, $data] = $this->fixture();
        $this->get(route('sales.create'))->assertOk()->assertSee($collector->name)->assertSee('5.000 kg');
        $this->post(route('sales.store'), $data + ['total_amount' => 1, 'staff_id' => 999, 'status' => 'void'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $sale = Sale::sole();
        $this->assertSame(3835, $sale->total_amount);
        $this->assertSame($actor->id, (int) $sale->staff_id);
        $this->assertSame('posted', $sale->status);
        $this->assertSame([1502, 2333], $sale->items()->orderBy('waste_type_id')->pluck('amount')->map(fn ($value) => (int) $value)->all());
        $this->assertSame('3.999', $first->stockKg());
        $this->assertSame('1.167', $second->stockKg());
        $this->assertSame(4, StockMovement::count());
        $this->assertSame('sale.posted', AuditLog::where('entity_type', 'sales')->sole()->action);
        $this->get(route('sales.show', $sale))->assertOk()->assertSee('Rp 3.835')->assertSee('Rp 1.500');
        $this->get(route('sales.index', ['q' => 'pengepul jual']))->assertOk()->assertSee($sale->sale_number);
        $this->get(route('balances.stock'))->assertOk()->assertSee('3.999')->assertSee('1.167');
        $this->get(route('balances.movements', $first))->assertOk()->assertSee('Penjualan keluar')->assertSee($sale->sale_number);
    }

    public function test_replay_is_idempotent_after_master_and_stock_changes(): void
    {
        [, $collector, $first, , $data] = $this->fixture('admin');
        $this->post(route('sales.store'), $data)->assertSessionHasNoErrors();
        $sale = Sale::sole();
        $collector->update(['is_active' => false]);
        $first->update(['is_active' => false]);
        $data['items'] = array_reverse($data['items']);
        $this->post(route('sales.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('sales.show', $sale));
        $this->assertSame(1, Sale::count());
        $this->assertSame(2, SaleItem::count());
        $this->assertSame(4, StockMovement::count());
        $this->assertSame(1, AuditLog::where('entity_type', 'sales')->count());
        $this->assertSame(1500, $sale->items()->where('waste_type_id', $first->id)->first()->sell_price_snapshot);
    }

    public function test_overselling_inactive_master_and_changed_idempotent_payload_are_rejected(): void
    {
        [, $collector, $first, , $data] = $this->fixture();
        $oversold = $data;
        $oversold['items'][0]['weight_kg'] = '5.001';
        $this->post(route('sales.store'), $oversold)->assertSessionHasErrors('items');
        $collector->update(['is_active' => false]);
        $this->post(route('sales.store'), $data)->assertSessionHasErrors('collector_id');
        $collector->update(['is_active' => true]);
        $first->update(['is_active' => false]);
        $this->post(route('sales.store'), $data)->assertSessionHasErrors('items');
        $first->update(['is_active' => true]);
        $this->post(route('sales.store'), $data)->assertSessionHasNoErrors();
        $changed = $data;
        $changed['items'][0]['sell_price'] = 1600;
        $this->post(route('sales.store'), $changed)->assertSessionHasErrors('idempotency_key');
        $this->actingAs(User::factory()->create(['role' => 'staff', 'status' => 'active']));
        $this->post(route('sales.store'), $data)->assertSessionHasErrors('idempotency_key');
        $this->assertSame(1, Sale::count());
    }

    public function test_invalid_precision_price_duplicates_and_zero_rounded_value_are_rejected(): void
    {
        [, , $first, , $data] = $this->fixture();
        foreach (['0', '-1', '1.0001', '1,250', '1e3', '1000000.001'] as $weight) {
            $invalid = $data;
            $invalid['items'][0]['weight_kg'] = $weight;
            $this->post(route('sales.store'), $invalid)->assertSessionHasErrors('items.0.weight_kg');
        }
        foreach ([0, -1, 1000000001, '1.5'] as $price) {
            $invalid = $data;
            $invalid['items'][0]['sell_price'] = $price;
            $this->post(route('sales.store'), $invalid)->assertSessionHasErrors('items.0.sell_price');
        }
        $invalid = $data;
        $invalid['items'][1]['waste_type_id'] = $first->id;
        $this->post(route('sales.store'), $invalid)->assertSessionHasErrors('items.0.waste_type_id');
        $data['items'] = [['waste_type_id' => $first->id, 'weight_kg' => '0.001', 'sell_price' => 499]];
        $this->post(route('sales.store'), $data)->assertSessionHasErrors('items');
        $this->assertSame(0, Sale::count());
    }

    public function test_all_sale_writes_roll_back_when_audit_fails(): void
    {
        [$actor, , $first, , $data] = $this->fixture();
        Event::listen('eloquent.creating: '.AuditLog::class, fn () => throw new \RuntimeException('Simulated sale failure'));
        try {
            app(SaleService::class)->post($data, $actor);
            $this->fail('Expected failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated sale failure', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, SaleItem::count());
        $this->assertSame('5.000', $first->stockKg());
        app(SaleService::class)->post($data, $actor);
        $this->assertSame('3.999', $first->stockKg());
    }

    public function test_access_empty_states_and_no_edit_or_delete_endpoints(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin);
        $this->get(route('sales.create'))->assertOk()->assertSee('Belum ada pengepul aktif');
        $this->get(route('sales.index'))->assertOk()->assertSee('Belum ada penjualan');
        $this->get(route('sales.show', 999))->assertNotFound();
        $this->put('/penjualan/999')->assertStatus(405);
        $this->delete('/penjualan/999')->assertStatus(405);
        auth()->logout();
        $this->get(route('sales.index'))->assertRedirect(route('login'));
        $member = Member::create(['member_code' => 'SIM-AKSES', 'full_name' => 'DATA SIMULASI Akses', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['role' => 'member', 'status' => 'active', 'member_id' => $member->id]));
        $this->get(route('sales.index'))->assertForbidden();
        $this->post(route('sales.store'), [])->assertForbidden();
    }
}
