<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\CustomerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): AdminUser
    {
        return AdminUser::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('CorrectHorse123!'),
        ]);
    }

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com', 'admin_user_name' => 'Admin'];
    }

    public function test_account_page_renders(): void
    {
        $this->makeAdmin();

        $this->withSession($this->adminSession())
            ->get(route('admin.account.edit'))
            ->assertOk()
            ->assertSee('E-mailadres wijzigen')
            ->assertSee('Wachtwoord wijzigen');
    }

    public function test_account_page_lists_all_admins_without_password_data(): void
    {
        $this->makeAdmin();
        AdminUser::create([
            'name' => 'Xander',
            'email' => 'xander@test.com',
            'password' => Hash::make('AnderWachtwoord123'),
        ]);

        $this->withSession($this->adminSession())
            ->get(route('admin.account.edit'))
            ->assertOk()
            ->assertSee('Beheerders')
            ->assertSee('admin@test.com')
            ->assertSee('xander@test.com')
            ->assertDontSee('AnderWachtwoord123');
    }

    public function test_two_admin_accounts_can_coexist_and_change_independently(): void
    {
        $martin = $this->makeAdmin();
        $xander = AdminUser::create([
            'name' => 'Xander',
            'email' => 'xander@test.com',
            'password' => Hash::make('XanderWachtwoord1'),
        ]);

        // Martin changes his own email + password; only his row changes.
        $this->withSession($this->adminSession())
            ->patch(route('admin.account.email.update'), [
                'email' => 'martin-nieuw@test.com',
                'current_password' => 'CorrectHorse123!',
            ])
            ->assertSessionHas('success', 'account_email_updated');

        $this->withSession(['admin_user_email' => 'martin-nieuw@test.com'])
            ->patch(route('admin.account.password.update'), [
                'current_password' => 'CorrectHorse123!',
                'password' => 'NieuwWachtwoord42abc',
                'password_confirmation' => 'NieuwWachtwoord42abc',
            ])
            ->assertSessionHas('success', 'account_password_updated');

        $xander->refresh();
        $this->assertSame('xander@test.com', $xander->email);
        $this->assertTrue(Hash::check('XanderWachtwoord1', $xander->password));

        // Xander changes his password; Martin's row stays untouched.
        $this->withSession(['admin_user_email' => 'xander@test.com'])
            ->patch(route('admin.account.password.update'), [
                'current_password' => 'XanderWachtwoord1',
                'password' => 'XanderNieuw42abcdef',
                'password_confirmation' => 'XanderNieuw42abcdef',
            ])
            ->assertSessionHas('success', 'account_password_updated');

        $martin->refresh();
        $this->assertSame('martin-nieuw@test.com', $martin->email);
        $this->assertTrue(Hash::check('NieuwWachtwoord42abc', $martin->password));
    }

    public function test_password_without_numbers_is_rejected(): void
    {
        $this->makeAdmin();

        $this->withSession($this->adminSession())
            ->patch(route('admin.account.password.update'), [
                'current_password' => 'CorrectHorse123!',
                'password' => 'alleenmaarletters',
                'password_confirmation' => 'alleenmaarletters',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_password_without_letters_is_rejected(): void
    {
        $this->makeAdmin();

        $this->withSession($this->adminSession())
            ->patch(route('admin.account.password.update'), [
                'current_password' => 'CorrectHorse123!',
                'password' => '123456789012345',
                'password_confirmation' => '123456789012345',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_email_change_updates_database_and_session(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->withSession($this->adminSession())
            ->patch(route('admin.account.email.update'), [
                'email' => 'nieuw@test.com',
                'current_password' => 'CorrectHorse123!',
            ]);

        $response->assertRedirect()->assertSessionHas('success', 'account_email_updated');
        $this->assertSame('nieuw@test.com', $admin->fresh()->email);
        $this->assertSame('nieuw@test.com', session('admin_user_email'));
    }

    public function test_email_change_rejects_wrong_current_password(): void
    {
        $admin = $this->makeAdmin();

        $this->withSession($this->adminSession())
            ->patch(route('admin.account.email.update'), [
                'email' => 'nieuw@test.com',
                'current_password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertSame('admin@test.com', $admin->fresh()->email);
        $this->assertSame('admin@test.com', session('admin_user_email'));
    }

    public function test_email_change_preserves_historical_standard_reply_sender(): void
    {
        $this->makeAdmin();
        $request = CustomerRequest::create([
            'locale' => 'nl',
            'service_slug' => 'airco',
            'request_type' => 'installation',
            'customer_name' => 'Test Klant',
            'customer_email' => 'klant@example.com',
            'description' => 'Test aanvraag',
            'status' => 'contacted',
            'standard_reply_sent_at' => now()->subDay(),
            'standard_reply_sent_by' => 'admin@test.com',
        ]);

        $this->withSession($this->adminSession())
            ->patch(route('admin.account.email.update'), [
                'email' => 'nieuw@test.com',
                'current_password' => 'CorrectHorse123!',
            ]);

        $this->assertSame('admin@test.com', $request->fresh()->standard_reply_sent_by);
    }

    public function test_email_change_rejects_duplicate_email(): void
    {
        $this->makeAdmin();
        AdminUser::create([
            'name' => 'Other',
            'email' => 'other@test.com',
            'password' => Hash::make('SomethingElse123!'),
        ]);

        $this->withSession($this->adminSession())
            ->patch(route('admin.account.email.update'), [
                'email' => 'other@test.com',
                'current_password' => 'CorrectHorse123!',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_password_change_stores_hashed_password(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->withSession($this->adminSession())
            ->patch(route('admin.account.password.update'), [
                'current_password' => 'CorrectHorse123!',
                'password' => 'NewSecurePass456!',
                'password_confirmation' => 'NewSecurePass456!',
            ]);

        $response->assertRedirect()->assertSessionHas('success', 'account_password_updated');

        $fresh = $admin->fresh();
        $this->assertNotSame('NewSecurePass456!', $fresh->password);
        $this->assertTrue(Hash::check('NewSecurePass456!', $fresh->password));
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $admin = $this->makeAdmin();

        $this->withSession($this->adminSession())
            ->patch(route('admin.account.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'NewSecurePass456!',
                'password_confirmation' => 'NewSecurePass456!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('CorrectHorse123!', $admin->fresh()->password));
    }

    public function test_password_change_requires_confirmation_and_min_length(): void
    {
        $this->makeAdmin();

        $this->withSession($this->adminSession())
            ->patch(route('admin.account.password.update'), [
                'current_password' => 'CorrectHorse123!',
                'password' => 'short',
                'password_confirmation' => 'mismatch',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_password_update_route_is_rate_limited(): void
    {
        $this->makeAdmin();

        for ($i = 0; $i < 10; $i++) {
            $this->withSession($this->adminSession())
                ->patch(route('admin.account.password.update'), [
                    'current_password' => 'wrong-password',
                    'password' => 'NewSecurePass456!',
                    'password_confirmation' => 'NewSecurePass456!',
                ])
                ->assertStatus(302);
        }

        $this->withSession($this->adminSession())
            ->patch(route('admin.account.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'NewSecurePass456!',
                'password_confirmation' => 'NewSecurePass456!',
            ])
            ->assertStatus(429);
    }

    public function test_unauthenticated_cannot_access_account_routes(): void
    {
        $this->makeAdmin();

        $this->get(route('admin.account.edit'))->assertRedirect(route('admin.login'));
        $this->patch(route('admin.account.email.update'), [])->assertRedirect(route('admin.login'));
        $this->patch(route('admin.account.password.update'), [])->assertRedirect(route('admin.login'));
    }
}
