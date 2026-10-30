<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\PostgreSqlTestCase;

class AuthenticationTest extends PostgreSqlTestCase
{
    private function operator(string $role = 'admin', string $status = 'active'): User
    {
        return User::factory()->create(['email' => 'operator@example.test', 'password' => 'TestPassword123!', 'role' => $role, 'status' => $status]);
    }

    public function test_guests_are_redirected_to_login_and_login_form_is_available(): void
    {
        foreach (['dashboard', 'admin.dashboard', 'staff.dashboard'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $this->get(route('login'))->assertOk()->assertSee('Selamat datang kembali')->assertSee('name="_token"', false);
    }

    public function test_active_admin_can_login_and_receives_only_admin_dashboard(): void
    {
        $user = $this->operator();
        $this->withSession(['url.intended' => route('staff.dashboard')]);
        $oldSession = session()->getId();
        $this->post(route('login.store'), ['email' => ' OPERATOR@EXAMPLE.TEST ', 'password' => 'TestPassword123!', 'remember' => '1'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertNotNull($user->fresh()->remember_token);
        $this->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Registrasi dan pengelolaan akun');
        $this->get(route('staff.dashboard'))->assertForbidden();
    }

    public function test_staff_cannot_access_admin_dashboard_by_direct_url(): void
    {
        $user = $this->operator('staff');
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'TestPassword123!'])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertRedirect(route('staff.dashboard'));
        $this->get(route('staff.dashboard'))->assertOk()->assertSee('Pencatatan setoran')->assertDontSee('Pengelolaan pengguna');
        $this->get(route('admin.dashboard'))->assertForbidden()->assertSee('Akses tidak tersedia');
    }

    public function test_invalid_unknown_and_disabled_logins_have_same_generic_error(): void
    {
        $user = $this->operator('admin', 'disabled');
        $message = 'Tidak dapat masuk. Periksa email dan kata sandi atau hubungi administrator.';
        foreach ([[$user->email, 'WrongPassword123!'], [$user->email, 'TestPassword123!'], ['missing@example.test', 'TestPassword123!']] as [$email, $password]) {
            $this->post(route('login.store'), compact('email', 'password'))->assertSessionHasErrors(['email' => $message])->assertSessionMissing('_old_input.password');
            $this->assertGuest();
        }
    }

    public function test_member_cannot_login_or_access_operator_routes(): void
    {
        $member = Member::create(['member_code' => 'SIM-AUTH', 'full_name' => 'DATA SIMULASI']);
        $user = User::factory()->create(['role' => 'member', 'status' => 'active', 'member_id' => $member->id, 'password' => 'TestPassword123!']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'TestPassword123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user);
        foreach (['dashboard', 'admin.dashboard', 'staff.dashboard'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_disabling_a_logged_in_account_revokes_its_access(): void
    {
        $user = $this->operator();
        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
        $user->status = 'disabled';
        $user->save();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_logout_invalidates_session_and_rotates_csrf_token(): void
    {
        $this->actingAs($this->operator())->withSession(['private_marker' => 'private']);
        $oldToken = session()->token();
        $this->post(route('logout'))->assertRedirect(route('login'))->assertSessionMissing('private_marker');
        $this->assertGuest();
        $this->assertNotSame($oldToken, session()->token());
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('logout'))->assertStatus(405);
    }

    public function test_login_is_limited_after_five_failed_attempts(): void
    {
        $user = $this->operator();
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'WrongPassword123!'])->assertSessionHasErrors('email');
        }
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'TestPassword123!'])->assertSessionHasErrors('email');
        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('email'));
        $this->assertGuest();
        RateLimiter::clear('login:'.hash('sha256', $user->email.'|127.0.0.1'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'TestPassword123!'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_validates_input_and_does_not_flash_password(): void
    {
        $this->post(route('login.store'), ['email' => 'invalid', 'password' => 'secret'])->assertSessionHasErrors('email')->assertSessionMissing('_old_input.password');
        $this->post(route('login.store'), ['email' => 'valid@example.test'])->assertSessionHasErrors('password');
    }

    public function test_operator_command_creates_hashed_active_account(): void
    {
        $this->artisan('asrikita:create-operator')
            ->expectsQuestion('Nama operator', 'DATA SIMULASI Admin')
            ->expectsQuestion('Email operator', 'ADMIN@EXAMPLE.TEST')
            ->expectsChoice('Peran', 'admin', ['staff', 'admin'])
            ->expectsQuestion('Kata sandi (minimal 12 karakter, huruf besar/kecil dan angka)', 'TestPassword123!')
            ->expectsQuestion('Ulangi kata sandi', 'TestPassword123!')
            ->assertSuccessful();
        $user = User::where('email', 'admin@example.test')->firstOrFail();
        $this->assertSame('admin', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertTrue(Hash::check('TestPassword123!', $user->password));
        $this->assertNotSame('TestPassword123!', $user->password);
    }

    public function test_login_and_logout_require_valid_csrf_tokens(): void
    {
        // Laravel normally bypasses CSRF in tests; enable the real middleware path.
        $this->app['env'] = 'local';
        $this->post(route('login.store'), ['email' => 'test@example.test', 'password' => 'password'])->assertStatus(419);
        $this->actingAs($this->operator());
        $this->post(route('logout'))->assertStatus(419);
        $this->assertAuthenticated();
        $this->withSession(['_token' => 'csrf-test-token'])->post(route('logout'), ['_token' => 'csrf-test-token'])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_operator_command_rejects_duplicate_email_and_weak_password(): void
    {
        $user = $this->operator('staff');
        $this->artisan('asrikita:create-operator')
            ->expectsQuestion('Nama operator', 'Replacement')
            ->expectsQuestion('Email operator', $user->email)
            ->expectsChoice('Peran', 'admin', ['staff', 'admin'])
            ->expectsQuestion('Kata sandi (minimal 12 karakter, huruf besar/kecil dan angka)', 'short')
            ->expectsQuestion('Ulangi kata sandi', 'short')
            ->assertFailed();
        $this->assertSame(1, User::count());
        $this->assertSame('staff', $user->fresh()->role);
        $this->assertTrue(Hash::check('TestPassword123!', $user->fresh()->password));
    }
}
