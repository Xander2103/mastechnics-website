<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Quote;
use App\Services\QuoteNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for quote integrity: sent quotes are immutable, status
 * transitions follow the state machine, item totals stay within column
 * capacity, and quote numbers keep counting past 9999.
 */
class QuoteIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(array $attrs = []): CustomerRequest
    {
        return CustomerRequest::create(array_merge([
            'locale' => 'nl',
            'service_slug' => 'airco',
            'request_type' => 'install',
            'customer_name' => 'Test Klant',
            'customer_email' => 'test@example.com',
            'description' => 'Test aanvraag',
            'status' => 'new',
        ], $attrs));
    }

    private function makeQuote(CustomerRequest $req, array $attrs = []): Quote
    {
        $quote = Quote::create(array_merge([
            'customer_request_id' => $req->id,
            'quote_number' => 'OFF-' . now()->year . '-0001',
            'quote_status' => 'draft',
        ], $attrs));

        $quote->items()->create([
            'position' => 1,
            'description' => 'Origineel',
            'quantity' => 1,
            'unit_price_excl_vat' => 100,
            'vat_rate' => 21,
            'line_total_excl_vat' => 100,
            'line_vat_amount' => 21,
            'line_total_incl_vat' => 121,
        ]);
        $quote->recalculateTotals();

        return $quote;
    }

    private function payload(float $price = 1000.00, float $quantity = 1): array
    {
        return [
            'items' => [[
                'description' => 'HERSCHREVEN',
                'quantity' => (string) $quantity,
                'unit_price_excl_vat' => (string) $price,
                'vat_rate' => '21',
            ]],
        ];
    }

    public function test_sent_quote_cannot_be_edited(): void
    {
        $req = $this->makeRequest(['status' => 'quote_sent']);
        $quote = $this->makeQuote($req, ['quote_status' => 'sent', 'sent_at' => now()]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), $this->payload())
            ->assertRedirect(route('admin.requests.show', $req))
            ->assertSessionHasErrors('quote');

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.quote.edit', $req))
            ->assertRedirect(route('admin.requests.show', $req));

        $quote->refresh();
        $this->assertSame('sent', $quote->quote_status);
        $this->assertSame('Origineel', $quote->items()->first()->description);
        $this->assertSame('121.00', (string) $quote->amount_incl_vat);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $req))
            ->assertOk()
            ->assertDontSee('✏ Bewerken');
    }

    public function test_accepted_and_rejected_quotes_cannot_be_edited_either(): void
    {
        foreach (['accepted', 'rejected'] as $status) {
            $req = $this->makeRequest();
            $this->makeQuote($req, ['quote_status' => $status, 'quote_number' => 'OFF-' . now()->year . '-' . ($status === 'accepted' ? '0101' : '0102')]);

            $this->withSession($this->adminSession())
                ->post(route('admin.requests.quote.store', $req), $this->payload())
                ->assertSessionHasErrors('quote');
        }
    }

    public function test_draft_quote_can_still_be_edited(): void
    {
        $req = $this->makeRequest();
        $quote = $this->makeQuote($req);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame('HERSCHREVEN', $quote->fresh()->items()->first()->description);
    }

    public function test_draft_quote_cannot_be_accepted_or_rejected_before_it_is_sent(): void
    {
        $req = $this->makeRequest();
        $quote = $this->makeQuote($req);

        foreach (['mark_accepted', 'mark_rejected'] as $action) {
            $this->withSession($this->adminSession())
                ->post(route('admin.requests.quote.action', $req), ['action' => $action])
                ->assertSessionHasErrors('action');
        }

        $this->assertSame('draft', $quote->fresh()->quote_status);
        $this->assertSame('new', $req->fresh()->status);
    }

    public function test_accepted_quote_cannot_be_marked_sent_again(): void
    {
        $req = $this->makeRequest(['status' => 'won', 'won_at' => now()]);
        $quote = $this->makeQuote($req, ['quote_status' => 'accepted', 'accepted_at' => now()]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.action', $req), ['action' => 'mark_sent'])
            ->assertSessionHasErrors('action');

        $this->assertSame('accepted', $quote->fresh()->quote_status);
        $this->assertSame('won', $req->fresh()->status);
    }

    public function test_item_totals_exceeding_column_capacity_are_rejected(): void
    {
        $req = $this->makeRequest();

        // 9999 × 1 000 000 = 9 999 000 000 > decimal(10,2) capacity.
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), $this->payload(1000000, 9999))
            ->assertSessionHasErrors('items.0.unit_price_excl_vat');

        $this->assertDatabaseCount('quotes', 0);
        $this->assertDatabaseCount('quote_items', 0);
    }

    public function test_quote_total_exceeding_column_capacity_is_rejected(): void
    {
        $req = $this->makeRequest();
        $items = [];

        for ($i = 0; $i < 2; $i++) {
            $items[] = [
                'description' => "Lijn {$i}",
                'quantity' => '60',
                'unit_price_excl_vat' => '1000000',
                'vat_rate' => '0',
            ];
        }

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), ['items' => $items])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_quote_number_keeps_counting_past_9999(): void
    {
        $year = now()->year;
        $this->makeQuote($this->makeRequest(), ['quote_number' => "OFF-{$year}-9999"]);
        $this->makeQuote($this->makeRequest(), ['quote_number' => "OFF-{$year}-10000"]);

        $generator = app(QuoteNumberGenerator::class);

        $this->assertSame("OFF-{$year}-10001", $generator->next());
    }

    public function test_quote_number_ignores_other_years_and_malformed_numbers(): void
    {
        $year = now()->year;
        $this->makeQuote($this->makeRequest(), ['quote_number' => 'OFF-' . ($year - 1) . '-0500']);
        $this->makeQuote($this->makeRequest(), ['quote_number' => "OFF-{$year}-0007"]);
        $this->makeQuote($this->makeRequest(), ['quote_number' => "OFF-{$year}-abc"]);

        $this->assertSame("OFF-{$year}-0008", app(QuoteNumberGenerator::class)->next());
    }

    public function test_first_save_uses_generator_and_second_save_keeps_the_number(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), $this->payload());

        $number = $req->fresh()->quote->quote_number;
        $this->assertSame('OFF-' . now()->year . '-0001', $number);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), $this->payload(50));

        $this->assertSame($number, $req->fresh()->quote->quote_number);
        $this->assertDatabaseCount('quotes', 1);
    }

    public function test_status_route_sets_workflow_timestamps(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->patch(route('admin.requests.update-status', $req), ['status' => 'won'])
            ->assertSessionHas('success', 'status_updated');

        $req->refresh();
        $this->assertSame('won', $req->status);
        $this->assertNotNull($req->won_at);
    }

    public function test_search_wildcards_are_escaped(): void
    {
        $this->makeRequest(['customer_name' => 'Jan Janssens']);
        $this->makeRequest(['customer_name' => '100% Piet']);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.requests.index', ['search' => '%']))
            ->assertOk();

        $response->assertSee('100% Piet');
        $response->assertDontSee('Jan Janssens');
    }
}
