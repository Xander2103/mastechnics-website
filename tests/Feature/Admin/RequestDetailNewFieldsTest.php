<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin request detail must render the 2026-08 form-rework values as
 * human labels (never raw JSON) and keep rendering legacy requests that
 * still hold the old attic/large-windows/urgency structure.
 */
class RequestDetailNewFieldsTest extends TestCase
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
            'service_slug'   => 'heating',
            'request_type'   => 'repair',
            'customer_name'  => 'Test Klant',
            'customer_email' => 'test@example.com',
            'description'    => 'Test aanvraag',
            'status'         => 'new',
        ], $attrs));
    }

    public function test_water_softener_request_renders_visual_labels(): void
    {
        $request = $this->makeRequest([
            'service_slug'     => 'water-softeners',
            'request_type'     => 'installation',
            'service_category' => 'waterverzachter',
            'preferred_time'   => 'within_1_month',
            'description'      => '',
            'metadata'         => ['answers' => [
                'installation_timeframe'   => 'within_1_month',
                'water_usage_m3'           => '120',
                'water_usage_unknown'      => false,
                'bathrooms_count'          => '2',
                'household_size'           => '4',
                'softener_type_preference' => 'salt',
                'drain_distance'           => 'within_1m',
                'power_socket_available'   => 'yes',
                'free_space_available'     => 'unknown',
            ]],
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request));

        $response->assertOk();
        $response->assertSee('Binnen 1 maand');
        $response->assertSee('120 m³ per jaar');
        $response->assertSee('Met zout');
        $response->assertSee('Binnen 1 meter');
        $response->assertSee('Aantal badkamers');
        $response->assertSee('Personen in huishouden');
    }

    public function test_water_softener_unknown_usage_and_other_type_render(): void
    {
        $request = $this->makeRequest([
            'service_category' => 'waterverzachter',
            'metadata'         => ['answers' => [
                'installation_timeframe'   => 'other',
                'installation_timeframe_other' => 'Na de verbouwing',
                'water_usage_unknown'      => true,
                'softener_type_preference' => 'other',
                'softener_type_other'      => 'CO2-systeem',
                'drain_distance'           => 'none',
            ]],
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request));

        $response->assertOk();
        $response->assertSee('Na de verbouwing');
        $response->assertSee('CO2-systeem');
        $response->assertSee('Geen afvoer aanwezig');
    }

    public function test_airco_request_renders_new_room_fields_as_labels(): void
    {
        $request = $this->makeRequest([
            'service_slug'     => 'airco',
            'request_type'     => 'installation',
            'service_category' => 'airco_offerte',
            'metadata'         => ['answers' => [
                'rooms' => [[
                    'type'              => 'slaapkamer',
                    'width'             => 4,
                    'length'            => 5,
                    'height'            => 2.6,
                    'surface'           => 20.0,
                    'roof_type'         => 'flat_roof',
                    'roof_type_other'   => null,
                    'windows'           => 'large',
                    'windows_other'     => null,
                    'orientation'       => 'south',
                    'orientation_other' => null,
                ]],
                'insulation_level' => 'good',
                'airco_house_age'  => 'yes',
            ]],
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request));

        $response->assertOk();
        $response->assertSee('Plat dak');
        $response->assertSee('Grote ramen');
        $response->assertSee('Zuid');
        $response->assertSee('Hoogte');
        $response->assertSee('20 m²');
        $response->assertSee('Isolatiegraad');
        $response->assertSee('Goed');

        // No raw JSON keys anywhere on the page.
        $response->assertDontSee('"roof_type"');
        $response->assertDontSee('"attic_or_flat_roof"');
    }

    public function test_legacy_airco_request_still_renders_correctly(): void
    {
        $request = $this->makeRequest([
            'service_slug'     => 'airco',
            'request_type'     => 'installation',
            'service_category' => 'airco_offerte',
            'metadata'         => ['answers' => [
                'rooms' => [[
                    'type'               => 'woonkamer',
                    'width'              => 5,
                    'length'             => 6,
                    'surface'            => 30.0,
                    'attic_or_flat_roof' => 'yes',
                    'large_windows'      => 'no',
                ]],
                'airco_house_age' => 'no',
            ]],
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request));

        $response->assertOk();
        $response->assertSee('Zolderkamer / plat dak');
        // Legacy string values must map correctly: 'no' renders Nee, not Ja.
        $response->assertSee('Nee');
        $response->assertSee('Grote ramen');
        $response->assertDontSee('"large_windows"');
    }

    public function test_legacy_urgency_value_still_renders(): void
    {
        $request = $this->makeRequest([
            'service_category' => 'waterverzachter',
            'urgency_level'    => 'not_urgent',
            'metadata'         => ['answers' => [
                'urgency' => 'not_urgent',
            ]],
        ]);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('Niet dringend');
    }
}
