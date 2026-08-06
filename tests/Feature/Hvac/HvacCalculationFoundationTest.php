<?php

namespace Tests\Feature\Hvac;

use App\Models\CustomerRequest;
use App\Models\HvacCalculation;
use App\Models\HvacRuleSet;
use App\Services\Hvac\HvacCalculationService;
use App\Services\Hvac\HvacInputNormalizer;
use App\Services\Hvac\HvacRuleSetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HvacCalculationFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAircoRequest(array $answerOverrides = [], array $attrs = []): CustomerRequest
    {
        $answers = array_merge([
            'rooms' => [[
                'type'        => 'slaapkamer',
                'width'       => 4,
                'length'      => 5,
                'height'      => 2.5,
                'surface'     => 20.0,
                'roof_type'   => 'none',
                'windows'     => 'large',
                'orientation' => 'south',
            ]],
            'insulation_level' => 'good',
            'airco_house_age'  => 'yes',
            'customer_type'    => 'residential',
            'airco_installation_timing' => 'within_3_months',
        ], $answerOverrides);

        return CustomerRequest::create(array_merge([
            'locale'           => 'nl',
            'service_slug'     => 'airco',
            'request_type'     => 'installation',
            'service_category' => 'airco_offerte',
            'customer_name'    => 'Test Klant',
            'customer_email'   => 'test@example.com',
            'description'      => '',
            'status'           => 'new',
            'metadata'         => ['answers' => $answers],
        ], $attrs));
    }

    // ── Rule set resolver ─────────────────────────────────────────────────────

    public function test_resolver_seeds_version_1_once_and_reuses_it(): void
    {
        $resolver = new HvacRuleSetResolver();

        $first = $resolver->active();
        $second = $resolver->active();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, (int) $first->version);
        $this->assertSame('active', $first->status);
        $this->assertSame(90, $first->configuration['insulation_w_per_m2']['good']);
        $this->assertDatabaseCount('hvac_rule_sets', 1);
    }

    // ── Normalizer ────────────────────────────────────────────────────────────

    public function test_valid_request_normalizes_with_assumption_warnings(): void
    {
        $request = $this->makeAircoRequest();
        $rules = (new HvacRuleSetResolver())->active()->configuration;

        $result = (new HvacInputNormalizer())->fromCustomerRequest($request, $rules);

        $this->assertTrue($result->isCalculable());
        $this->assertSame('single_split', $result->input->splitType);
        $this->assertSame('nl', $result->input->locale);
        $this->assertCount(1, $result->input->rooms);

        $room = $result->input->rooms[0];
        $this->assertSame(20.0, $room->areaM2);
        $this->assertSame('good', $room->insulation);
        $this->assertTrue($room->occupantsAssumed);
        $this->assertTrue($room->pipeAssumed);

        $codes = array_column($result->warnings, 'code');
        $this->assertContains('occupancy_assumed', $codes);
        $this->assertContains('equipment_assumed', $codes);
        $this->assertContains('pipe_assumed', $codes);
        $this->assertContains('electrical_supply_unknown', $codes);
        $this->assertContains('split_type_derived', $codes);

        // Original source answers preserved untouched.
        $this->assertSame('within_3_months', $result->input->source['airco_installation_timing']);
    }

    public function test_missing_height_blocks_calculation(): void
    {
        $request = $this->makeAircoRequest([
            'rooms' => [[
                'type' => 'woonkamer', 'width' => 5, 'length' => 6,
                'attic_or_flat_roof' => 'yes', 'large_windows' => 'no',
            ]],
        ]);
        $rules = (new HvacRuleSetResolver())->active()->configuration;

        $result = (new HvacInputNormalizer())->fromCustomerRequest($request, $rules);

        $this->assertFalse($result->isCalculable());
        $this->assertContains('missing_height', array_column($result->blockers, 'code'));
    }

    public function test_impossible_dimensions_block_calculation(): void
    {
        $request = $this->makeAircoRequest([
            'rooms' => [[
                'type' => 'slaapkamer', 'width' => 4, 'length' => 5, 'height' => 12,
                'roof_type' => 'none', 'windows' => 'small', 'orientation' => 'north',
            ]],
        ]);
        $rules = (new HvacRuleSetResolver())->active()->configuration;

        $result = (new HvacInputNormalizer())->fromCustomerRequest($request, $rules);

        $this->assertFalse($result->isCalculable());
        $this->assertContains('impossible_dimensions', array_column($result->blockers, 'code'));
    }

    public function test_missing_insulation_blocks_calculation(): void
    {
        $request = $this->makeAircoRequest(['insulation_level' => null]);
        $rules = (new HvacRuleSetResolver())->active()->configuration;

        $result = (new HvacInputNormalizer())->fromCustomerRequest($request, $rules);

        $this->assertFalse($result->isCalculable());
        $this->assertContains('missing_insulation', array_column($result->blockers, 'code'));
    }

    public function test_non_airco_request_is_not_calculable(): void
    {
        $request = $this->makeAircoRequest([], ['service_category' => 'waterverzachter']);
        $rules = (new HvacRuleSetResolver())->active()->configuration;

        $result = (new HvacInputNormalizer())->fromCustomerRequest($request, $rules);

        $this->assertFalse($result->isCalculable());
    }

    // ── Calculation service ───────────────────────────────────────────────────

    public function test_run_stores_full_snapshot_with_rule_version(): void
    {
        $request = $this->makeAircoRequest();

        $calculation = app(HvacCalculationService::class)->run($request, 'admin@test.com');

        $this->assertSame('calculated', $calculation->status);
        $this->assertSame('admin@test.com', $calculation->calculated_by);
        $this->assertSame(1, $calculation->result['rule_set']['version']);
        $this->assertSame(90, $calculation->result['rule_set']['configuration']['insulation_w_per_m2']['good']);

        $room = $calculation->result['rooms'][0];
        $this->assertSame(2599, $room['load']['final_watts']);
        $this->assertEquals(2.6, $room['load']['final_kw']);
        $this->assertEquals(3.5, $room['capacity_class_kw']);
        $this->assertSame(16, $room['electrical']['breaker_a']);

        $this->assertSame('single_split', $calculation->result['system']['split_type']);
        // House >10y + residential → 6% suggested, never auto-applied.
        $this->assertEquals(6.0, $calculation->result['system']['vat']['suggested_rate']);
        $this->assertTrue($calculation->result['system']['vat']['requires_confirmation']);
    }

    public function test_multi_split_uses_diversity_factor(): void
    {
        $roomData = [
            'type' => 'slaapkamer', 'width' => 4, 'length' => 5, 'height' => 2.5,
            'roof_type' => 'none', 'windows' => 'small', 'orientation' => 'north',
        ];
        $request = $this->makeAircoRequest([
            'rooms' => [$roomData, $roomData, $roomData],
        ]);

        $calculation = app(HvacCalculationService::class)->run($request);
        $system = $calculation->result['system'];

        $this->assertSame('multi_split', $system['split_type']);
        $this->assertEquals(0.88, $system['diversity_factor']);
        $this->assertEquals(
            round($system['sum_of_classes_kw'] * 0.88, 2),
            $system['estimated_outdoor_kw']
        );
    }

    public function test_recalculation_supersedes_but_preserves_history(): void
    {
        $request = $this->makeAircoRequest();
        $service = app(HvacCalculationService::class);

        $first = $service->run($request);
        $second = $service->run($request);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertSame('calculated', $second->status);
        // History intact: the first snapshot still holds its full result.
        $this->assertSame(2599, $first->fresh()->result['rooms'][0]['load']['final_watts']);
    }

    public function test_rule_changes_do_not_alter_historical_calculations(): void
    {
        $request = $this->makeAircoRequest();
        $service = app(HvacCalculationService::class);

        $first = $service->run($request);

        // Rules change afterwards (new insulation value).
        $ruleSet = HvacRuleSet::first();
        $config = $ruleSet->configuration;
        $config['insulation_w_per_m2']['good'] = 999;
        $ruleSet->update(['configuration' => $config]);

        $second = $service->run($request);

        $this->assertSame(90, $first->fresh()->result['rule_set']['configuration']['insulation_w_per_m2']['good']);
        $this->assertSame(999, $second->result['rule_set']['configuration']['insulation_w_per_m2']['good']);
        $this->assertNotSame(
            $first->fresh()->result['rooms'][0]['load']['final_watts'],
            $second->result['rooms'][0]['load']['final_watts']
        );
    }

    public function test_blocked_request_stores_blocked_calculation_with_blockers(): void
    {
        $request = $this->makeAircoRequest([
            'rooms' => [[
                'type' => 'woonkamer', 'width' => 5, 'length' => 6,
                'attic_or_flat_roof' => 'yes', 'large_windows' => 'no',
            ]],
        ]);

        $calculation = app(HvacCalculationService::class)->run($request);

        $this->assertSame('blocked', $calculation->status);
        $this->assertNull($calculation->result);
        $this->assertNotEmpty($calculation->warnings['blockers']);
    }
}
