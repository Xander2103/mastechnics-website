<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PageSeeder::class);
    }

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com', 'admin_user_name' => 'Admin'];
    }

    public function test_authenticated_admin_sees_admin_navigation_on_admin_pages(): void
    {
        $this->withSession($this->adminSession())
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSee('aria-label="Adminnavigatie"', false)
            ->assertSee('admin-header-badge', false)
            ->assertSee('Aanvragen')
            ->assertSee('Geblokkeerde e-mails')
            ->assertSee('Uitloggen');
    }

    public function test_admin_pages_do_not_render_public_marketing_navigation(): void
    {
        $this->withSession($this->adminSession())
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->assertDontSee('class="site-header"', false)
            ->assertDontSee('Start aanvraag')
            ->assertDontSee('language-switcher', false)
            ->assertDontSee('services-dropdown', false);
    }

    public function test_active_nav_item_gets_aria_current(): void
    {
        $requestsHtml = $this->withSession($this->adminSession())
            ->get(route('admin.requests.index'))
            ->getContent();

        $this->assertSame(1, substr_count($requestsHtml, 'aria-current="page"'));
        $this->assertMatchesRegularExpression(
            '/aria-current="page"\s*>Aanvragen</',
            $requestsHtml
        );

        $accountHtml = $this->withSession($this->adminSession())
            ->get(route('admin.account.edit'))
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/aria-current="page"\s*>Account</',
            $accountHtml
        );

        $blockedHtml = $this->withSession($this->adminSession())
            ->get(route('admin.blocked-emails.index'))
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/aria-current="page"\s*>Geblokkeerde e-mails</',
            $blockedHtml
        );
    }

    public function test_mobile_toggle_has_accessible_attributes(): void
    {
        $this->withSession($this->adminSession())
            ->get(route('admin.requests.index'))
            ->assertSee('aria-controls="adminHeaderMenu"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('aria-label="Adminmenu openen"', false);
    }

    public function test_logged_out_visitor_sees_no_admin_navigation(): void
    {
        $this->get('/nl')
            ->assertOk()
            ->assertDontSee('Adminnavigatie')
            ->assertDontSee('admin-header-badge', false)
            ->assertDontSee('Uitloggen');
    }

    public function test_footer_no_longer_duplicates_account_and_logout_links(): void
    {
        // Public page with an active admin session: only the "Admin panel"
        // shortcut remains in the footer.
        $publicHtml = $this->withSession($this->adminSession())
            ->get('/nl')
            ->assertOk()
            ->assertSee('Admin panel')
            ->assertDontSee('Uitloggen')
            ->getContent();

        $this->assertStringNotContainsString(route('admin.account.edit'), $publicHtml);

        // Admin page: Uitloggen appears exactly once (in the admin header),
        // and the footer contains no admin links at all.
        $adminHtml = $this->withSession($this->adminSession())
            ->get(route('admin.requests.index'))
            ->getContent();

        $this->assertSame(1, substr_count($adminHtml, 'Uitloggen'));
        $this->assertStringNotContainsString('footer-admin-link', $adminHtml);
    }

    public function test_account_and_blocked_emails_links_resolve(): void
    {
        $this->withSession($this->adminSession())
            ->get(route('admin.account.edit'))
            ->assertOk();

        $this->withSession($this->adminSession())
            ->get(route('admin.blocked-emails.index'))
            ->assertOk();
    }

    public function test_logout_uses_post_and_ends_the_session(): void
    {
        AdminUser::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('CorrectHorse123!'),
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertFalse(session()->has('admin_user_email'));

        // GET on the logout endpoint is not a valid route method.
        $this->withSession($this->adminSession())
            ->get('/admin/logout')
            ->assertStatus(405);
    }
}
