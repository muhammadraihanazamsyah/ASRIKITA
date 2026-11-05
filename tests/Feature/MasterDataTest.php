<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Collector;
use App\Models\Deposit;
use App\Models\DepositItem;
use App\Models\Member;
use App\Models\User;
use App\Models\WastePriceHistory;
use App\Models\WasteType;
use Illuminate\Support\Facades\Event;
use Tests\PostgreSqlTestCase;

class MasterDataTest extends PostgreSqlTestCase
{
    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($user);

        return $user;
    }

    private function payload(string $resource): array
    {
        return match ($resource) {
            'nasabah' => ['member_code' => 'sim-new', 'full_name' => 'DATA SIMULASI Nasabah Baru', 'status' => 'active'],
            'jenis-sampah' => ['name' => 'DATA SIMULASI PET Baru', 'current_buy_price' => 3000, 'is_active' => '1', 'note' => 'Harga awal simulasi'],
            'pengepul' => ['name' => 'DATA SIMULASI Pengepul Baru', 'is_active' => '1'],
        };
    }

    public function test_guests_and_staff_cannot_read_or_write_any_master_resource(): void
    {
        foreach (['nasabah', 'jenis-sampah', 'pengepul'] as $resource) {
            $this->get(route('admin.master.index', $resource))->assertRedirect(route('login'));
            $this->post(route('admin.master.store', $resource), $this->payload($resource))->assertRedirect(route('login'));
        }
        $this->actingAs(User::factory()->create(['role' => 'staff', 'status' => 'active']));
        foreach (['nasabah', 'jenis-sampah', 'pengepul'] as $resource) {
            $this->get(route('admin.master.index', $resource))->assertForbidden();
            $this->get(route('admin.master.create', $resource))->assertForbidden();
            $this->get(route('admin.master.edit', [$resource, 1]))->assertForbidden();
            $this->post(route('admin.master.store', $resource), $this->payload($resource))->assertForbidden();
            $this->put(route('admin.master.update', [$resource, 1]), $this->payload($resource))->assertForbidden();
            $this->patch(route('admin.master.deactivate', [$resource, 1]))->assertForbidden();
        }
        $this->assertSame(0, Member::count());
        $this->assertSame(0, WasteType::count());
        $this->assertSame(0, Collector::count());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_admin_creates_reads_updates_and_deactivates_members_without_deleting(): void
    {
        $admin = $this->admin();
        $this->get(route('admin.master.index', 'nasabah'))->assertOk()->assertSee('Belum ada data yang cocok');
        $this->get(route('admin.master.create', 'nasabah'))->assertOk()->assertSee('Kode nasabah');
        $this->post(route('admin.master.store', 'nasabah'), $this->payload('nasabah') + ['role' => 'admin', 'id' => 999])->assertSessionHasNoErrors()->assertRedirect();
        $member = Member::sole();
        $this->assertSame('SIM-NEW', $member->member_code);
        $this->assertNotSame(999, $member->id);
        $this->assertSame($admin->id, AuditLog::sole()->actor_id);
        $this->get(route('admin.master.edit', ['nasabah', $member->id]))->assertOk()->assertSee($member->full_name);
        $this->put(route('admin.master.update', ['nasabah', $member->id]), array_replace($this->payload('nasabah'), ['full_name' => 'DATA SIMULASI Diperbarui', 'phone' => 'SIMULASI', 'address' => 'Alamat simulasi']))->assertSessionHasNoErrors();
        $this->assertSame('DATA SIMULASI Diperbarui', $member->fresh()->full_name);
        $this->patch(route('admin.master.deactivate', ['nasabah', $member->id]))->assertRedirect(route('admin.master.index', 'nasabah'));
        $this->assertSame('inactive', $member->fresh()->status);
        $count = AuditLog::count();
        $this->patch(route('admin.master.deactivate', ['nasabah', $member->id]))->assertRedirect();
        $this->assertSame($count, AuditLog::count());
        $this->put(route('admin.master.update', ['nasabah', $member->id]), $this->payload('nasabah'))->assertSessionHasNoErrors();
        $this->assertSame('active', $member->fresh()->status);
        $this->assertSame(1, Member::count());
    }

    public function test_member_validation_rejects_duplicate_code_and_invalid_fields(): void
    {
        $this->admin();
        $this->post(route('admin.master.store', 'nasabah'), $this->payload('nasabah'))->assertSessionHasNoErrors();
        $this->post(route('admin.master.store', 'nasabah'), $this->payload('nasabah'))->assertSessionHasErrors('member_code');
        $this->post(route('admin.master.store', 'nasabah'), ['member_code' => 'bad code!', 'full_name' => '', 'phone' => str_repeat('1', 31), 'status' => 'pending'])->assertSessionHasErrors(['member_code', 'full_name', 'phone', 'status']);
        $this->assertSame(1, Member::count());
    }

    public function test_collector_can_be_updated_deactivated_and_reactivated(): void
    {
        $this->admin();
        $this->get(route('admin.master.create', 'pengepul'))->assertOk();
        $this->post(route('admin.master.store', 'pengepul'), $this->payload('pengepul'))->assertSessionHasNoErrors();
        $collector = Collector::sole();
        $this->get(route('admin.master.edit', ['pengepul', $collector->id]))->assertOk();
        $this->put(route('admin.master.update', ['pengepul', $collector->id]), ['name' => 'DATA SIMULASI Diperbarui', 'phone' => 'SIMULASI', 'is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($collector->fresh()->is_active);
        $this->put(route('admin.master.update', ['pengepul', $collector->id]), $this->payload('pengepul'))->assertSessionHasNoErrors();
        $this->assertTrue($collector->fresh()->is_active);
        $this->patch(route('admin.master.deactivate', ['pengepul', $collector->id]))->assertRedirect();
        $this->assertFalse($collector->fresh()->is_active);
        $this->assertSame(1, Collector::count());
    }

    public function test_price_history_tracks_actor_and_does_not_repeat_for_metadata_edits(): void
    {
        $admin = $this->admin();
        $this->get(route('admin.master.create', 'jenis-sampah'))->assertOk();
        $this->post(route('admin.master.store', 'jenis-sampah'), $this->payload('jenis-sampah') + ['unit' => 'ton', 'changed_by' => 999])->assertSessionHasNoErrors();
        $waste = WasteType::sole();
        $history = WastePriceHistory::sole();
        $this->assertNull($history->old_buy_price);
        $this->assertSame(3000, $history->new_buy_price);
        $this->assertSame($admin->id, $history->changed_by);
        $this->assertSame('kg', $waste->unit);
        $this->put(route('admin.master.update', ['jenis-sampah', $waste->id]), array_replace($this->payload('jenis-sampah'), ['current_buy_price' => 4500, 'expected_price' => 3000, 'note' => 'Harga baru']))->assertSessionHasNoErrors();
        $latest = WastePriceHistory::orderByDesc('id')->first();
        $this->assertSame(3000, $latest->old_buy_price);
        $this->assertSame(4500, $latest->new_buy_price);
        $this->assertSame($admin->id, $latest->changed_by);
        $this->get(route('admin.master.edit', ['jenis-sampah', $waste->id]))->assertOk()->assertSee('Harga baru')->assertSee('4.500');
        $this->put(route('admin.master.update', ['jenis-sampah', $waste->id]), array_replace($this->payload('jenis-sampah'), ['current_buy_price' => 4500, 'expected_price' => 4500, 'name' => 'DATA SIMULASI PET Revisi']))->assertSessionHasNoErrors();
        $this->assertSame(2, WastePriceHistory::count());
        $this->patch(route('admin.master.deactivate', ['jenis-sampah', $waste->id]))->assertRedirect();
        $this->assertFalse($waste->fresh()->is_active);
        $this->assertSame(2, WastePriceHistory::count());
    }

    public function test_stale_price_submission_is_rejected_and_invalid_prices_do_not_write(): void
    {
        $this->admin();
        $this->post(route('admin.master.store', 'jenis-sampah'), $this->payload('jenis-sampah'))->assertSessionHasNoErrors();
        $waste = WasteType::sole();
        $this->put(route('admin.master.update', ['jenis-sampah', $waste->id]), $this->payload('jenis-sampah') + ['expected_price' => 2000])->assertSessionHasErrors('current_buy_price');
        foreach ([0, -1, '2.5', 'not-a-price', 1000000001] as $price) {
            $this->post(route('admin.master.store', 'jenis-sampah'), array_replace($this->payload('jenis-sampah'), ['name' => 'Invalid', 'current_buy_price' => $price]))->assertSessionHasErrors('current_buy_price');
        }
        $this->post(route('admin.master.store', 'jenis-sampah'), $this->payload('jenis-sampah'))->assertSessionHasErrors('name');
        $this->assertSame(3000, $waste->fresh()->current_buy_price);
        $this->assertSame(1, WasteType::count());
        $this->assertSame(1, WastePriceHistory::count());
        $this->assertSame(1, AuditLog::count());
    }

    public function test_price_and_history_roll_back_if_audit_write_fails(): void
    {
        $this->admin();
        $this->post(route('admin.master.store', 'jenis-sampah'), $this->payload('jenis-sampah'))->assertSessionHasNoErrors();
        $waste = WasteType::sole();
        $event = 'eloquent.creating: '.AuditLog::class;
        Event::listen($event, fn () => throw new \RuntimeException('Simulated audit failure'));
        $this->withoutExceptionHandling();
        try {
            $this->put(route('admin.master.update', ['jenis-sampah', $waste->id]), array_replace($this->payload('jenis-sampah'), ['current_buy_price' => 9000, 'expected_price' => 3000]));
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated audit failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame(3000, $waste->fresh()->current_buy_price);
        $this->assertSame(1, WastePriceHistory::count());
        $this->assertSame(1, AuditLog::count());
    }

    public function test_search_filter_pagination_and_html_escaping(): void
    {
        $this->admin();
        for ($i = 1; $i <= 17; $i++) {
            Member::create(['member_code' => 'SIM-'.$i, 'full_name' => 'DATA SIMULASI Nasabah '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'status' => 'active']);
        }
        Member::create(['member_code' => 'SIM-OFF', 'full_name' => '<script>alert(1)</script>', 'status' => 'inactive']);
        $this->get(route('admin.master.index', 'nasabah'))->assertOk()->assertViewHas('records', fn ($records) => $records->count() === 15 && $records->total() === 18)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('admin.master.index', ['resource' => 'nasabah', 'q' => 'sim-17', 'status' => 'active']))->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 1);
        $this->get(route('admin.master.index', ['resource' => 'nasabah', 'status' => 'inactive']))->assertOk()->assertSee('&lt;script&gt;', false)->assertViewHas('records', fn ($records) => $records->total() === 1);
        $this->get(route('admin.master.index', ['resource' => 'nasabah', 'q' => '%']))->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 0);
    }

    public function test_deactivation_preserves_transactions_and_price_updates_preserve_snapshots(): void
    {
        $admin = $this->admin();
        $member = Member::create(['member_code' => 'SIM-LINK', 'full_name' => 'DATA SIMULASI']);
        $waste = WasteType::create(['name' => 'DATA SIMULASI PET', 'current_buy_price' => 3000]);
        $deposit = new Deposit;
        $deposit->forceFill(['deposit_number' => 'SIM-001', 'member_id' => $member->id, 'staff_id' => $admin->id, 'total_amount' => 3000])->save();
        $item = new DepositItem;
        $item->forceFill(['deposit_id' => $deposit->id, 'waste_type_id' => $waste->id, 'weight_kg' => '1.000', 'buy_price_snapshot' => 3000, 'amount' => 3000])->save();
        $this->patch(route('admin.master.deactivate', ['nasabah', $member->id]))->assertRedirect();
        $this->put(route('admin.master.update', ['jenis-sampah', $waste->id]), ['name' => $waste->name, 'current_buy_price' => 5000, 'expected_price' => 3000, 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('inactive', $member->fresh()->status);
        $this->assertSame(1, Deposit::count());
        $this->assertSame(3000, $item->fresh()->buy_price_snapshot);
        $this->assertSame(3000, $item->fresh()->amount);
    }

    public function test_unknown_resources_missing_records_and_csrf_are_rejected(): void
    {
        $this->admin();
        $this->get('/admin/master/users')->assertNotFound();
        $this->get(route('admin.master.edit', ['nasabah', 999]))->assertNotFound();
        $this->app['env'] = 'local';
        foreach (['nasabah', 'jenis-sampah', 'pengepul'] as $resource) {
            $this->post(route('admin.master.store', $resource), $this->payload($resource))->assertStatus(419);
            $this->put(route('admin.master.update', [$resource, 1]), $this->payload($resource))->assertStatus(419);
            $this->patch(route('admin.master.deactivate', [$resource, 1]))->assertStatus(419);
        }
    }
}
