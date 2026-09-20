<?php

namespace Tests\Unit\Hvac;

use App\Services\Hvac\CapacityClassSelector;
use App\Services\Hvac\QuickCoolingEstimator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuickCoolingEstimatorTest extends TestCase
{
    private function rules(): array
    {
        return config('hvac.default_rule_set.configuration');
    }

    private function estimator(): QuickCoolingEstimator
    {
        return new QuickCoolingEstimator(new CapacityClassSelector());
    }

    private function input(array $overrides = []): array
    {
        return array_merge([
            'length_m'  => '5',
            'width_m'   => '4',
            'height_m'  => '2.5',
            'situation' => 'well_insulated',
        ], $overrides);
    }

    public function test_reference_room_gives_1500_watts(): void
    {
        $result = $this->estimator()->estimate($this->input(), $this->rules());

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['errors']);
        $this->assertSame(50.0, $result['volume_m3']);
        $this->assertSame(30.0, $result['w_per_m3']);
        $this->assertSame(1500, $result['watts']);
        $this->assertSame(1.5, $result['kw']);
    }

    #[DataProvider('situationProvider')]
    public function test_every_situation_uses_its_own_value(string $situation, float $wPerM3, int $watts): void
    {
        $result = $this->estimator()->estimate($this->input(['situation' => $situation]), $this->rules());

        $this->assertTrue($result['ok']);
        $this->assertSame($wPerM3, $result['w_per_m3']);
        $this->assertSame($watts, $result['watts']);
        $this->assertSame(round($watts / 1000, 2), $result['kw']);
    }

    public static function situationProvider(): array
    {
        return [
            'goed geïsoleerd'                    => ['well_insulated', 30.0, 1500],
            'zuidgericht'                        => ['south_facing', 35.0, 1750],
            'veel zon + slecht geïsoleerd'       => ['sunny_poor_insulation', 40.0, 2000],
            'veel zon + slecht + onder dak'      => ['sunny_poor_insulation_under_roof', 45.0, 2250],
        ];
    }

    public function test_situations_are_explicit_choices_never_combined(): void
    {
        // The under-roof situation is its own value (45), not 30 × factors or
        // a sum of the other situations.
        $result = $this->estimator()->estimate(
            $this->input(['situation' => 'sunny_poor_insulation_under_roof']),
            $this->rules()
        );

        $this->assertSame(50.0 * 45.0, (float) $result['watts']);
    }

    public function test_decimal_comma_is_accepted(): void
    {
        $result = $this->estimator()->estimate($this->input(['height_m' => '2,5']), $this->rules());

        $this->assertTrue($result['ok']);
        $this->assertSame(2.5, $result['height_m']);
    }

    public function test_values_come_from_the_rule_set_not_from_code(): void
    {
        $rules = $this->rules();
        $rules['quick_estimate']['w_per_m3']['well_insulated'] = 32;

        $result = $this->estimator()->estimate($this->input(), $rules);

        $this->assertSame(32.0, $result['w_per_m3']);
        $this->assertSame(1600, $result['watts']);
    }

    #[DataProvider('invalidDimensionProvider')]
    public function test_invalid_dimensions_are_rejected(string $field, mixed $value): void
    {
        $result = $this->estimator()->estimate($this->input([$field => $value]), $this->rules());

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey($field, $result['errors']);
        $this->assertNull($result['watts']);
        $this->assertNull($result['class_indication']);
    }

    public static function invalidDimensionProvider(): array
    {
        return [
            'nul'            => ['length_m', '0'],
            'negatief'       => ['width_m', '-4'],
            'tekst'          => ['height_m', 'hoog'],
            'te groot'       => ['length_m', '500'],
            'te hoog'        => ['height_m', '40'],
            'oneindig'       => ['width_m', 'INF'],
            'array'          => ['length_m', ['5']],
        ];
    }

    public function test_missing_input_is_reported_per_field(): void
    {
        $result = $this->estimator()->estimate([], $this->rules());

        $this->assertFalse($result['ok']);
        $this->assertSame(
            ['length_m', 'width_m', 'height_m', 'situation'],
            array_keys($result['errors'])
        );
    }

    public function test_unknown_situation_is_rejected(): void
    {
        $result = $this->estimator()->estimate($this->input(['situation' => 'iglo']), $this->rules());

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('situation', $result['errors']);
    }

    public function test_rule_set_without_quick_values_is_unavailable(): void
    {
        $rules = $this->rules();
        unset($rules['quick_estimate']);

        $estimator = $this->estimator();

        $this->assertFalse($estimator->available($rules));
        $result = $estimator->estimate($this->input(), $rules);
        $this->assertFalse($result['ok']);
        $this->assertFalse($result['available']);
    }

    public function test_result_is_always_indicative_with_a_non_binding_class(): void
    {
        $result = $this->estimator()->estimate($this->input(), $this->rules());

        $this->assertTrue($result['indicative']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertFalse($result['class_indication']['binding']);
        // 1.5 kW falls in the first class band of the rule set (≤ 2.2 → 2.5).
        $this->assertSame(2.5, $result['class_indication']['class_kw']);
        $this->assertArrayNotHasKey('product', $result);
        $this->assertArrayNotHasKey('products', $result);
    }

    public function test_load_above_class_range_asks_for_manual_review(): void
    {
        $result = $this->estimator()->estimate(
            $this->input(['length_m' => '12', 'width_m' => '8', 'height_m' => '3']),
            $this->rules()
        );

        $this->assertTrue($result['ok']);
        $this->assertNull($result['class_indication']['class_kw']);
        $this->assertTrue($result['class_indication']['manual_review']);
    }

    public function test_calculation_steps_are_spelled_out(): void
    {
        $result = $this->estimator()->estimate($this->input(), $this->rules());

        $steps = implode(' | ', $result['steps']);
        $this->assertStringContainsString('5 m × 4 m × 2,5 m = 50 m³', $steps);
        $this->assertStringContainsString('50 m³ × 30 W/m³ = 1.500 W', $steps);
        $this->assertStringContainsString('1,5 kW', $steps);
    }

    public function test_v2_rule_set_carries_the_same_quick_values(): void
    {
        $v2 = config('hvac.cooling_load_v2_rule_set.configuration');

        $this->assertSame(
            $this->rules()['quick_estimate'],
            $v2['quick_estimate']
        );
    }
}
