<?php

namespace Tests\Feature\Hvac;

use App\Models\CustomerRequest;
use App\Models\HvacBrand;
use App\Models\HvacCalculation;
use App\Models\HvacProduct;
use App\Services\Hvac\HvacCalculationService;
use App\Services\Hvac\HvacManualOverrideService;
use App\Services\Hvac\HvacRecommendationBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HvacRecommendationTest extends TestCase
{
    use RefreshDatabase;

    private HvacBrand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->brand = HvacBrand::create(['name' => 'TestBrand', 'slug' => 'testbrand']);
    }

    private function product(array $attrs = []): HvacProduct
    {
        static $i = 0;
        $i++;

        return HvacProduct::create(array_merge([
            'hvac_brand_id'               => $this->brand->id,
            'sku'                         => "TB-R{$i}",
            'model'                       => "TestBrand R{$i}",
            'name'                        => "TestBrand product R{$i}",
            'product_type'                => 'single_split_set',
            'cooling_capacity_kw'         => 3.5,
            'maximum_pipe_length_m'       => 20,
            'maximum_height_difference_m' => 10,
            'default_sale_price_excl_vat' => 1500,
            'purchase_price_excl_vat'     => 1000,
            'stock_quantity'              => 5,
            'lead_time_days'              => 3,
            'is_active'                   => true,
        ], $attrs));
    }

    private function seedAccessories(): void
    {
        foreach (['wall_bracket', 'vibration_damper', 'pipe', 'trunking', 'electrical_accessory', 'drain_hose', 'condensate_pump'] as $type) {
            $this->product([
                'product_type'                => $type,
                'cooling_capacity_kw'         => null,
                'default_sale_price_excl_vat' => 10,
                'purchase_price_excl_vat'     => 5,
            ]);
        }
    }

    private function calculation(): HvacCalculation
    {
        $request = CustomerRequest::create([
            'locale' => 'nl', 'service_slug' => 'airco', 'request_type' => 'installation',
            'service_category' => 'airco_offerte',
            'customer_name' => 'Test', 'customer_email' => 't@e.com',
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

        return app(HvacCalculationService::class)->run($request);
    }

    public function test_full_recommendation_is_built_with_priced_items_and_totals(): void
    {
        $set = $this->product();
        $this->seedAccessories();

        $result = app(HvacRecommendationBuilder::class)->buildForCalculation($this->calculation());

        $this->assertCount(1, $result['recommendations']);
        $rec = $result['recommendations'][0];

        $this->assertSame('recommended', $rec->option_type);
        $this->assertSame('draft', $rec->status);

        $items = $rec->items;
        $this->assertSame(1, $items->where('item_type', 'equipment')->count());
        $this->assertGreaterThanOrEqual(6, $items->where('item_type', 'material')->count());
        $this->assertSame(1, $items->where('item_type', 'labor')->count());
        $this->assertSame(1, $items->where('item_type', 'travel')->count());

        // Equipment 1500 + materials 240 (priced) + labor 9.0h × 65 = 585
        // (base 6 + drilling 0.5 + pump 1 + electrical 1.5) + travel 35.
        // The optional refrigerant flag line carries no price.
        $this->assertEquals(1500.0, $rec->equipment_total_excl_vat);
        $this->assertEquals(240.0, $rec->materials_total_excl_vat);
        $this->assertEquals(585.0, $rec->labor_total_excl_vat);
        $this->assertEquals(35.0, $rec->travel_total_excl_vat);
        $this->assertEquals(2360.0, $rec->subtotal_excl_vat);
        $this->assertEquals(21.0, $rec->vat_rate);
        $this->assertEquals(495.6, $rec->vat_amount);
        $this->assertEquals(2855.6, $rec->total_incl_vat);

        // Margin: sale (1500 + 240) − purchase (1000 + 120) = 620
        $this->assertEquals(620.0, $rec->margin_amount);

        // Every material line has a reason.
        foreach ($items->where('item_type', 'material') as $item) {
            $this->assertNotEmpty($item->metadata['reason']);
        }

        $this->assertSame($set->id, $items->firstWhere('item_type', 'equipment')->hvac_product_id);
    }

    public function test_missing_accessory_catalog_products_force_manual_review(): void
    {
        $this->product(); // equipment set only, no accessories in catalog

        $result = app(HvacRecommendationBuilder::class)->buildForCalculation($this->calculation());

        $rec = $result['recommendations'][0];
        $this->assertSame('manual_review', $rec->status);
        $warningCodes = array_column($rec->metadata['warnings'], 'code');
        $this->assertContains('accessory_missing_in_catalog', $warningCodes);
        $this->assertContains('material_price_missing', $warningCodes);
    }

    public function test_no_valid_candidates_produces_no_approvable_recommendation(): void
    {
        // Indoor unit without outdoor compatibility → invalid candidate only.
        $this->product(['product_type' => 'indoor_unit']);

        $result = app(HvacRecommendationBuilder::class)->buildForCalculation($this->calculation());

        $this->assertSame([], $result['recommendations']);
        $this->assertNotEmpty($result['invalid_candidates']);
        $this->assertDatabaseCount('hvac_recommendations', 0);
    }

    public function test_multiple_distinct_systems_become_budget_and_premium_options(): void
    {
        $this->seedAccessories();
        $this->product(['sku' => 'TB-CHEAP', 'default_sale_price_excl_vat' => 1200, 'stock_quantity' => 0]);
        $this->product(['sku' => 'TB-STOCK', 'default_sale_price_excl_vat' => 1500, 'stock_quantity' => 5]);
        $this->product(['sku' => 'TB-PREMIUM', 'default_sale_price_excl_vat' => 2100, 'stock_quantity' => 2, 'sound_level_db' => 18, 'wifi_included' => true]);

        $result = app(HvacRecommendationBuilder::class)->buildForCalculation($this->calculation());

        $byType = collect($result['recommendations'])->keyBy('option_type');
        $this->assertTrue($byType->has('recommended'));
        $this->assertTrue($byType->has('budget'));
        $this->assertTrue($byType->has('premium'));

        // Recommended = in-stock ranked first; budget = cheapest; premium = priciest.
        $this->assertSame('TB-STOCK', $byType['recommended']->items->firstWhere('item_type', 'equipment')->sku);
        $this->assertSame('TB-CHEAP', $byType['budget']->items->firstWhere('item_type', 'equipment')->sku);
        $this->assertSame('TB-PREMIUM', $byType['premium']->items->firstWhere('item_type', 'equipment')->sku);
    }

    public function test_rebuild_supersedes_previous_recommendations(): void
    {
        $this->product();
        $this->seedAccessories();
        $calculation = $this->calculation();
        $builder = app(HvacRecommendationBuilder::class);

        $first = $builder->buildForCalculation($calculation)['recommendations'][0];
        $second = $builder->buildForCalculation($calculation)['recommendations'][0];

        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertNotSame('superseded', $second->fresh()->status);
    }

    // ── Overrides ─────────────────────────────────────────────────────────────

    public function test_item_override_requires_reason_and_preserves_original(): void
    {
        $this->product();
        $this->seedAccessories();
        $rec = app(HvacRecommendationBuilder::class)->buildForCalculation($this->calculation())['recommendations'][0];
        $item = $rec->items->firstWhere('item_type', 'equipment');
        $service = app(HvacManualOverrideService::class);

        // Empty reason rejected.
        try {
            $service->overrideItem($item, null, 1400.0, '   ', 'admin@test.com');
            $this->fail('Expected exception for empty reason');
        } catch (\InvalidArgumentException) {
            // expected
        }

        $service->overrideItem($item, null, 1400.0, 'Prijsafspraak met klant', 'admin@test.com');

        $this->assertEquals(1400.0, $item->fresh()->sale_unit_price);

        $override = $rec->calculation->overrides()->first();
        $this->assertSame('1500', $override->original_value);
        $this->assertSame('1400', $override->overridden_value);
        $this->assertSame('Prijsafspraak met klant', $override->reason);
        $this->assertSame('admin@test.com', $override->overridden_by);

        // Totals recomputed: subtotal drops by 100.
        $this->assertEquals(2260.0, $rec->fresh()->subtotal_excl_vat);
        $this->assertNotNull($rec->calculation->fresh()->manually_overridden_at);
    }

    public function test_vat_override_recomputes_totals(): void
    {
        $this->product();
        $this->seedAccessories();
        $rec = app(HvacRecommendationBuilder::class)->buildForCalculation($this->calculation())['recommendations'][0];

        app(HvacManualOverrideService::class)->overrideVatRate($rec, 6.0, 'Woning ouder dan 10 jaar, voorwaarden gecontroleerd', 'admin@test.com');

        $fresh = $rec->fresh();
        $this->assertEquals(6.0, $fresh->vat_rate);
        $this->assertEquals(round(2360.0 * 1.06, 2), $fresh->total_incl_vat);
    }

    public function test_change_item_product_uses_catalog_data_only(): void
    {
        $this->product();
        $this->seedAccessories();
        $other = $this->product(['sku' => 'TB-ALT', 'default_sale_price_excl_vat' => 1800]);
        $inactive = $this->product(['sku' => 'TB-OFF', 'is_active' => false]);

        $rec = app(HvacRecommendationBuilder::class)->buildForCalculation($this->calculation())['recommendations'][0];
        $item = $rec->items->firstWhere('item_type', 'equipment');
        $service = app(HvacManualOverrideService::class);

        try {
            $service->changeItemProduct($item, $inactive, 'test', 'admin@test.com');
            $this->fail('Expected exception for inactive product');
        } catch (\InvalidArgumentException) {
            // expected
        }

        $service->changeItemProduct($item, $other, 'Klant verkiest stiller model', 'admin@test.com');

        $fresh = $item->fresh();
        $this->assertSame('TB-ALT', $fresh->sku);
        $this->assertEquals(1800.0, $fresh->sale_unit_price);
        $this->assertTrue($fresh->metadata['manually_selected']);
        $this->assertEquals(1800.0 + 240.0 + 585.0 + 35.0, $rec->fresh()->subtotal_excl_vat);
    }
}
