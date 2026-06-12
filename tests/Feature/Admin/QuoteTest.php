<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteTest extends TestCase
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

    private function makeQuote(CustomerRequest $req, array $attrs = []): Quote
    {
        return Quote::create(array_merge([
            'customer_request_id' => $req->id,
            'quote_status'        => 'draft',
        ], $attrs));
    }

    // --- store: VAT calculation ---

    public function test_store_creates_quote_with_vat_calculation(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), [
                'amount_excl_vat' => '1000.00',
                'vat_rate'        => '21',
            ])
            ->assertRedirect(route('admin.requests.show', $req));

        $quote = $req->fresh()->quote;
        $this->assertNotNull($quote);
        $this->assertSame('210.00', $quote->amount_vat);
        $this->assertSame('1210.00', $quote->amount_incl_vat);
    }

    public function test_store_updates_existing_quote_without_duplicate(): void
    {
        $req = $this->makeRequest();
        $this->makeQuote($req, ['title' => 'Oud']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), [
                'title' => 'Nieuw',
            ]);

        $this->assertSame(1, Quote::count());
        $this->assertSame('Nieuw', $req->fresh()->quote->title);
    }

    public function test_store_generates_quote_number_on_first_save(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), []);

        $quote = $req->fresh()->quote;
        $this->assertNotNull($quote->quote_number);
        $this->assertMatchesRegularExpression('/^OFF-\d{4}-\d{4}$/', $quote->quote_number);
    }

    public function test_store_does_not_regenerate_quote_number_on_update(): void
    {
        $req = $this->makeRequest();
        $this->makeQuote($req, ['quote_number' => 'OFF-2026-0001']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), ['title' => 'Update']);

        $this->assertSame('OFF-2026-0001', $req->fresh()->quote->quote_number);
    }

    public function test_quote_number_increments_for_each_new_quote(): void
    {
        $req1 = $this->makeRequest();
        $req2 = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req1), []);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req2), []);

        $num1 = $req1->fresh()->quote->quote_number;
        $num2 = $req2->fresh()->quote->quote_number;

        $this->assertNotSame($num1, $num2);
        $suffix1 = (int) substr($num1, -4);
        $suffix2 = (int) substr($num2, -4);
        $this->assertSame(1, $suffix2 - $suffix1);
    }

    // --- store: validation ---

    public function test_store_validates_amount_must_be_numeric(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), [
                'amount_excl_vat' => 'niet-numeriek',
            ])
            ->assertSessionHasErrors('amount_excl_vat');
    }

    public function test_store_validates_amount_must_be_non_negative(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), [
                'amount_excl_vat' => '-10',
            ])
            ->assertSessionHasErrors('amount_excl_vat');
    }

    public function test_store_validates_vat_rate_must_be_non_negative(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), [
                'vat_rate' => '-5',
            ])
            ->assertSessionHasErrors('vat_rate');
    }

    // --- performAction: mark_sent ---

    public function test_mark_sent_sets_quote_status_and_request_status(): void
    {
        $req   = $this->makeRequest(['status' => 'new']);
        $quote = $this->makeQuote($req);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.action', $req), ['action' => 'mark_sent']);

        $this->assertSame('sent', $quote->fresh()->quote_status);
        $this->assertSame('quote_sent', $req->fresh()->status);
        $this->assertNotNull($quote->fresh()->sent_at);
    }

    public function test_mark_sent_does_not_overwrite_existing_sent_at(): void
    {
        $original = now()->subDay()->startOfMinute();
        $req      = $this->makeRequest();
        $quote    = $this->makeQuote($req, ['sent_at' => $original]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.action', $req), ['action' => 'mark_sent']);

        $this->assertSame($original->timestamp, $quote->fresh()->sent_at->timestamp);
    }

    // --- performAction: mark_accepted ---

    public function test_mark_accepted_sets_quote_status_and_request_status(): void
    {
        $req   = $this->makeRequest(['status' => 'quote_sent']);
        $quote = $this->makeQuote($req, ['quote_status' => 'sent']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.action', $req), ['action' => 'mark_accepted']);

        $this->assertSame('accepted', $quote->fresh()->quote_status);
        $this->assertSame('won', $req->fresh()->status);
        $this->assertNotNull($quote->fresh()->accepted_at);
    }

    public function test_mark_accepted_does_not_overwrite_existing_accepted_at(): void
    {
        $original = now()->subHour()->startOfMinute();
        $req      = $this->makeRequest();
        $quote    = $this->makeQuote($req, ['accepted_at' => $original]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.action', $req), ['action' => 'mark_accepted']);

        $this->assertSame($original->timestamp, $quote->fresh()->accepted_at->timestamp);
    }

    // --- performAction: mark_rejected ---

    public function test_mark_rejected_sets_quote_status_and_request_status(): void
    {
        $req   = $this->makeRequest(['status' => 'quote_sent']);
        $quote = $this->makeQuote($req, ['quote_status' => 'sent']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.action', $req), ['action' => 'mark_rejected']);

        $this->assertSame('rejected', $quote->fresh()->quote_status);
        $this->assertSame('lost', $req->fresh()->status);
        $this->assertNotNull($quote->fresh()->rejected_at);
    }

    public function test_mark_rejected_does_not_overwrite_existing_rejected_at(): void
    {
        $original = now()->subHour()->startOfMinute();
        $req      = $this->makeRequest();
        $quote    = $this->makeQuote($req, ['rejected_at' => $original]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.action', $req), ['action' => 'mark_rejected']);

        $this->assertSame($original->timestamp, $quote->fresh()->rejected_at->timestamp);
    }

    // --- validation / auth ---

    public function test_invalid_action_returns_validation_error(): void
    {
        $req   = $this->makeRequest();
        $quote = $this->makeQuote($req);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.action', $req), ['action' => 'invalid_action'])
            ->assertSessionHasErrors('action');
    }

    public function test_unauthenticated_store_redirects_to_login(): void
    {
        $req = $this->makeRequest();

        $this->post(route('admin.requests.quote.store', $req), [])
            ->assertRedirect(route('admin.login'));
    }
}
