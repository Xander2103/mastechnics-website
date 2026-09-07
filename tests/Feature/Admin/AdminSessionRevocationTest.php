<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression tests: an admin session must stop working the moment the
 * account is deleted or its password changes. Before this guard existed the
 * middleware only checked that a session key was present, so a stale cookie
 * kept full admin access for up to SESSION_LIFETIME.
 */
class AdminSessionRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_without_matching_admin_account_is_rejected(): void
    {
        $this->withSession(['admin_user_email' => 'ghost@nowhere.test', 'admin_user_name' => 'Ghost'])
            ->get(route('admin.requests.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_session_of_deleted_admin_is_rejected(): void
    {
        $session = $this->adminSession('martin@test.com');

        $this->withSession($session)->get(route('admin.requests.index'))->assertOk();

        AdminUser::where('email', 'martin@test.com')->delete();

        $this->withSession($session)
            ->get(route('admin.requests.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_session_without_fingerprint_is_rejected_even_when_account_exists(): void
    {
        $this->adminSession('martin@test.com');

        $this->withSession(['admin_user_email' => 'martin@test.com'])
            ->get(route('admin.requests.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_password_change_invalidates_other_sessions_but_keeps_the_current_one(): void
    {
        $adminUser = AdminUser::create([
            'name' => 'Martin',
            'email' => 'martin@test.com',
            'password' => Hash::make('OudWachtwoord123'),
        ]);
        $staleSession = $adminUser->sessionPayload();

        $this->withSession($staleSession)
            ->patch(route('admin.account.password.update'), [
                'current_password' => 'OudWachtwoord123',
                'password' => 'NieuwWachtwoord456',
                'password_confirmation' => 'NieuwWachtwoord456',
            ])
            ->assertSessionHas('success', 'account_password_updated');

        // The session that changed the password stays valid...
        $this->assertSame(
            $adminUser->fresh()->sessionFingerprint(),
            session(AdminUser::SESSION_FINGERPRINT_KEY)
        );
        $this->get(route('admin.requests.index'))->assertOk();

        // ...while a second browser still carrying the old fingerprint is out.
        $this->flushSession();
        $this->withSession($staleSession)
            ->get(route('admin.requests.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_login_stores_fingerprint_and_accepts_mixed_case_email(): void
    {
        $adminUser = AdminUser::create([
            'name' => 'Martin',
            'email' => 'martin@test.com',
            'password' => Hash::make('CorrectHorse123'),
        ]);

        $this->post(route('admin.login.submit'), [
            'email' => 'Martin@Test.com',
            'password' => 'CorrectHorse123',
        ])->assertRedirect(route('admin.requests.index'));

        $this->assertSame($adminUser->sessionFingerprint(), session(AdminUser::SESSION_FINGERPRINT_KEY));
        $this->get(route('admin.requests.index'))->assertOk();
    }

    public function test_logout_invalidates_the_whole_session(): void
    {
        $session = $this->adminSession('martin@test.com') + ['unrelated' => 'value'];

        $this->withSession($session)
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertFalse(session()->has('admin_user_email'));
        $this->assertFalse(session()->has('unrelated'));
    }
}
