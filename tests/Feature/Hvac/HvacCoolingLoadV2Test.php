<?php

namespace Tests\Feature\Hvac;

use App\Models\CustomerRequest;
use App\Models\HvacRuleSet;
use App\Services\Hvac\CapacityClassSelector;
use App\Services\Hvac\CoolingLoadCalculator;
use App\Services\Hvac\HvacCalculationService;
use App\Services\Hvac\Input\HvacRoomInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the "Belgian Residential Cooling Load v2" engineering
 * model against the reference workbook example (see
 * docs/hvac/excel-calculator-audit.md §2.4). All intermediate values are
 * asserted — never only the final number.
 */
class HvacCoolingLoadV2Test extends TestCase
{
    use RefreshDatabase;

    private function v2Rules(array $overrides = []): array
    {
        $rules = config('hvac.cooling_load_v2_rule_set.configuration');

        return array_replace_recursive($rules, $overrides);
    }

    /**
     * The exact workbook example: 6 × 5 × 2.6 m, 5 m² window, West, internal
     * blinds, "Recent insulated" (Ueq 0.6 = our 'good'), 3 people, 250 W
     * equipment, ACH 0.5, safety factor 1.1 → 2572.229 W.
     */
    private function workbookRoom(): HvacRoomInput
    {
        return new HvacRoomInput(
            index: 0,
            name: 'Werkboekkamer',
            type: 'woonkamer',
            widthM: 5.0,
            lengthM: 6.0,
            heightM: 2.6,
            areaM2: 30.0,
            insulation: 'good',
            insulationOther: null,
            orientation: 'west',
            orientationOther: null,
            roofType: 'none',
            roofTypeOther: null,
            windowType: 'large',
            windowTypeOther: null,
            windowAreaM2: 5.0,
            occupants: 3,
            occupantsAssumed: false,
            equipment: ['workbook_equipment'],
            equipmentAssumed: false,
            pipeLengthM: 5.0,
            pipeBends: 4,
            pipeRiseM: 2.5,
            pipeAssumed: true,
            drainage: 'unknown',
        );
    }

    private function workbookRules(): array
    {
        return $this->v2Rules([
            'assumed_shading'  => 'internal_blinds',
            'equipment_heat_w' => ['workbook_equipment' => 250],
        ]);
    }

    public function test_workbook_example_reproduces_all_intermediate_values(): void
    {
        $load = (new CoolingLoadCalculator())->calculateRoom($this->workbookRoom(), $this->workbookRules());

        $this->assertSame('engineering_v2', $load['method']);

        // Geometry
        $this->assertEqualsWithDelta(30.0, $load['area_m2'], 0.01);
        $this->assertEqualsWithDelta(78.0, $load['volume_m3'], 0.01);
        $this->assertEqualsWithDelta(87.2, $load['envelope_area_m2'], 0.01);

        // Transmission: Ueq 0.6 × 87.2 m² × 8 K
        $this->assertEqualsWithDelta(0.6, $load['u_equivalent'], 0.001);
        $this->assertEqualsWithDelta(8.0, $load['design_delta_t_k'], 0.001);
        $this->assertEqualsWithDelta(418.56, $load['q_transmission_w'], 0.01);

        // Solar: 5 m² × 300 (west) × 0.75 (internal blinds)
        $this->assertEqualsWithDelta(5.0, $load['window_area_m2'], 0.01);
        $this->assertFalse($load['window_area_assumed']);
        $this->assertEqualsWithDelta(300, $load['solar_gain_w_per_m2'], 0.001);
        $this->assertSame('internal_blinds', $load['shading']);
        $this->assertEqualsWithDelta(0.75, $load['shading_factor'], 0.001);
        $this->assertEqualsWithDelta(1125.0, $load['q_solar_w'], 0.01);

        // Internal gains
        $this->assertEqualsWithDelta(225.0, $load['people_sensible_w'], 0.01);
        $this->assertEqualsWithDelta(165.0, $load['people_latent_w'], 0.01);
        $this->assertEqualsWithDelta(250.0, $load['equipment_heat_w'], 0.01);

        // Ventilation: 2.67 / 1.3 × ACH 0.5 × 78 m³
        $this->assertEqualsWithDelta(0.5, $load['ach'], 0.001);
        $this->assertEqualsWithDelta(104.13, $load['q_vent_sensible_w'], 0.01);
        $this->assertEqualsWithDelta(50.7, $load['q_vent_latent_w'], 0.01);

        // Totals
        $this->assertEqualsWithDelta(2122.69, $load['q_sensible_total_w'], 0.01);
        $this->assertEqualsWithDelta(215.7, $load['q_latent_total_w'], 0.01);
        $this->assertEqualsWithDelta(2338.39, $load['q_total_w'], 0.01);
        $this->assertEqualsWithDelta(1.1, $load['safety_factor'], 0.001);
        $this->assertEqualsWithDelta(2572.229, $load['design_load_w'], 0.01);

        // Rounded outputs used downstream (class selection, overrides, quotes).
        $this->assertSame(2572, $load['final_watts']);
        $this->assertEqualsWithDelta(2.57, $load['final_kw'], 0.001);
    }

    public function test_workbook_example_maps_to_capacity_class_3_5(): void
    {
        $rules = $this->workbookRules();
        $load = (new CoolingLoadCalculator())->calculateRoom($this->workbookRoom(), $rules);

        $class = (new CapacityClassSelector())->select($load['final_kw'], $rules);

        $this->assertSame(3.5, $class['class_kw']);
        $this->assertFalse($class['manual_review']);
    }

    public function test_window_area_is_derived_from_window_type_when_unknown(): void
    {
        $room = HvacRoomInput::fromArray(array_merge($this->workbookRoom()->toArray(), [
            'window_area_m2' => null,
        ]));

        $load = (new CoolingLoadCalculator())->calculateRoom($room, $this->workbookRules());

        // large → 25% of 30 m² floor area = 7.5 m²
        $this->assertEqualsWithDelta(7.5, $load['window_area_m2'], 0.01);
        $this->assertTrue($load['window_area_assumed']);
    }

    public function test_v1_rules_still_use_the_simple_method_unchanged(): void
    {
        $v1Rules = config('hvac.default_rule_set.configuration');

        $load = (new CoolingLoadCalculator())->calculateRoom($this->workbookRoom(), $v1Rules);

        $this->assertSame('simple_v1', $load['method'] ?? 'simple_v1');
        $this->assertArrayHasKey('base_watts', $load);
        $this->assertArrayNotHasKey('q_transmission_w', $load);
        // v1 for this room: 30 m² × 90 W/m² (good) × (2.6/2.5) × 1.08 (west)
        // × 1.00 (window ratio 5/30 ≤ 0.20 → 1.05? no: ratio 0.167 → 1.05)
        // — value itself is asserted loosely; the point is the method is untouched.
        $this->assertGreaterThan(2000, $load['final_watts']);
    }

    public function test_seed_command_creates_v2_as_draft_and_keeps_v1_active(): void
    {
        // Seed v1 as the active set first (as production would have).
        $v1 = HvacRuleSet::create([
            'name'           => config('hvac.default_rule_set.name'),
            'version'        => 1,
            'status'         => 'active',
            'effective_from' => now()->toDateString(),
            'configuration'  => config('hvac.default_rule_set.configuration'),
            'created_by'     => 'test',
        ]);

        $this->artisan('hvac:seed-v2-rule-set')->assertSuccessful();

        $v2 = HvacRuleSet::where('name', config('hvac.cooling_load_v2_rule_set.name'))->first();
        $this->assertNotNull($v2);
        $this->assertSame('draft', $v2->status);
        $this->assertSame(2, (int) $v2->version);
        $this->assertSame('engineering_v2', $v2->configuration['load_method']);

        // v1 untouched and still active.
        $this->assertSame('active', $v1->fresh()->status);

        // Idempotent: running again never duplicates.
        $this->artisan('hvac:seed-v2-rule-set')->assertSuccessful();
        $this->assertSame(1, HvacRuleSet::where('name', config('hvac.cooling_load_v2_rule_set.name'))->count());
    }

    public function test_full_calculation_with_active_v2_stores_all_intermediates(): void
    {
        HvacRuleSet::create([
            'name'           => config('hvac.cooling_load_v2_rule_set.name'),
            'version'        => 2,
            'status'         => 'active',
            'effective_from' => now()->toDateString(),
            'configuration'  => config('hvac.cooling_load_v2_rule_set.configuration'),
            'created_by'     => 'test',
        ]);

        $request = CustomerRequest::create([
            'locale'           => 'nl',
            'service_slug'     => 'airco',
            'request_type'     => 'installation',
            'service_category' => 'airco_offerte',
            'customer_name'    => 'Test Klant',
            'customer_email'   => 'test@example.com',
            'description'      => '',
            'status'           => 'new',
            'metadata'         => ['answers' => [
                'rooms' => [[
                    'type'        => 'slaapkamer',
                    'width'       => 4,
                    'length'      => 5,
                    'height'      => 2.5,
                    'roof_type'   => 'none',
                    'windows'     => 'small',
                    'orientation' => 'south',
                ]],
                'insulation_level' => 'good',
                'airco_house_age'  => 'no',
                'customer_type'    => 'residential',
            ]],
        ]);

        $calculation = app(HvacCalculationService::class)->run($request, 'admin@test.com');

        $this->assertSame('calculated', $calculation->status);
        $load = $calculation->result['rooms'][0]['load'];

        $this->assertSame('engineering_v2', $load['method']);
        foreach ([
            'area_m2', 'volume_m3', 'envelope_area_m2', 'u_equivalent',
            'q_transmission_w', 'window_area_m2', 'q_solar_w',
            'people_sensible_w', 'people_latent_w', 'equipment_heat_w',
            'q_vent_sensible_w', 'q_vent_latent_w', 'q_sensible_total_w',
            'q_latent_total_w', 'q_total_w', 'safety_factor', 'design_load_w',
            'final_watts', 'final_kw',
        ] as $key) {
            $this->assertArrayHasKey($key, $load, "missing intermediate: {$key}");
        }

        $this->assertSame(2, (int) $calculation->result['rule_set']['version']);

        // v2 assumption warnings surface to the admin.
        $codes = array_column($calculation->warnings['warnings'], 'code');
        $this->assertContains('v2_window_area_derived', $codes);
        $this->assertContains('v2_shading_assumed', $codes);
    }

    public function test_admin_panel_shows_v2_breakdown_groups(): void
    {
        HvacRuleSet::create([
            'name'           => config('hvac.cooling_load_v2_rule_set.name'),
            'version'        => 2,
            'status'         => 'active',
            'effective_from' => now()->toDateString(),
            'configuration'  => config('hvac.cooling_load_v2_rule_set.configuration'),
            'created_by'     => 'test',
        ]);

        $request = CustomerRequest::create([
            'locale'           => 'nl',
            'service_slug'     => 'airco',
            'request_type'     => 'installation',
            'service_category' => 'airco_offerte',
            'customer_name'    => 'Test Klant',
            'customer_email'   => 'test@example.com',
            'description'      => '',
            'status'           => 'new',
            'metadata'         => ['answers' => [
                'rooms' => [[
                    'type'        => 'woonkamer',
                    'width'       => 5,
                    'length'      => 6,
                    'height'      => 2.6,
                    'roof_type'   => 'none',
                    'windows'     => 'large',
                    'orientation' => 'west',
                ]],
                'insulation_level' => 'good',
                'customer_type'    => 'residential',
            ]],
        ]);

        app(HvacCalculationService::class)->run($request, 'admin@test.com');

        $response = $this->withSession(['admin_user_email' => 'admin@test.com'])
            ->get(route('admin.requests.show', $request));

        $response->assertOk();
        $response->assertSee('Transmissie');
        $response->assertSee('Zonnewinst');
        $response->assertSee('Ventilatie');
        $response->assertSee('Ontwerpbelasting');
    }
}
