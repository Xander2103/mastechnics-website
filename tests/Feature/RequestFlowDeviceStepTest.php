<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The "Technische gegevens" (brand/model/serial) step only makes sense for
 * flows about an EXISTING device. Quote flows for a new installation
 * (airco_offerte, waterverzachter) must not show it or require its fields.
 */
class RequestFlowDeviceStepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'street'          => 'Voorbeeldstraat 12',
            'postal_code'     => '1000',
            'city'            => 'Brussel',
            'customer_name'   => 'Jan Janssens',
            'customer_email'  => 'jan@example.com',
            'privacy_consent' => '1',
        ], $overrides);
    }

    private function aircoOffertePayload(): array
    {
        return $this->basePayload([
            'service_category' => 'airco_offerte',
            'customer_type'    => 'residential',
            'airco_house_age'  => 'yes',
            'insulation_level' => 'good',
            'rooms'            => [
                [
                    'type'        => 'slaapkamer',
                    'width'       => '4',
                    'length'      => '5',
                    'height'      => '2.6',
                    'roof_type'   => 'none',
                    'windows'     => 'large',
                    'orientation' => 'south',
                ],
            ],
        ]);
    }

    private function waterverzachterPayload(): array
    {
        return $this->basePayload([
            'service_category'         => 'waterverzachter',
            'customer_type'            => 'residential',
            // Old generic fields (still required until the dedicated flow lands):
            'urgency'                  => 'not_urgent',
            'description'              => 'Ik wil graag een waterverzachter.',
            // New dedicated flow fields (ignored until Task 2, required after):
            'installation_timeframe'   => 'within_1_month',
            'bathrooms_count'          => '2',
            'household_size'           => '4',
            'softener_type_preference' => 'salt',
            'drain_distance'           => 'within_1m',
        ]);
    }

    public function test_airco_installation_quote_does_not_require_device_details(): void
    {
        $response = $this->post(
            route('customer-requests.store', ['locale' => 'nl']),
            $this->aircoOffertePayload()
        );

        $response->assertSessionDoesntHaveErrors(['brand', 'device_model', 'serial_number']);
        $response->assertSessionHasNoErrors();

        $request = CustomerRequest::first();
        $this->assertNotNull($request);
        $this->assertNull($request->brand);
        $this->assertNull($request->device_model);
    }

    public function test_water_softener_quote_does_not_require_device_details(): void
    {
        $response = $this->post(
            route('customer-requests.store', ['locale' => 'nl']),
            $this->waterverzachterPayload()
        );

        $response->assertSessionDoesntHaveErrors(['brand', 'device_model', 'serial_number']);

        $request = CustomerRequest::first();
        $this->assertNotNull($request);
        $this->assertNull($request->brand);
    }

    public function test_sanitair_still_requires_brand_or_unknown_checkbox(): void
    {
        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->basePayload([
            'service_category' => 'sanitair',
            'customer_type'    => 'residential',
            'urgency'          => 'not_urgent',
            'description'      => 'Lekkende kraan.',
        ]));

        $response->assertSessionHasErrors('brand');
        $this->assertDatabaseCount('customer_requests', 0);
    }

    public function test_airco_maintenance_still_requires_brand_or_unknown_checkbox(): void
    {
        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->basePayload([
            'service_category' => 'airco_onderhoud',
            'customer_type'    => 'residential',
            'description'      => 'Jaarlijks onderhoud graag.',
        ]));

        $response->assertSessionHasErrors('brand');
        $this->assertDatabaseCount('customer_requests', 0);
    }

    public function test_technical_details_step_is_conditional_and_excludes_quote_flows(): void
    {
        $this->seed(PageSeeder::class);

        $response = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'aanvraag']));

        $response->assertOk();
        $response->assertSee(
            'data-condition-service-categories="airco_onderhoud,onderhoud_cv,herstelling_cv,dringend_lek,sanitair,ventilatie,koeling,andere"',
            false
        );
    }

    public function test_missing_info_checklist_skips_brand_for_quote_flows(): void
    {
        $airco = CustomerRequest::create([
            'locale'           => 'nl',
            'service_slug'     => 'airco',
            'request_type'     => 'installation',
            'service_category' => 'airco_offerte',
            'customer_name'    => 'Test',
            'customer_email'   => 'test@example.com',
            'description'      => '',
            'status'           => 'new',
        ]);

        $heating = CustomerRequest::create([
            'locale'           => 'nl',
            'service_slug'     => 'heating',
            'request_type'     => 'repair',
            'service_category' => 'herstelling_cv',
            'customer_name'    => 'Test',
            'customer_email'   => 'test@example.com',
            'description'      => 'Ketel valt uit.',
            'status'           => 'new',
        ]);

        $this->assertNotContains('Merk/model ontbreekt.', $airco->getMissingInfoChecklist());
        $this->assertNotContains('Geen duidelijke beschrijving ingevuld.', $airco->getMissingInfoChecklist());
        $this->assertContains('Merk/model ontbreekt.', $heating->getMissingInfoChecklist());
    }
}
