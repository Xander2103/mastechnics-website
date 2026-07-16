<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Quote;
use App\Models\QuoteItem;
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

    private function singleItemPayload(float $price = 1000.00, float $vat = 21.0): array
    {
        return [
            'items' => [
                [
                    'description'         => 'Test post',
                    'quantity'            => '1',
                    'unit_price_excl_vat' => (string) $price,
                    'vat_rate'            => (string) $vat,
                ],
            ],
        ];
    }

    // ── Items: calculation ──────────────────────────────────────────────────

    public function test_store_creates_quote_with_vat_calculation(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), $this->singleItemPayload(1000.00, 21.0))
            ->assertRedirect(route('admin.requests.show', $req));

        $quote = $req->fresh()->quote;
        $this->assertNotNull($quote);
        $this->assertSame('210.00', $quote->amount_vat);
        $this->assertSame('1210.00', $quote->amount_incl_vat);
    }

    public function test_single_item_line_totals_are_correct(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), $this->singleItemPayload(500.00, 6.0));

        $item = $req->fresh()->quote->items->first();
        $this->assertNotNull($item);
        $this->assertSame('500.00', $item->line_total_excl_vat);
        $this->assertSame('30.00', $item->line_vat_amount);
        $this->assertSame('530.00', $item->line_total_incl_vat);
    }

    public function test_multiple_items_sum_to_quote_totals(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), [
                'items' => [
                    ['description' => 'Post A', 'quantity' => '2', 'unit_price_excl_vat' => '100.00', 'vat_rate' => '21'],
                    ['description' => 'Post B', 'quantity' => '1', 'unit_price_excl_vat' => '50.00',  'vat_rate' => '6'],
                ],
            ]);

        $quote = $req->fresh()->quote;
        // A: 2 × 100 = 200 excl, 42 VAT; B: 1 × 50 = 50 excl, 3 VAT
        $this->assertSame('250.00', $quote->amount_excl_vat);
        $this->assertSame('45.00',  $quote->amount_vat);
        $this->assertSame('295.00', $quote->amount_incl_vat);
        $this->assertSame(2, $quote->items()->count());
    }

    public function test_editing_item_updates_quote_totals(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), $this->singleItemPayload(1000.00, 21.0));

        // Edit: change price
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), $this->singleItemPayload(2000.00, 21.0));

        $quote = $req->fresh()->quote;
        $this->assertSame('2000.00', $quote->amount_excl_vat);
        $this->assertSame('420.00',  $quote->amount_vat);
        $this->assertSame('2420.00', $quote->amount_incl_vat);
    }

    public function test_removing_item_updates_quote_totals(): void
    {
        $req = $this->makeRequest();

        // Create with 2 items
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), [
                'items' => [
                    ['description' => 'A', 'quantity' => '1', 'unit_price_excl_vat' => '300.00', 'vat_rate' => '21'],
                    ['description' => 'B', 'quantity' => '1', 'unit_price_excl_vat' => '200.00', 'vat_rate' => '21'],
                ],
            ]);

        // Update with only 1 item (B removed)
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), [
                'items' => [
                    ['description' => 'A', 'quantity' => '1', 'unit_price_excl_vat' => '300.00', 'vat_rate' => '21'],
                ],
            ]);

        $quote = $req->fresh()->quote;
        $this->assertSame('300.00', $quote->amount_excl_vat);
        $this->assertSame(1, $quote->items()->count());
    }

    // ── ensure_default_item backward compat ─────────────────────────────────

    public function test_ensure_default_item_seeds_from_legacy_amount(): void
    {
        $req   = $this->makeRequest();
        $quote = $this->makeQuote($req, [
            'amount_excl_vat' => 500.00,
            'vat_rate'        => 21.00,
            'amount_vat'      => 105.00,
            'amount_incl_vat' => 605.00,
        ]);

        $this->assertSame(0, $quote->items()->count());

        $quote->ensureDefaultItem();

        $item = $quote->items()->first();
        $this->assertNotNull($item);
        $this->assertSame('500.00', $item->unit_price_excl_vat);
        $this->assertSame('21.00', $item->vat_rate);
        $this->assertSame('Offertebedrag', $item->description);
    }

    public function test_ensure_default_item_is_idempotent(): void
    {
        $req   = $this->makeRequest();
        $quote = $this->makeQuote($req, [
            'amount_excl_vat' => 500.00,
            'vat_rate'        => 21.00,
        ]);

        $quote->ensureDefaultItem();
        $quote->ensureDefaultItem(); // second call must not create duplicate

        $this->assertSame(1, $quote->items()->count());
    }

    // ── quote number ────────────────────────────────────────────────────────

    public function test_store_updates_existing_quote_without_duplicate(): void
    {
        $req = $this->makeRequest();
        $this->makeQuote($req, ['title' => 'Oud']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), array_merge(
                ['title' => 'Nieuw'],
                $this->singleItemPayload()
            ));

        $this->assertSame(1, Quote::count());
        $this->assertSame('Nieuw', $req->fresh()->quote->title);
    }

    public function test_store_generates_quote_number_on_first_save(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), $this->singleItemPayload());

        $quote = $req->fresh()->quote;
        $this->assertNotNull($quote->quote_number);
        $this->assertMatchesRegularExpression('/^OFF-\d{4}-\d{4}$/', $quote->quote_number);
    }

    public function test_store_does_not_regenerate_quote_number_on_update(): void
    {
        $req = $this->makeRequest();
        $this->makeQuote($req, ['quote_number' => 'OFF-2026-0001']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), array_merge(
                ['title' => 'Update'],
                $this->singleItemPayload()
            ));

        $this->assertSame('OFF-2026-0001', $req->fresh()->quote->quote_number);
    }

    public function test_quote_number_increments_for_each_new_quote(): void
    {
        $req1 = $this->makeRequest();
        $req2 = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req1), $this->singleItemPayload());

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req2), $this->singleItemPayload());

        $num1 = $req1->fresh()->quote->quote_number;
        $num2 = $req2->fresh()->quote->quote_number;

        $this->assertNotSame($num1, $num2);
        $this->assertSame(1, ((int) substr($num2, -4)) - ((int) substr($num1, -4)));
    }

    // ── validation ──────────────────────────────────────────────────────────

    public function test_store_validates_items_required(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), [])
            ->assertSessionHasErrors('items');
    }

    public function test_store_validates_item_description_required(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), [
                'items' => [
                    ['description' => '', 'quantity' => '1', 'unit_price_excl_vat' => '100', 'vat_rate' => '21'],
                ],
            ])
            ->assertSessionHasErrors('items.0.description');
    }

    public function test_store_validates_amount_must_be_non_negative(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), [
                'items' => [
                    ['description' => 'Test', 'quantity' => '1', 'unit_price_excl_vat' => '-10', 'vat_rate' => '21'],
                ],
            ])
            ->assertSessionHasErrors('items.0.unit_price_excl_vat');
    }

    public function test_store_validates_vat_rate_must_be_non_negative(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $req), [
                'items' => [
                    ['description' => 'Test', 'quantity' => '1', 'unit_price_excl_vat' => '100', 'vat_rate' => '-5'],
                ],
            ])
            ->assertSessionHasErrors('items.0.vat_rate');
    }

    // ── performAction ────────────────────────────────────────────────────────

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

    // ── PDF route ────────────────────────────────────────────────────────────

    public function test_admin_can_access_quote_pdf(): void
    {
        $req   = $this->makeRequest();
        $quote = $this->makeQuote($req, ['quote_number' => 'OFF-2026-0001']);
        QuoteItem::create([
            'quote_id'            => $quote->id,
            'position'            => 1,
            'description'         => 'Test post',
            'quantity'            => 1.00,
            'unit_price_excl_vat' => 500.00,
            'vat_rate'            => 21.00,
            'line_total_excl_vat' => 500.00,
            'line_vat_amount'     => 105.00,
            'line_total_incl_vat' => 605.00,
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.requests.quote.pdf', $req));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_unauthenticated_pdf_redirects_to_login(): void
    {
        $req = $this->makeRequest();
        $this->makeQuote($req);

        $this->get(route('admin.requests.quote.pdf', $req))
            ->assertRedirect(route('admin.login'));
    }

    public function test_pdf_returns_404_when_no_quote(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.quote.pdf', $req))
            ->assertNotFound();
    }

    // ── edit page rendering ─────────────────────────────────────────────────

    public function test_edit_page_renders_for_request_without_quote(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.quote.edit', $req))
            ->assertOk();
    }

    public function test_edit_page_renders_for_request_with_existing_quote(): void
    {
        $req   = $this->makeRequest();
        $quote = $this->makeQuote($req);
        QuoteItem::create([
            'quote_id'            => $quote->id,
            'position'            => 1,
            'description'         => 'Test post',
            'quantity'            => 2.00,
            'unit_price_excl_vat' => 150.00,
            'vat_rate'            => 21.00,
            'line_total_excl_vat' => 300.00,
            'line_vat_amount'     => 63.00,
            'line_total_incl_vat' => 363.00,
        ]);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.quote.edit', $req))
            ->assertOk()
            ->assertSee('300,00', false);
    }

    public function test_edit_page_renders_with_old_input_containing_empty_price(): void
    {
        $req = $this->makeRequest();

        $this->withSession($this->adminSession())
            ->withSession(['_old_input' => [
                'items' => [
                    ['description' => 'Onvolledige regel', 'quantity' => '1', 'unit_price_excl_vat' => '', 'vat_rate' => '21'],
                ],
            ]])
            ->get(route('admin.requests.quote.edit', $req))
            ->assertOk();
    }

    // ── regression: duplicate mobile/desktop item field names ────────────────

    public function test_edit_page_does_not_duplicate_item_field_names(): void
    {
        // Desktop + mobile item inputs used to share the same name="items[idx][...]"
        // attribute, so the browser submitted both and the last one (mobile,
        // untouched by the user) silently won and overwrote the real value.
        $req   = $this->makeRequest();
        $quote = $this->makeQuote($req);
        QuoteItem::create([
            'quote_id'            => $quote->id,
            'position'            => 1,
            'description'         => 'Test post',
            'quantity'            => 2.00,
            'unit_price_excl_vat' => 150.00,
            'vat_rate'            => 21.00,
            'line_total_excl_vat' => 300.00,
            'line_vat_amount'     => 63.00,
            'line_total_incl_vat' => 363.00,
        ]);

        $html = $this->withSession($this->adminSession())
            ->get(route('admin.requests.quote.edit', $req))
            ->assertOk()
            ->getContent();

        foreach (['description', 'quantity', 'unit_price_excl_vat', 'vat_rate'] as $field) {
            $needle = 'name="items[0][' . $field . ']"';
            $this->assertSame(
                1,
                substr_count($html, $needle),
                "Expected exactly one {$needle} in the rendered form, found " . substr_count($html, $needle)
            );
        }
    }
}
