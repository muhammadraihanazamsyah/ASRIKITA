<?php

namespace Tests\Feature;

use App\Models\Collector;
use App\Models\Deposit;
use App\Models\DepositItem;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WasteType;
use Database\Seeders\SimulationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\PostgreSqlTestCase;

class DomainSchemaTest extends PostgreSqlTestCase
{
    private function fixture(): array
    {
        $member = Member::create(['member_code' => 'SIM-TEST', 'full_name' => 'DATA SIMULASI', 'status' => 'active']);
        $staff = User::factory()->create();
        $waste = WasteType::create(['name' => 'DATA SIMULASI PET', 'current_buy_price' => 3000]);
        $deposit = new Deposit;
        $deposit->forceFill([
            'deposit_number' => 'SIM-DEP-001', 'member_id' => $member->id,
            'staff_id' => $staff->id, 'total_amount' => 3702,
        ])->save();
        $item = new DepositItem;
        $item->forceFill([
            'deposit_id' => $deposit->id, 'waste_type_id' => $waste->id,
            'weight_kg' => '1.234', 'buy_price_snapshot' => 3000, 'amount' => 3702,
        ])->save();

        return [$member, $staff, $waste, $deposit, $item];
    }

    private function assertRejected(callable $operation, string $sqlState): void
    {
        DB::beginTransaction();
        try {
            $operation();
            $this->fail('Database accepted invalid data.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_master_seeder_is_repeatable_and_does_not_overwrite_records(): void
    {
        $this->seed(SimulationSeeder::class);
        Member::where('member_code', 'SIM-001')->update(['status' => 'inactive']);
        $this->seed(SimulationSeeder::class);
        $this->assertSame(3, Member::count());
        $this->assertSame(3, WasteType::count());
        $this->assertSame('inactive', Member::where('member_code', 'SIM-001')->value('status'));
        $this->assertSame(0, User::count());
        $this->assertSame(0, Deposit::count());
    }

    public function test_price_snapshot_and_decimal_weight_survive_master_price_change(): void
    {
        [$member, $staff, $waste, $deposit, $item] = $this->fixture();
        $waste->update(['current_buy_price' => 5000]);
        $this->assertSame('1.234', $item->fresh()->weight_kg);
        $this->assertSame(3000, $item->fresh()->buy_price_snapshot);
        $this->assertTrue($deposit->member->is($member));
        $this->assertTrue($deposit->staff->is($staff));
        $this->assertTrue($deposit->items->first()->is($item));
        $this->assertRejected(fn () => DB::table('deposit_items')->where('id', $item->id)->update(['amount' => 3703]), '23514');
        $this->assertRejected(fn () => DB::table('deposit_items')->where('id', $item->id)->update(['weight_kg' => 0]), '23514');
    }

    public function test_unique_member_codes_restricted_deletion_and_member_role_profile(): void
    {
        [$member, $staff] = $this->fixture();
        $this->assertRejected(fn () => Member::create(['member_code' => $member->member_code, 'full_name' => 'Duplicate']), '23505');
        $this->assertRejected(fn () => $member->delete(), '23503');
        $this->assertRejected(fn () => DB::table('users')->where('id', $staff->id)->update(['role' => 'member']), '23514');
    }

    public function test_ledger_and_stock_views_follow_credit_and_reversal(): void
    {
        [$member, $staff, $waste, $deposit, $item] = $this->fixture();
        $credit = new LedgerEntry;
        $credit->forceFill([
            'member_id' => $member->id, 'deposit_id' => $deposit->id,
            'entry_type' => 'deposit_credit', 'amount_signed' => 3702, 'created_by' => $staff->id,
        ])->save();
        $movement = new StockMovement;
        $movement->forceFill([
            'waste_type_id' => $waste->id, 'deposit_item_id' => $item->id,
            'movement_type' => 'deposit_in', 'weight_signed' => '1.234', 'created_by' => $staff->id,
        ])->save();
        $this->assertSame(3702, $member->balanceIdr());
        $this->assertSame('1.234', $waste->stockKg());
        $this->assertSame(3702, (int) DB::table('member_balances')->value('balance_idr'));
        $this->assertSame('1.234', DB::table('waste_stock_balances')->value('stock_kg'));

        $this->assertRejected(fn () => DB::table('ledger_entries')->insert([
            'member_id' => $member->id, 'deposit_id' => $deposit->id,
            'entry_type' => 'deposit_credit', 'amount_signed' => 3702, 'created_by' => $staff->id,
        ]), '23505');

        $reversal = new LedgerEntry;
        $reversal->forceFill([
            'member_id' => $member->id, 'deposit_id' => $deposit->id,
            'entry_type' => 'deposit_reversal', 'amount_signed' => -3702,
            'reversal_of_id' => $credit->id, 'created_by' => $staff->id,
        ])->save();
        $reverseMovement = new StockMovement;
        $reverseMovement->forceFill([
            'waste_type_id' => $waste->id, 'deposit_item_id' => $item->id,
            'movement_type' => 'deposit_reversal', 'weight_signed' => '-1.234', 'created_by' => $staff->id,
        ])->save();
        $this->assertTrue($credit->reversal->is($reversal));
        $this->assertSame(0, $member->balanceIdr());
        $this->assertSame('0.000', $waste->stockKg());
        $this->assertSame(0, (int) DB::table('member_balances')->value('balance_idr'));
        $this->assertSame('0.000', DB::table('waste_stock_balances')->value('stock_kg'));
    }

    public function test_void_requires_reason_actor_and_time_and_stock_requires_one_source(): void
    {
        [, $staff, $waste, $deposit, $item] = $this->fixture();
        $this->assertRejected(fn () => DB::table('deposits')->where('id', $deposit->id)->update(['status' => 'void']), '23514');
        DB::table('deposits')->where('id', $deposit->id)->update([
            'status' => 'void', 'voided_at' => now(), 'voided_by' => $staff->id, 'void_reason' => 'DATA SIMULASI koreksi',
        ]);
        $this->assertNotNull($deposit->fresh()->voided_at);
        $this->assertRejected(fn () => DB::table('stock_movements')->insert([
            'waste_type_id' => $waste->id, 'movement_type' => 'deposit_in',
            'weight_signed' => '1.234', 'created_by' => $staff->id,
        ]), '23514');
        $this->assertRejected(fn () => DB::table('stock_movements')->insert([
            'waste_type_id' => $waste->id, 'deposit_item_id' => $item->id, 'movement_type' => 'deposit_in',
            'weight_signed' => '-1.234', 'created_by' => $staff->id,
        ]), '23514');
    }

    public function test_domain_rollback_preserves_bootstrap_users_and_can_be_migrated_again(): void
    {
        $staff = User::factory()->create();
        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
        $this->assertSame($staff->email, DB::table('users')->where('id', $staff->id)->value('email'));
        Artisan::call('migrate', ['--force' => true]);
        $this->assertSame('disabled', User::findOrFail($staff->id)->status);
    }

    public function test_sales_reduce_only_stock_and_round_each_item_to_whole_rupiah(): void
    {
        [$member, $staff, $waste, $deposit, $item] = $this->fixture();
        DB::table('ledger_entries')->insert([
            'member_id' => $member->id, 'deposit_id' => $deposit->id,
            'entry_type' => 'deposit_credit', 'amount_signed' => 3702, 'created_by' => $staff->id,
        ]);
        DB::table('stock_movements')->insert([
            'waste_type_id' => $waste->id, 'deposit_item_id' => $item->id,
            'movement_type' => 'deposit_in', 'weight_signed' => '1.234', 'created_by' => $staff->id,
        ]);
        $collector = Collector::create(['name' => 'DATA SIMULASI Pengepul']);
        $sale = new Sale;
        $sale->forceFill([
            'sale_number' => 'SIM-SALE-001', 'collector_id' => $collector->id,
            'staff_id' => $staff->id, 'total_amount' => 1,
        ])->save();
        $saleItem = new SaleItem;
        $saleItem->forceFill([
            'sale_id' => $sale->id, 'waste_type_id' => $waste->id,
            'weight_kg' => '0.001', 'sell_price_snapshot' => 500, 'amount' => 1,
        ])->save();
        $this->assertRejected(fn () => DB::table('sale_items')->where('id', $saleItem->id)->update(['amount' => 0]), '23514');
        DB::table('stock_movements')->insert([
            'waste_type_id' => $waste->id, 'sale_item_id' => $saleItem->id,
            'movement_type' => 'sale_out', 'weight_signed' => '-0.001', 'created_by' => $staff->id,
        ]);
        $this->assertTrue($sale->collector->is($collector));
        $this->assertTrue($sale->items->first()->is($saleItem));
        $this->assertSame('1.233', $waste->stockKg());
        $this->assertSame(3702, $member->balanceIdr());
        $this->assertRejected(fn () => DB::table('stock_movements')->insert([
            'waste_type_id' => $waste->id, 'sale_item_id' => $saleItem->id,
            'movement_type' => 'sale_out', 'weight_signed' => '-0.001', 'created_by' => $staff->id,
        ]), '23505');
    }
}
