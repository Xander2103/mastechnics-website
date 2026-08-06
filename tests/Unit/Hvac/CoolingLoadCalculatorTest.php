<?php

namespace Tests\Unit\Hvac;

use App\Services\Hvac\CapacityClassSelector;
use App\Services\Hvac\CoolingLoadCalculator;
use App\Services\Hvac\ElectricalEstimator;
use App\Services\Hvac\Input\HvacRoomInput;
use App\Services\Hvac\PipeEstimator;
use Tests\TestCase;

class CoolingLoadCalculatorTest extends TestCase
{
    private function rules(): array
    {
        return config('hvac.default_rule_set.configuration');
    }

    private function room(array $overrides = []): HvacRoomInput
    {
        return HvacRoomInput::fromArray(array_merge([
            'index'             => 0,
            'name'              => 'Slaapkamer 1',
            'type'              => 'slaapkamer',
            'width_m'           => 4.0,
            'length_m'          => 5.0,
            'height_m'          => 2.5,
            'area_m2'           => 20.0,
            'insulation'        => 'good',
            'insulation_other'  => null,
            'orientation'       => 'south',
            'orientation_other' => null,
            'roof_type'         => 'none',
            'roof_type_other'   => null,
            'window_type'       => 'large',
            'window_type_other' => null,
            'window_area_m2'    => null,
            'occupants'         => 2,
            'occupants_assumed' => true,
            'equipment'         => ['tv'],
            'equipment_assumed' => true,
            'pipe_length_m'     => 5.0,
            'pipe_bends'        => 4,
            'pipe_rise_m'       => 2.5,
            'pipe_assumed'      => true,
            'drainage'          => 'unknown',
        ], $overrides));
    }

    public function test_reference_room_calculates_expected_load_with_all_intermediates(): void
    {
        $result = (new CoolingLoadCalculator())->calculateRoom($this->room(), $this->rules());

        // 20 m² × 90 W/m² × 1.0 × 1.12 (south) × 1.18 (large windows) × 1.0 = 2378.88 W
        $this->assertSame(90.0, $result['insulation_w_per_m2']);
        $this->assertSame(1.0, $result['ceiling_factor']);
        $this->assertSame(1.12, $result['orientation_factor']);
        $this->assertSame(1.18, $result['window_factor']);
        $this->assertSame(1.0, $result['roof_factor']);
        $this->assertSame(2378.9, $result['base_watts']);
        $this->assertSame(120.0, $result['occupancy_heat_w']);   // 2 persons, 1 included
        $this->assertSame(100.0, $result['equipment_heat_w']);   // tv
        $this->assertSame(2599, $result['final_watts']);
        $this->assertSame(2.6, $result['final_kw']);
    }

    public function test_insulation_levels_map_to_configured_base_loads(): void
    {
        $calculator = new CoolingLoadCalculator();

        foreach (['excellent' => 70.0, 'good' => 90.0, 'average' => 110.0, 'poor' => 140.0, 'unknown' => 110.0] as $level => $expected) {
            $result = $calculator->calculateRoom($this->room(['insulation' => $level]), $this->rules());
            $this->assertSame($expected, $result['insulation_w_per_m2'], "insulation {$level}");
        }
    }

    public function test_ceiling_factor_scales_with_height(): void
    {
        $result = (new CoolingLoadCalculator())->calculateRoom(
            $this->room(['height_m' => 3.0]),
            $this->rules()
        );

        $this->assertSame(1.2, $result['ceiling_factor']);
    }

    public function test_orientation_factors(): void
    {
        $calculator = new CoolingLoadCalculator();

        foreach (['north' => 0.95, 'east' => 1.0, 'west' => 1.08, 'south' => 1.12, 'unknown' => 1.0] as $orientation => $expected) {
            $result = $calculator->calculateRoom($this->room(['orientation' => $orientation]), $this->rules());
            $this->assertSame($expected, $result['orientation_factor'], "orientation {$orientation}");
        }
    }

    public function test_window_ratio_table_takes_precedence_when_window_area_known(): void
    {
        $calculator = new CoolingLoadCalculator();

        foreach ([
            [1.9, 1.00],   // 1.9 / 20 = 9.5% → 1.00
            [3.0, 1.05],   // 15% → 1.05
            [5.0, 1.10],   // 25% → 1.10
            [8.0, 1.18],   // 40% → 1.18
        ] as [$windowArea, $expected]) {
            $result = $calculator->calculateRoom(
                $this->room(['window_area_m2' => $windowArea]),
                $this->rules()
            );
            $this->assertSame($expected, $result['window_factor'], "window area {$windowArea}");
        }
    }

    public function test_occupancy_first_person_included(): void
    {
        $result = (new CoolingLoadCalculator())->calculateRoom(
            $this->room(['occupants' => 1]),
            $this->rules()
        );

        $this->assertSame(0.0, $result['occupancy_heat_w']);
    }

    public function test_equipment_heat_sums_configured_values(): void
    {
        $result = (new CoolingLoadCalculator())->calculateRoom(
            $this->room(['equipment' => ['tv', 'pc', 'open_kitchen']]),
            $this->rules()
        );

        $this->assertSame(550.0, $result['equipment_heat_w']);
    }

    public function test_capacity_class_boundaries(): void
    {
        $selector = new CapacityClassSelector();
        $rules = $this->rules();

        $this->assertSame(2.5, $selector->select(2.2, $rules)['class_kw']);
        $this->assertSame(3.5, $selector->select(2.21, $rules)['class_kw']);
        $this->assertSame(3.5, $selector->select(3.2, $rules)['class_kw']);
        $this->assertSame(5.0, $selector->select(4.6, $rules)['class_kw']);
        $this->assertSame(6.0, $selector->select(6.3, $rules)['class_kw']);

        $aboveRange = $selector->select(8.0, $rules);
        $this->assertNull($aboveRange['class_kw']);
        $this->assertTrue($aboveRange['manual_review']);
    }

    public function test_pipe_equivalent_length_formula(): void
    {
        $result = (new PipeEstimator())->estimate($this->room(), $this->rules());

        // 5 + 4 × 1.0 + 2.5 × 0.5 = 10.25 → 10.3
        $this->assertSame(10.3, $result['equivalent_length_m']);
        $this->assertFalse($result['exceeds_generic_threshold']);
        $this->assertTrue($result['assumed']);
    }

    public function test_pipe_threshold_warning_flag(): void
    {
        $result = (new PipeEstimator())->estimate(
            $this->room(['pipe_length_m' => 14.0]),
            $this->rules()
        );

        // 14 + 4 + 1.25 = 19.25 > 15
        $this->assertTrue($result['exceeds_generic_threshold']);
    }

    public function test_electrical_defaults_per_class(): void
    {
        $estimator = new ElectricalEstimator();
        $rules = $this->rules();

        $small = $estimator->forClass(2.5, $rules);
        $this->assertSame(16, $small['breaker_a']);
        $this->assertSame('3G2.5', $small['cable']);

        $large = $estimator->forClass(7.1, $rules);
        $this->assertSame(25, $large['breaker_a']);
        $this->assertSame('3G4', $large['cable']);

        $unknown = $estimator->forClass(null, $rules);
        $this->assertNull($unknown['breaker_a']);
        $this->assertNotSame('', $unknown['warning']);
    }
}
