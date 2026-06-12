<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestFollowupTest extends TestCase
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
            'service_slug'   => 'airco',
            'request_type'   => 'install',
            'customer_name'  => 'Test Klant',
            'customer_email' => 'test@example.com',
            'description'    => 'Test aanvraag',
            'status'         => 'new',
        ], $attrs));
    }

    // --- mark_viewed ---

    public function test_mark_viewed_sets_status_to_viewed_when_new(): void
    {
        $req = $this->makeRequest(['status' => 'new']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_viewed'])
            ->assertRedirect();

        $this->assertSame('viewed', $req->fresh()->status);
    }

    public function test_mark_viewed_does_not_downgrade_from_contacted(): void
    {
        $req = $this->makeRequest(['status' => 'contacted']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_viewed']);

        $this->assertSame('contacted', $req->fresh()->status);
    }

    public function test_mark_viewed_does_not_downgrade_from_won(): void
    {
        $req = $this->makeRequest(['status' => 'won']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_viewed']);

        $this->assertSame('won', $req->fresh()->status);
    }

    public function test_mark_viewed_does_not_downgrade_from_lost(): void
    {
        $req = $this->makeRequest(['status' => 'lost']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_viewed']);

        $this->assertSame('lost', $req->fresh()->status);
    }

    // --- mark_contacted ---

    public function test_mark_contacted_sets_status_and_timestamp(): void
    {
        $req = $this->makeRequest(['status' => 'new']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_contacted']);

        $fresh = $req->fresh();
        $this->assertSame('contacted', $fresh->status);
        $this->assertNotNull($fresh->contacted_at);
    }

    public function test_mark_contacted_does_not_overwrite_existing_contacted_at(): void
    {
        $original = now()->subDay()->startOfMinute();
        $req      = $this->makeRequest([
            'status'       => 'new',
            'contacted_at' => $original,
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_contacted']);

        $this->assertSame($original->timestamp, $req->fresh()->contacted_at->timestamp);
    }

    // --- mark_quote_sent ---

    public function test_mark_quote_sent_sets_status_and_timestamp(): void
    {
        $req = $this->makeRequest(['status' => 'contacted']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_quote_sent']);

        $fresh = $req->fresh();
        $this->assertSame('quote_sent', $fresh->status);
        $this->assertNotNull($fresh->quote_sent_at);
    }

    public function test_mark_quote_sent_does_not_overwrite_existing_quote_sent_at(): void
    {
        $original = now()->subHours(3)->startOfMinute();
        $req      = $this->makeRequest([
            'status'        => 'contacted',
            'quote_sent_at' => $original,
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_quote_sent']);

        $this->assertSame($original->timestamp, $req->fresh()->quote_sent_at->timestamp);
    }

    // --- mark_won ---

    public function test_mark_won_sets_status_and_timestamp(): void
    {
        $req = $this->makeRequest(['status' => 'quote_sent']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_won']);

        $fresh = $req->fresh();
        $this->assertSame('won', $fresh->status);
        $this->assertNotNull($fresh->won_at);
    }

    public function test_mark_won_does_not_overwrite_existing_won_at(): void
    {
        $original = now()->subHour()->startOfMinute();
        $req      = $this->makeRequest([
            'status' => 'quote_sent',
            'won_at' => $original,
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_won']);

        $this->assertSame($original->timestamp, $req->fresh()->won_at->timestamp);
    }

    // --- mark_lost ---

    public function test_mark_lost_sets_status_and_timestamp(): void
    {
        $req = $this->makeRequest(['status' => 'quote_sent']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_lost']);

        $fresh = $req->fresh();
        $this->assertSame('lost', $fresh->status);
        $this->assertNotNull($fresh->lost_at);
    }

    public function test_mark_lost_does_not_overwrite_existing_lost_at(): void
    {
        $original = now()->subHour()->startOfMinute();
        $req      = $this->makeRequest([
            'status'  => 'quote_sent',
            'lost_at' => $original,
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'mark_lost']);

        $this->assertSame($original->timestamp, $req->fresh()->lost_at->timestamp);
    }

    // --- validation ---

    public function test_invalid_action_returns_validation_error(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.action', $req), ['action' => 'do_something_invalid'])
            ->assertSessionHasErrors('action');
    }

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $req = $this->makeRequest();

        $this->post(route('admin.requests.action', $req), ['action' => 'mark_viewed'])
            ->assertRedirect(route('admin.login'));
    }
}
