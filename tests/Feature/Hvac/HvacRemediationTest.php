<?php

namespace Tests\Feature\Hvac;

use App\Models\CustomerRequest;
use App\Models\HvacBrand;
use App\Models\HvacProduct;
use App\Models\HvacRecommendation;
use App\Services\Hvac\AccessorySelector;
use App\Services\Hvac\HvacCalculationService;
use App\Services\Hvac\HvacRuleSetResolver;
use App\Services\Hvac\LaborEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HvacRemediationTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com'];
    }

    private function makeAircoRequest(int $roomCount = 1): CustomerRequest
    {
        $room = [
            'type' => 'slaapkamer', 'width' => 4, 'length' => 5, 'height' => 2.5,
            'roof_type' => 'none', 'windows' => 'large', 'orientation' => 'south',
        ];

        return CustomerRequest::create([
            'locale' => 'nl', 'service_slug' => 'airco', 'request_type' => 'installation',
            'service_category' => 'airco_offerte',
            'customer_name' => 'Test', 'customer_email' => 't@e.com',
            'description' => '', 'status' => 'new',
            'metadata' => ['answers' => [
                'rooms'            => array_fill(0, $roomCount, $room),
                'insulation_level' => 'good',
                'customer_type'    => 'residential',
            ]],
        ]);
    }

    private function seedSets(): void
    {
        $brand = HvacBrand::create(['name' => 'TestBrand', 'slug' => 'testbrand']);
        foreach ([['TB-SET-35', 3.5], ['TB-SET-50', 5.0]] as [$sku, $kw]) {
            HvacProduct::create([
                'hvac_brand_id' => $brand->id, 'sku' => $sku, 'model' => "TestBrand {$sku}",
                'name' => "TestBrand set {$kw} kW", 'product_type' => 'single_split_set',
                'cooling_capacity_kw' => $kw, 'maximum_pipe_length_m' => 20,
                'maximum_height_difference_m' => 10,
                'default_sale_price_excl_vat' => 1000 + $kw * 100, 'purchase_price_excl_vat' => 800,
                'stock_quantity' => 5, 'is_active' => true,
            ]);
        }
        foreach (['wall_bracket', 'vibration_damper', 'pipe', 'trunking', 'electrical_accessory', 'drain_hose', 'condensate_pump'] as $i => $type) {
            HvacProduct::create([
                'hvac_brand_id' => $brand->id, 'sku' => "TB-ACC-{$i}", 'model' => "Accessoire {$type}",
                'name' => "Accessoire {$type}", 'product_type' => $type,
                'default_sale_price_excl_vat' => 10, 'purchase_price_excl_vat' => 5, 'is_active' => true,
            ]);
        }
    }

    // ── Room-load overrides (audit item H1) ───────────────────────────────────

    public function test_room_load_override_preserves_original_and_rebuilds_selection(): void
    {
        $this->seedSets();
        $request = $this->makeAircoRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.calculate', $request));

        // Automatic result: 2.6 kW → class 3.5 → TB-SET-35.
        $first = HvacRecommendation::whereNotIn('status', ['superseded'])->firstOrFail();
        $this->assertSame('TB-SET-35', $first->items->firstWhere('item_type', 'equipment')->sku);

        // Override to 4000 W → class 5.0 → options must be rebuilt on TB-SET-50.
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.rooms.override-load', $request), [
                'room_index' => 0,
                'watts'      => 4000,
                'reason'     => 'Grote glaspartij, klant wil reserve',
            ])->assertSessionHas('success', 'hvac_override_applied');

        $calculation = $request->hvacCalculations()->where('status', 'calculated')->first();
        $room = $calculation->result['rooms'][0];

        // Original untouched, override stored alongside it.
        $this->assertSame(2599, $room['load']['final_watts']);
        $this->assertSame(4000, $room['load']['final_watts_override']);
        $this->assertEquals(5.0, $room['capacity_class_kw_override']);
        $this->assertEquals(3.5, $room['capacity_class_kw']);

        // Audit row with original and new value.
        $this->assertDatabaseHas('hvac_manual_overrides', [
            'field'            => 'room:0:final_watts',
            'original_value'   => '2599',
            'overridden_value' => '4000',
        ]);

        // Recommendations rebuilt on the overridden class.
        $rebuilt = HvacRecommendation::whereNotIn('status', ['superseded'])->firstOrFail();
        $this->assertNotSame($first->id, $rebuilt->id);
        $this->assertSame('TB-SET-50', $rebuilt->items->firstWhere('item_type', 'equipment')->sku);
        $this->assertSame('superseded', $first->fresh()->status);
    }

    public function test_room_load_override_validates_input(): void
    {
        $this->seedSets();
        $request = $this->makeAircoRequest();
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.calculate', $request));

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.rooms.override-load', $request), [
                'room_index' => 0, 'watts' => 50, 'reason' => 'geldige reden hier',
            ])->assertSessionHasErrors('watts');

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.rooms.override-load', $request), [
                'room_index' => 0, 'watts' => 3000,
            ])->assertSessionHasErrors('reason');
    }

    // ── Labor completion (audit item H3) ──────────────────────────────────────

    public function test_labor_includes_electrical_work_estimate(): void
    {
        $request = $this->makeAircoRequest();
        $calculation = app(HvacCalculationService::class)->run($request);
        $rules = (new HvacRuleSetResolver())->active()->configuration;

        $labor = (new LaborEstimator())->estimate($calculation->result['rooms'], true, $rules);

        $keys = array_column($labor['lines'], 'key');
        $this->assertContains('electrical', $keys);
        // 6 base + 0.5 drilling + 1 pump + 1.5 electrical = 9.0
        $this->assertEquals(9.0, $labor['total_hours']);
    }

    public function test_second_technician_assumed_from_three_indoor_units(): void
    {
        $request = $this->makeAircoRequest(3);
        $calculation = app(HvacCalculationService::class)->run($request);
        $rules = (new HvacRuleSetResolver())->active()->configuration;

        $labor = (new LaborEstimator())->estimate($calculation->result['rooms'], false, $rules);

        $keys = array_column($labor['lines'], 'key');
        $this->assertContains('second_technician', $keys);
        $this->assertContains('second_technician_assumed', array_column($labor['warnings'], 'code'));

        // Not for a single room.
        $single = app(HvacCalculationService::class)->run($this->makeAircoRequest());
        $singleLabor = (new LaborEstimator())->estimate($single->result['rooms'], false, $rules);
        $this->assertNotContains('second_technician', array_column($singleLabor['lines'], 'key'));
    }

    // ── Accessory completion (audit item H3) ──────────────────────────────────

    public function test_refrigerant_is_flagged_optionally_on_long_equivalent_runs(): void
    {
        $request = $this->makeAircoRequest();
        $calculation = app(HvacCalculationService::class)->run($request);
        $rules = (new HvacRuleSetResolver())->active()->configuration;

        // Default assumptions give 10.3 m equivalent > 10 m threshold.
        $result = (new AccessorySelector())->select(
            ['products' => []],
            $calculation->result['rooms'],
            $rules
        );

        $refrigerant = collect($result['items'])->firstWhere('key', 'refrigerant');
        $this->assertNotNull($refrigerant);
        $this->assertFalse($refrigerant['mandatory']);
        $this->assertContains('refrigerant_possible', array_column($result['warnings'], 'code'));
    }

    // ── Discounts (audit item M2) ─────────────────────────────────────────────

    public function test_discount_lowers_totals_and_is_audited(): void
    {
        $this->seedSets();
        $request = $this->makeAircoRequest();
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.calculate', $request));

        $recommendation = HvacRecommendation::whereNotIn('status', ['superseded'])->firstOrFail();
        $before = (float) $recommendation->subtotal_excl_vat;

        // Reason required.
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.discount', [$request, $recommendation]), [
                'amount' => 100,
            ])->assertSessionHasErrors('reason');

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.discount', [$request, $recommendation]), [
                'amount' => 100,
                'reason' => 'Trouwe klant, afgesproken korting',
            ])->assertSessionHas('success', 'hvac_override_applied');

        $fresh = $recommendation->fresh();
        $this->assertEquals($before - 100.0, $fresh->subtotal_excl_vat);
        $this->assertNotNull($fresh->items()->where('item_type', 'discount')->first());
        $this->assertDatabaseHas('hvac_manual_overrides', ['field' => 'discount']);

        // A discount larger than the subtotal is refused.
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.discount', [$request, $recommendation]), [
                'amount' => 99999,
                'reason' => 'dit mag niet lukken',
            ])->assertSessionHasErrors('amount');
    }

    // ── CSV in-file duplicates (audit item M3) ────────────────────────────────

    public function test_duplicate_supplier_sku_within_one_file_is_a_row_error(): void
    {
        $csv = "supplier;brand;sku;model;name;product_type;cooling_capacity_kw\r\n"
            . "Leverancier;TestBrand;TB-1;Model A;Naam A;single_split_set;2.5\r\n"
            . "Leverancier;TestBrand;TB-1;Model B;Naam B;single_split_set;3.5\r\n";

        $parsed = (new \App\Services\Hvac\HvacCsvImporter())->parse($csv);

        $this->assertSame([], $parsed['rows'][0]['errors']);
        $this->assertNotEmpty($parsed['rows'][1]['errors']);
        $this->assertStringContainsString('Dubbele rij', $parsed['rows'][1]['errors'][0]);
    }

    // ── AI wiring (audit item H2) ─────────────────────────────────────────────

    public function test_identical_ai_input_reuses_valid_output_without_second_provider_call(): void
    {
        $this->seedSets();
        $request = $this->makeAircoRequest();
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.calculate', $request));
        $recommendation = HvacRecommendation::whereNotIn('status', ['superseded'])->firstOrFail();

        $generator = new class implements \App\Services\Hvac\Explanation\HvacExplanationGeneratorInterface
        {
            public int $calls = 0;

            public function generate(array $payload): ?array
            {
                $this->calls++;

                return ['locale' => 'nl', 'explanation' => 'Prima passend systeem voor deze kamer.'];
            }

            public function name(): string
            {
                return 'counting-fake';
            }

            public function model(): ?string
            {
                return null;
            }

            public function promptVersion(): ?string
            {
                return 'v1';
            }
        };

        $service = new \App\Services\Hvac\Explanation\HvacExplanationService(
            $generator,
            new \App\Services\Hvac\Explanation\AiExplanationValidator()
        );

        $first = $service->generateFor($recommendation);
        $second = $service->generateFor($recommendation->fresh());

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame(1, $generator->calls); // duplicate call prevented
        $this->assertDatabaseCount('hvac_ai_logs', 1);
    }

    public function test_stored_explanation_is_shown_in_the_panel(): void
    {
        $this->seedSets();
        $request = $this->makeAircoRequest();
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.calculate', $request));
        $recommendation = HvacRecommendation::whereNotIn('status', ['superseded'])->firstOrFail();
        $recommendation->update(['explanation_nl' => 'Dit toestel past bij de berekende koellast van de slaapkamer.']);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('Gevalideerde AI-uitleg')
            ->assertSee('Dit toestel past bij de berekende koellast van de slaapkamer.');
    }

    public function test_wifi_module_only_added_when_equipment_explicitly_lacks_wifi(): void
    {
        $request = $this->makeAircoRequest();
        $calculation = app(HvacCalculationService::class)->run($request);
        $rules = (new HvacRuleSetResolver())->active()->configuration;
        $selector = new AccessorySelector();

        $withoutWifi = $selector->select(
            ['products' => [['product_type' => 'single_split_set', 'wifi_included' => false]]],
            $calculation->result['rooms'],
            $rules
        );
        $this->assertNotNull(collect($withoutWifi['items'])->firstWhere('key', 'wifi_module'));

        $unknownWifi = $selector->select(
            ['products' => [['product_type' => 'single_split_set', 'wifi_included' => null]]],
            $calculation->result['rooms'],
            $rules
        );
        $this->assertNull(collect($unknownWifi['items'])->firstWhere('key', 'wifi_module'));
    }
}
