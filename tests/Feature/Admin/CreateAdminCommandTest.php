<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD_PROMPT = 'Wachtwoord (min. 12 tekens, met letters en cijfers)';

    public function test_command_creates_admin_with_hashed_password(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('E-mailadres', 'xander@example.com')
            ->expectsQuestion('Naam', 'Xander')
            ->expectsQuestion(self::PASSWORD_PROMPT, 'sterk-wachtwoord-42')
            ->expectsQuestion('Bevestig wachtwoord', 'sterk-wachtwoord-42')
            ->expectsConfirmation('Admin-account voor xander@example.com aanmaken?', 'yes')
            ->doesntExpectOutputToContain('sterk-wachtwoord-42')
            ->assertExitCode(0);

        $admin = AdminUser::where('email', 'xander@example.com')->first();
        $this->assertNotNull($admin);
        $this->assertSame('Xander', $admin->name);
        $this->assertNotSame('sterk-wachtwoord-42', $admin->password);
        $this->assertTrue(Hash::check('sterk-wachtwoord-42', $admin->password));
    }

    public function test_email_is_normalized_to_lowercase(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('E-mailadres', '  Xander.VanMalder@Example.COM ')
            ->expectsQuestion('Naam', 'Xander')
            ->expectsQuestion(self::PASSWORD_PROMPT, 'sterk-wachtwoord-42')
            ->expectsQuestion('Bevestig wachtwoord', 'sterk-wachtwoord-42')
            ->expectsConfirmation('Admin-account voor xander.vanmalder@example.com aanmaken?', 'yes')
            ->assertExitCode(0);

        $this->assertDatabaseHas('admin_users', ['email' => 'xander.vanmalder@example.com']);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('E-mailadres', 'geen-email')
            ->expectsOutputToContain('Ongeldig e-mailadres.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('admin_users', 0);
    }

    public function test_weak_password_is_rejected(): void
    {
        foreach (['kort1a', 'alleen-maar-letters', '123456789012'] as $weak) {
            $this->artisan('admin:create')
                ->expectsQuestion('E-mailadres', 'xander@example.com')
                ->expectsQuestion('Naam', 'Xander')
                ->expectsQuestion(self::PASSWORD_PROMPT, $weak)
                ->expectsQuestion('Bevestig wachtwoord', $weak)
                ->expectsOutputToContain('minstens 12 tekens')
                ->assertExitCode(1);
        }

        $this->assertDatabaseCount('admin_users', 0);
    }

    public function test_mismatched_confirmation_is_rejected(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('E-mailadres', 'xander@example.com')
            ->expectsQuestion('Naam', 'Xander')
            ->expectsQuestion(self::PASSWORD_PROMPT, 'sterk-wachtwoord-42')
            ->expectsQuestion('Bevestig wachtwoord', 'iets-anders-42')
            ->expectsOutputToContain('komen niet overeen')
            ->assertExitCode(1);

        $this->assertDatabaseCount('admin_users', 0);
    }

    public function test_duplicate_email_is_rejected_without_update_flag(): void
    {
        AdminUser::create([
            'name' => 'Martin',
            'email' => 'martin@example.com',
            'password' => Hash::make('bestaand-wachtwoord-1'),
        ]);

        $this->artisan('admin:create')
            ->expectsQuestion('E-mailadres', 'martin@example.com')
            ->expectsOutputToContain('bestaat al')
            ->assertExitCode(1);

        $this->assertDatabaseCount('admin_users', 1);
    }

    public function test_update_flag_updates_existing_password_only(): void
    {
        $admin = AdminUser::create([
            'name' => 'Martin',
            'email' => 'martin@example.com',
            'password' => Hash::make('oud-wachtwoord-12'),
        ]);

        $this->artisan('admin:create --update')
            ->expectsQuestion('E-mailadres', 'martin@example.com')
            ->expectsQuestion(self::PASSWORD_PROMPT, 'nieuw-wachtwoord-42')
            ->expectsQuestion('Bevestig wachtwoord', 'nieuw-wachtwoord-42')
            ->expectsConfirmation('Admin-account voor martin@example.com bijwerken?', 'yes')
            ->assertExitCode(0);

        $admin->refresh();
        $this->assertSame('Martin', $admin->name);
        $this->assertTrue(Hash::check('nieuw-wachtwoord-42', $admin->password));
        $this->assertDatabaseCount('admin_users', 1);
    }

    public function test_update_flag_requires_existing_account(): void
    {
        $this->artisan('admin:create --update')
            ->expectsQuestion('E-mailadres', 'onbekend@example.com')
            ->expectsOutputToContain('Geen bestaande admin')
            ->assertExitCode(1);
    }

    public function test_declining_confirmation_changes_nothing(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('E-mailadres', 'xander@example.com')
            ->expectsQuestion('Naam', 'Xander')
            ->expectsQuestion(self::PASSWORD_PROMPT, 'sterk-wachtwoord-42')
            ->expectsQuestion('Bevestig wachtwoord', 'sterk-wachtwoord-42')
            ->expectsConfirmation('Admin-account voor xander@example.com aanmaken?', 'no')
            ->expectsOutputToContain('Geannuleerd')
            ->assertExitCode(0);

        $this->assertDatabaseCount('admin_users', 0);
    }
}
