<?php

namespace Tests\Feature\Hvac;

use App\Models\CustomerRequest;
use App\Models\HvacBrand;
use App\Models\HvacProduct;
use App\Models\HvacRecommendation;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HvacQuoteConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com'];
    }

    private function makeApprovedRecommendation(string $locale = 'nl'): array
    {
        $brand = HvacBrand::create(['name' => 'TestBrand', 'slug' => 'testbrand']);
        HvacProduct::create([
            'hvac_brand_id' => $brand->id, 'sku' => 'TB-SET', 'model' => 'TestBrand Set 35',
            'name' => 'TestBrand single split set', 'product_type' => 'single_split_set',
            'cooling_capacity_kw' => 3.5, 'maximum_pipe_length_m' => 20, 'maximum_height_difference_m' => 10,
            'default_sale_price_excl_vat' => 1500, 'purchase_price_excl_vat' => 1000,
            'stock_quantity' => 5, 'is_active' => true,
        ]);
        foreach (['wall_bracket', 'vibration_damper', 'pipe', 'trunking', 'electrical_accessory', 'drain_hose', 'condensate_pump'] as $i => $type) {
            HvacProduct::create([
                'hvac_brand_id' => $brand->id, 'sku' => "TB-ACC-{$i}", 'model' => "Accessoire {$type}",
                'name' => "Accessoire {$type}", 'product_type' => $type,
                'default_sale_price_excl_vat' => 10, 'purchase_price_excl_vat' => 5, 'is_active' => true,
            ]);
        }

        $request = CustomerRequest::create([
            'locale' => $locale, 'service_slug' => 'airco', 'request_type' => 'installation',
            'service_category' => 'airco_offerte',
            'customer_name' => 'Test Klant', 'customer_email' => 'test@example.com',
            'description' => '', 'status' => 'new',
            'metadata' => ['answers' => [
                'rooms' => [[
                    'type' => 'slaapkamer', 'width' => 4, 'length' => 5, 'height' => 2.5,
                    'roof_type' => 'none', 'windows' => 'large', 'orientation' => 'south',
                ]],
                'insulation_level' => 'good',
                'customer_type'    => 'residential',
            ]],
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.calculate', $request));

        $recommendation = HvacRecommendation::firstOrFail();
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.approve', [$request, $recommendation]));

        return [$request, $recommendation->fresh()];
    }

    public function test_approved_recommendation_converts_to_draft_quote_with_items(): void
    {
        [$request, $recommendation] = $this->makeApprovedRecommendation();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.convert', [$request, $recommendation]))
            ->assertSessionHas('success', 'hvac_converted');

        $quote = Quote::firstOrFail();
        $this->assertSame('draft', $quote->quote_status);
        $this->assertSame($request->id, $quote->customer_request_id);
        $this->assertMatchesRegularExpression('/^OFF-\d{4}-\d{4}$/', $quote->quote_number);

        // Items preserved 1:1 with SKU in the description and VAT applied.
        $this->assertSame($recommendation->items()->count(), $quote->items()->count());
        $equipmentLine = $quote->items()->where('description', 'like', '%TB-SET%')->first();
        $this->assertNotNull($equipmentLine);
        $this->assertEquals(1500.0, (float) $equipmentLine->unit_price_excl_vat);
        $this->assertEquals(21.0, (float) $equipmentLine->vat_rate);

        // Totals match the recommendation.
        $this->assertEquals($recommendation->subtotal_excl_vat, (float) $quote->amount_excl_vat);
        $this->assertEquals($recommendation->total_incl_vat, (float) $quote->amount_incl_vat);

        // Audit trail preserved in both directions.
        $fresh = $recommendation->fresh();
        $this->assertSame('converted', $fresh->status);
        $this->assertSame($quote->id, $fresh->converted_quote_id);
        $this->assertDatabaseHas('hvac_manual_overrides', [
            'field' => "recommendation:{$recommendation->id}:converted",
        ]);
    }

    public function test_conversion_never_sends_mail(): void
    {
        [$request, $recommendation] = $this->makeApprovedRecommendation();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.convert', [$request, $recommendation]));

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_unapproved_recommendation_cannot_convert(): void
    {
        [$request, $recommendation] = $this->makeApprovedRecommendation();
        $recommendation->update(['status' => 'draft', 'approved_by' => null, 'approved_at' => null]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.convert', [$request, $recommendation]))
            ->assertSessionHasErrors('hvac_convert');

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_existing_quote_blocks_conversion(): void
    {
        [$request, $recommendation] = $this->makeApprovedRecommendation();
        Quote::create([
            'customer_request_id' => $request->id,
            'quote_status'        => 'draft',
            'quote_number'        => 'OFF-2026-0001',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.convert', [$request, $recommendation]))
            ->assertSessionHasErrors('hvac_convert');

        $this->assertDatabaseCount('quotes', 1); // only the pre-existing one
        $this->assertSame('approved', $recommendation->fresh()->status);
    }

    public function test_double_conversion_is_blocked(): void
    {
        [$request, $recommendation] = $this->makeApprovedRecommendation();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.convert', [$request, $recommendation]));

        // Second attempt: status is now 'converted', and a quote exists.
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.convert', [$request, $recommendation]))
            ->assertSessionHasErrors('hvac_convert');

        $this->assertDatabaseCount('quotes', 1);
    }

    public function test_quote_language_follows_request_locale_not_admin(): void
    {
        [$request, $recommendation] = $this->makeApprovedRecommendation('fr');

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.convert', [$request, $recommendation]));

        $this->assertSame('Installation de climatisation — pré-calcul', Quote::first()->title);
    }

    public function test_purchase_prices_never_reach_the_quote(): void
    {
        [$request, $recommendation] = $this->makeApprovedRecommendation();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.convert', [$request, $recommendation]));

        foreach (Quote::first()->items as $item) {
            $this->assertStringNotContainsString('1000', $item->description);
            $this->assertNotEquals(1000.0, (float) $item->unit_price_excl_vat);
        }
    }
}
