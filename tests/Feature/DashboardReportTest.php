<?php

namespace Tests\Feature;

use App\Models\Collector;
use App\Models\Member;
use App\Models\User;
use App\Models\WasteType;
use App\Services\DepositService;
use App\Services\SaleService;
use App\Services\TransactionReversalService;
use Illuminate\Support\Str;
use Tests\PostgreSqlTestCase;

class DashboardReportTest extends PostgreSqlTestCase
{
    private function fixture(): array
    {
        $actor = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($actor);
        $member = Member::create(['member_code' => 'SIM-LAP', 'full_name' => '=DATA SIMULASI Laporan', 'status' => 'active']);
        $type = WasteType::create(['name' => 'DATA SIMULASI PET Laporan', 'current_buy_price' => 1000, 'is_active' => true]);
        $collector = Collector::create(['name' => '@DATA SIMULASI Pengepul Laporan', 'is_active' => true]);
        $deposit = app(DepositService::class)->post([
            'member_id' => $member->id, 'idempotency_key' => (string) Str::uuid(),
            'items' => [['waste_type_id' => $type->id, 'weight_kg' => '5.000', 'expected_price' => 1000]],
        ], $actor);
        $sale = app(SaleService::class)->post([
            'collector_id' => $collector->id, 'idempotency_key' => (string) Str::uuid(),
            'items' => [['waste_type_id' => $type->id, 'weight_kg' => '1.250', 'sell_price' => 1500]],
        ], $actor);
        $deposit->forceFill(['occurred_at' => '2026-10-15 03:00:00+00'])->save();
        $sale->forceFill(['occurred_at' => '2026-10-16 04:00:00+00'])->save();

        return [$actor, $member, $type, $collector, $deposit, $sale];
    }

    public function test_dashboard_shows_active_period_statistics_chart_and_recent_transactions(): void
    {
        [, , , , $deposit, $sale] = $this->fixture();
        $response = $this->get(route('admin.dashboard', ['start_date' => '2026-10-15', 'end_date' => '2026-10-16']));
        $response->assertOk()->assertSee('Tren nilai transaksi')->assertSee('Rp 5.000')->assertSee('Rp 1.875')
            ->assertSee($deposit->deposit_number)->assertSee($sale->sale_number)->assertSee('2026-10-15')->assertSee('2026-10-16');
    }

    public function test_void_transactions_remain_in_reports_but_are_excluded_from_active_value(): void
    {
        [$actor, , , , $deposit, $sale] = $this->fixture();
        app(TransactionReversalService::class)->voidSale($sale, 'DATA SIMULASI batal laporan', $actor);
        $params = ['start_date' => '2026-10-15', 'end_date' => '2026-10-30'];
        $this->get(route('admin.dashboard', $params))->assertOk()->assertSee('Rp 5.000')->assertSee('Rp 0');
        $this->get(route('reports.index', $params))->assertOk()->assertSee('Nilai aktif Rp 5.000')->assertSee('Nilai aktif Rp 0');

        $csv = $this->get(route('reports.export', ['report' => 'sales'] + $params));
        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString($sale->sale_number, $content);
        $this->assertStringContainsString('void', $content);
        $this->assertSame('posted', $deposit->fresh()->status);
    }

    public function test_csv_exports_are_utf8_detailed_and_protected_from_formula_injection(): void
    {
        [, , , , $deposit, $sale] = $this->fixture();
        $params = ['start_date' => '2026-10-01', 'end_date' => '2026-10-31'];
        $depositResponse = $this->get(route('reports.export', ['report' => 'deposits'] + $params));
        $depositResponse->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $depositCsv = $depositResponse->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $depositCsv);
        $this->assertStringContainsString('Harga beli/kg', $depositCsv);
        $this->assertStringContainsString($deposit->deposit_number, $depositCsv);
        $this->assertStringContainsString("'=DATA SIMULASI Laporan", $depositCsv);

        $saleCsv = $this->get(route('reports.export', ['report' => 'sales'] + $params))->streamedContent();
        $this->assertStringContainsString($sale->sale_number, $saleCsv);
        $this->assertStringContainsString("'@DATA SIMULASI Pengepul Laporan", $saleCsv);
        $movementCsv = $this->get(route('reports.export', ['report' => 'stock-movements'] + $params))->streamedContent();
        $this->assertStringContainsString('Berat bertanda kg', $movementCsv);
        $this->assertStringContainsString('-1.250', $movementCsv);
        $this->assertStringNotContainsString("'-1.250", $movementCsv);
    }

    public function test_period_validation_rejects_invalid_reversed_and_excessive_ranges(): void
    {
        $actor = User::factory()->create(['role' => 'staff', 'status' => 'active']);
        $this->actingAs($actor);
        $this->get(route('staff.dashboard', ['start_date' => 'invalid', 'end_date' => '2026-10-01']))->assertSessionHasErrors('start_date');
        $this->get(route('reports.index', ['start_date' => '2026-10-10', 'end_date' => '2026-10-01']))->assertSessionHasErrors('end_date');
        $this->get(route('reports.index', ['start_date' => '2024-01-01', 'end_date' => '2026-01-01']))->assertSessionHasErrors('end_date');
    }

    public function test_reports_require_operator_access_and_unknown_exports_are_not_routable(): void
    {
        $params = ['start_date' => '2026-10-01', 'end_date' => '2026-10-31'];
        $this->get(route('reports.index', $params))->assertRedirect(route('login'));
        $member = Member::create(['member_code' => 'SIM-LAP-AKSES', 'full_name' => 'DATA SIMULASI Akses Laporan', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['role' => 'member', 'status' => 'active', 'member_id' => $member->id]));
        $this->get(route('reports.index', $params))->assertForbidden();
        $this->get('/laporan/ekspor/unknown?start_date=2026-10-01&end_date=2026-10-31')->assertNotFound();
    }
}
