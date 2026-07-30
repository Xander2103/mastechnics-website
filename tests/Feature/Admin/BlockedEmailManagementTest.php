<?php

namespace Tests\Feature\Admin;

use App\Models\BlockedEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockedEmailManagementTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com', 'admin_user_name' => 'Admin'];
    }

    public function test_unauthenticated_cannot_manage_blocks(): void
    {
        $this->get(route('admin.blocked-emails.index'))
            ->assertRedirect(route('admin.login'));

        $this->post(route('admin.blocked-emails.store'), [
            'email' => 'spam@example.com',
            'duration' => 'permanent',
        ])->assertRedirect(route('admin.login'));

        $this->assertDatabaseCount('blocked_emails', 0);
    }

    public function test_index_renders_form_list_and_confirm_modal(): void
    {
        BlockedEmail::create([
            'email' => 'spam@example.com',
            'reason' => 'Herhaalde spam',
            'blocked_by' => 'admin@test.com',
            'is_active' => true,
        ]);

        $this->withSession($this->adminSession())
            ->get(route('admin.blocked-emails.index'))
            ->assertOk()
            ->assertSee('Geblokkeerde e-mails')
            ->assertSee('spam@example.com')
            ->assertSee('Herhaalde spam')
            ->assertSee('Actief')
            ->assertSee('Deblokkeren')
            ->assertSee('id="admin-confirm-modal"', false)
            ->assertSee('data-confirm-form="#block-email-form"', false);
    }

    public function test_admin_can_add_permanent_block_with_normalized_email(): void
    {
        $this->withSession($this->adminSession())
            ->post(route('admin.blocked-emails.store'), [
                'email' => '  Spam@Example.COM ',
                'reason' => 'Scam berichten',
                'duration' => 'permanent',
            ])
            ->assertRedirect(route('admin.blocked-emails.index'))
            ->assertSessionHas('success', 'email_blocked');

        $this->assertDatabaseHas('blocked_emails', [
            'email' => 'spam@example.com',
            'reason' => 'Scam berichten',
            'blocked_by' => 'admin@test.com',
            'is_active' => true,
            'expires_at' => null,
        ]);
    }

    public function test_temporary_block_sets_expiry(): void
    {
        $this->withSession($this->adminSession())
            ->post(route('admin.blocked-emails.store'), [
                'email' => 'spam@example.com',
                'duration' => '24h',
            ]);

        $block = BlockedEmail::firstOrFail();
        $this->assertNotNull($block->expires_at);
        $this->assertTrue($block->expires_at->between(now()->addHours(23), now()->addHours(25)));
    }

    public function test_invalid_duration_is_rejected(): void
    {
        $this->withSession($this->adminSession())
            ->post(route('admin.blocked-emails.store'), [
                'email' => 'spam@example.com',
                'duration' => 'forever',
            ])
            ->assertSessionHasErrors('duration');

        $this->assertDatabaseCount('blocked_emails', 0);
    }

    public function test_admin_can_unblock(): void
    {
        $block = BlockedEmail::create([
            'email' => 'spam@example.com',
            'blocked_by' => 'admin@test.com',
            'is_active' => true,
        ]);

        $this->withSession($this->adminSession())
            ->patch(route('admin.blocked-emails.unblock', $block))
            ->assertRedirect(route('admin.blocked-emails.index'))
            ->assertSessionHas('success', 'email_unblocked');

        $this->assertDatabaseHas('blocked_emails', [
            'id' => $block->id,
            'is_active' => false,
        ]);
    }

    public function test_reblocking_reactivates_existing_row(): void
    {
        BlockedEmail::create([
            'email' => 'spam@example.com',
            'blocked_by' => 'old@test.com',
            'is_active' => false,
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.blocked-emails.store'), [
                'email' => 'Spam@example.com',
                'reason' => 'Opnieuw spam',
                'duration' => '7d',
            ]);

        $this->assertDatabaseCount('blocked_emails', 1);
        $this->assertDatabaseHas('blocked_emails', [
            'email' => 'spam@example.com',
            'is_active' => true,
            'reason' => 'Opnieuw spam',
            'blocked_by' => 'admin@test.com',
        ]);
    }
}
