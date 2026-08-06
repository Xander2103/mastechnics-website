<?php

namespace Tests\Feature\Hvac;

use App\Models\CustomerRequest;
use App\Models\HvacBrand;
use App\Models\HvacCalculation;
use App\Models\HvacProduct;
use App\Models\HvacProductCompatibility;
use App\Models\HvacRecommendation;
use App\Services\Hvac\HvacCalculationService;
use App\Services\Hvac\ProductSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSelectorTest extends TestCase
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
            'sku'                         => "TB-{$i}",
            'model'                       => "TestBrand Model {$i}",
            'name'                        => "TestBrand Product {$i}",
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

    private function singleRoomCalculation(): HvacCalculation
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

    private function threeRoomCalculation(): HvacCalculation
    {
        $room = [
            'type' => 'zolderkamer', 'width' => 4, 'length' => 5, 'height' => 2.5,
            'roof_type' => 'none', 'windows' => 'few_none', 'orientation' => 'north',
        ];
        $request = CustomerRequest::create([
            'locale' => 'nl', 'service_slug' => 'airco', 'request_type' => 'installation',
            'service_category' => 'airco_offerte',
            'customer_name' => 'Test', 'customer_email' => 't@e.com',
            'description' => '', 'status' => 'new',
            'metadata' => ['answers' => [
                'rooms'            => [$room, $room, $room],
                'insulation_level' => 'good',
                'customer_type'    => 'residential',
            ]],
        ]);

        return app(HvacCalculationService::class)->run($request);
    }

    // ── Single split ──────────────────────────────────────────────────────────

    public function test_valid_single_split_set_is_selected(): void
    {
        $set = $this->product();
        $calculation = $this->singleRoomCalculation();

        $result = (new ProductSelector())->selectSystems($calculation);

        $this->assertCount(1, $result['candidates']);
        $candidate = $result['candidates'][0];
        $this->assertTrue($candidate['valid']);
        $this->assertSame('set', $candidate['kind']);
        $this->assertSame($set->sku, $candidate['products'][0]['sku']);
        $this->assertEquals(1500.0, $candidate['total_sale_price']);
    }

    public function test_inactive_product_is_excluded(): void
    {
        $this->product(['is_active' => false]);

        $result = (new ProductSelector())->selectSystems($this->singleRoomCalculation());

        $this->assertCount(0, $result['candidates']);
        $this->assertContains('no_valid_products', array_column($result['warnings'], 'code'));
    }

    public function test_insufficient_capacity_is_excluded(): void
    {
        $this->product(['cooling_capacity_kw' => 2.0]); // below the 2.6 kW load

        $result = (new ProductSelector())->selectSystems($this->singleRoomCalculation());

        $this->assertCount(0, $result['candidates']);
    }

    public function test_absurd_oversizing_is_excluded(): void
    {
        $this->product(['cooling_capacity_kw' => 7.1]); // above 3.5 × 1.30

        $result = (new ProductSelector())->selectSystems($this->singleRoomCalculation());

        $this->assertCount(0, $result['candidates']);
    }

    public function test_indoor_unit_without_compatibility_data_is_never_auto_valid(): void
    {
        $this->product(['product_type' => 'indoor_unit']);

        $result = (new ProductSelector())->selectSystems($this->singleRoomCalculation());

        $this->assertCount(1, $result['candidates']);
        $candidate = $result['candidates'][0];
        $this->assertFalse($candidate['valid']);
        $this->assertSame('missing', $candidate['compatibility']);
        $this->assertContains('no_compatibility_data', array_column($candidate['warnings'], 'code'));
    }

    public function test_compatible_indoor_outdoor_pair_is_selected(): void
    {
        $indoor = $this->product(['product_type' => 'indoor_unit', 'default_sale_price_excl_vat' => 800]);
        $outdoor = $this->product(['product_type' => 'outdoor_unit', 'default_sale_price_excl_vat' => 900]);
        HvacProductCompatibility::create([
            'parent_product_id'     => $outdoor->id,
            'compatible_product_id' => $indoor->id,
            'compatibility_type'    => 'indoor_outdoor',
        ]);

        $result = (new ProductSelector())->selectSystems($this->singleRoomCalculation());

        $this->assertCount(1, $result['candidates']);
        $candidate = $result['candidates'][0];
        $this->assertTrue($candidate['valid']);
        $this->assertSame('pair', $candidate['kind']);
        $this->assertEquals(1700.0, $candidate['total_sale_price']);
    }

    public function test_pipe_limit_violation_invalidates_candidate(): void
    {
        // Estimated equivalent length is 10.3 m; product only allows 8 m.
        $this->product(['maximum_pipe_length_m' => 8]);

        $result = (new ProductSelector())->selectSystems($this->singleRoomCalculation());

        $candidate = $result['candidates'][0];
        $this->assertFalse($candidate['valid']);
        $this->assertContains('pipe_limit_exceeded', array_column($candidate['warnings'], 'code'));
    }

    public function test_height_limit_violation_invalidates_candidate(): void
    {
        // Assumed rise is 2.5 m; product only allows 2 m.
        $this->product(['maximum_height_difference_m' => 2]);

        $result = (new ProductSelector())->selectSystems($this->singleRoomCalculation());

        $candidate = $result['candidates'][0];
        $this->assertFalse($candidate['valid']);
        $this->assertContains('height_limit_exceeded', array_column($candidate['warnings'], 'code'));
    }

    public function test_unknown_limits_require_manual_review(): void
    {
        $this->product(['maximum_pipe_length_m' => null, 'maximum_height_difference_m' => null]);

        $result = (new ProductSelector())->selectSystems($this->singleRoomCalculation());

        $candidate = $result['candidates'][0];
        $this->assertFalse($candidate['valid']);
        $this->assertContains('limits_unknown', array_column($candidate['warnings'], 'code'));
    }

    public function test_in_stock_products_rank_before_cheaper_out_of_stock(): void
    {
        $this->product(['stock_quantity' => 0, 'default_sale_price_excl_vat' => 1000, 'sku' => 'TB-OUT']);
        $this->product(['stock_quantity' => 4, 'default_sale_price_excl_vat' => 1400, 'sku' => 'TB-IN']);

        $result = (new ProductSelector())->selectSystems($this->singleRoomCalculation());

        $this->assertSame('TB-IN', $result['candidates'][0]['products'][0]['sku']);
    }

    public function test_missing_prices_invalidate_candidate(): void
    {
        $this->product(['default_sale_price_excl_vat' => null, 'purchase_price_excl_vat' => null]);

        $result = (new ProductSelector())->selectSystems($this->singleRoomCalculation());

        $candidate = $result['candidates'][0];
        $this->assertFalse($candidate['valid']);
        $this->assertContains('price_missing', array_column($candidate['warnings'], 'code'));
    }

    // ── Multi split ───────────────────────────────────────────────────────────

    public function test_multi_split_selects_outdoor_supporting_all_indoor_models(): void
    {
        $indoor = $this->product([
            'product_type' => 'indoor_unit', 'cooling_capacity_kw' => 2.5,
            'default_sale_price_excl_vat' => 700,
        ]);
        $outdoor = $this->product([
            'product_type' => 'multi_split_outdoor', 'cooling_capacity_kw' => 8.0,
            'minimum_capacity_kw' => 4.0, 'maximum_capacity_kw' => 9.0,
            'maximum_connected_indoor_units' => 4,
            'maximum_pipe_length_m' => 60, 'maximum_pipe_length_per_unit_m' => 25,
            'maximum_height_difference_m' => 10,
            'default_sale_price_excl_vat' => 2500,
        ]);
        HvacProductCompatibility::create([
            'parent_product_id'     => $outdoor->id,
            'compatible_product_id' => $indoor->id,
            'compatibility_type'    => 'multi_split_indoor',
        ]);

        $result = (new ProductSelector())->selectSystems($this->threeRoomCalculation());

        $this->assertCount(1, $result['candidates']);
        $candidate = $result['candidates'][0];
        $this->assertTrue($candidate['valid']);
        $this->assertSame('multi', $candidate['kind']);
        $this->assertCount(4, $candidate['products']); // 3 indoor + 1 outdoor
        $this->assertSame(3, $candidate['diversity']['indoor_count']);
        $this->assertEquals(7.5, $candidate['diversity']['sum_nominal_kw']);
        $this->assertEquals(0.88, $candidate['diversity']['diversity_factor']);
    }

    public function test_multi_split_without_compatibility_rows_yields_no_candidate(): void
    {
        $this->product(['product_type' => 'indoor_unit', 'cooling_capacity_kw' => 2.5]);
        $this->product([
            'product_type' => 'multi_split_outdoor', 'cooling_capacity_kw' => 8.0,
            'minimum_capacity_kw' => 4.0, 'maximum_capacity_kw' => 9.0,
            'maximum_connected_indoor_units' => 4,
        ]);
        // No compatibility rows: capacity alone must never pair units.

        $result = (new ProductSelector())->selectSystems($this->threeRoomCalculation());

        $this->assertCount(0, $result['candidates']);
        $this->assertContains('no_valid_multi_split', array_column($result['warnings'], 'code'));
    }

    public function test_multi_split_connected_capacity_window_is_respected(): void
    {
        $indoor = $this->product(['product_type' => 'indoor_unit', 'cooling_capacity_kw' => 2.5]);
        $outdoor = $this->product([
            'product_type' => 'multi_split_outdoor', 'cooling_capacity_kw' => 8.0,
            'minimum_capacity_kw' => 4.0, 'maximum_capacity_kw' => 6.0, // sum 7.5 > 6.0
            'maximum_connected_indoor_units' => 4,
        ]);
        HvacProductCompatibility::create([
            'parent_product_id'     => $outdoor->id,
            'compatible_product_id' => $indoor->id,
            'compatibility_type'    => 'multi_split_indoor',
        ]);

        $result = (new ProductSelector())->selectSystems($this->threeRoomCalculation());

        $this->assertCount(0, $result['candidates']);
    }

    // ── Historical protection ─────────────────────────────────────────────────

    public function test_referenced_product_cannot_be_hard_deleted(): void
    {
        $set = $this->product();
        $calculation = $this->singleRoomCalculation();
        $recommendation = HvacRecommendation::create([
            'hvac_calculation_id' => $calculation->id,
            'option_type'         => 'recommended',
            'status'              => 'draft',
        ]);
        $recommendation->items()->create([
            'hvac_product_id' => $set->id,
            'item_type'       => 'equipment',
            'sku'             => $set->sku,
            'description'     => $set->name,
            'quantity'        => 1,
            'sale_unit_price' => 1500,
            'line_total'      => 1500,
        ]);

        $this->expectException(\RuntimeException::class);
        $set->delete();
    }
}
