<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com'];
    }

    private function makeRequest(array $attrs = []): CustomerRequest
    {
        return CustomerRequest::create(array_merge([
            'locale'         => 'nl',
            'service_slug'   => 'heating',
            'request_type'   => 'repair',
            'customer_name'  => 'Test Klant',
            'customer_email' => 'test@example.com',
            'description'    => 'Test aanvraag',
            'status'         => 'new',
        ], $attrs));
    }

    public function test_dashboard_renders_widget_cards(): void
    {
        $this->makeRequest(['status' => 'won', 'won_at' => now()]);
        $this->makeRequest(['status' => 'lost', 'lost_at' => now()]);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSee('Opvolgingen vandaag')
            ->assertSee('Offertes wachten op antwoord')
            ->assertSee('Gewonnen deze maand')
            ->assertSee('Verloren deze maand')
            ->assertSee('Statistieken')
            ->assertSee('Aanvragen per maand');
    }

    public function test_dashboard_shows_notification_for_new_requests(): void
    {
        $this->makeRequest();

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSee('nieuwe aanvraag');
    }

    public function test_dashboard_shows_recent_activity_from_notes(): void
    {
        $req = $this->makeRequest();
        $req->notes()->create(['author_email' => 'admin@test.com', 'body' => 'Klant teruggebeld, komt terug volgende week.']);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSee('Recente activiteit')
            ->assertSee('Klant teruggebeld, komt terug volgende week.');
    }

    public function test_has_quote_filter_narrows_results(): void
    {
        $withQuote = $this->makeRequest(['customer_name' => 'Heeft Offerte']);
        Quote::create(['customer_request_id' => $withQuote->id, 'quote_status' => 'draft']);

        $this->makeRequest(['customer_name' => 'Geen Offerte']);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.requests.index', ['has_quote' => 'yes']));

        $response->assertOk()
            ->assertSee('Heeft Offerte')
            ->assertDontSee('Geen Offerte');
    }

    public function test_dashboard_requires_admin_auth(): void
    {
        $this->get(route('admin.requests.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_notifications_render_before_stats_and_widgets(): void
    {
        $this->makeRequest();

        $html = $this->withSession($this->adminSession())
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->getContent();

        $notificationPos = strpos($html, 'id="adminNotificationCenter"');
        $statsRowPos      = strpos($html, 'admin-stats-row');
        $activityPos      = strpos($html, 'id="recentActivityToggle"');
        $statisticsPos    = strpos($html, 'id="statisticsToggle"');

        $this->assertNotFalse($notificationPos, 'Notification center should be present when there are new requests.');
        $this->assertLessThan($statsRowPos, $notificationPos);
        $this->assertLessThan($activityPos, $notificationPos);
        $this->assertLessThan($statisticsPos, $notificationPos);
    }

    public function test_recent_activity_and_statistics_are_collapsed_by_default(): void
    {
        $req = $this->makeRequest();
        $req->notes()->create(['author_email' => 'admin@test.com', 'body' => 'Notitie voor tijdlijn.']);

        $html = $this->withSession($this->adminSession())
            ->get(route('admin.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="recentActivityToggle"[^>]*aria-expanded="false"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="statisticsToggle"[^>]*aria-expanded="false"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="recentActivityBody"[^>]*hidden/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="statisticsBody"[^>]*hidden/',
            $html
        );
        $this->assertStringContainsString('aria-controls="recentActivityBody"', $html);
        $this->assertStringContainsString('aria-controls="statisticsBody"', $html);
    }
}
