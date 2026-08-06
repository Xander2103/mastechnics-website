<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WaterSoftenerFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'service_category'         => 'waterverzachter',
            'customer_type'            => 'residential',
            'installation_timeframe'   => 'within_1_month',
            'water_usage_m3'           => '120',
            'bathrooms_count'          => '2',
            'household_size'           => '4',
            'softener_type_preference' => 'salt',
            'drain_distance'           => 'within_1m',
            'power_socket_available'   => 'yes',
            'free_space_available'     => 'unknown',
            'street'                   => 'Voorbeeldstraat 12',
            'postal_code'              => '1000',
            'city'                     => 'Brussel',
            'customer_name'            => 'Jan Janssens',
            'customer_email'           => 'jan@example.com',
            'privacy_consent'          => '1',
        ], $overrides);
    }

    // ── Rendering ─────────────────────────────────────────────────────────────

    public function test_dutch_wizard_shows_new_water_softener_flow(): void
    {
        $this->seed(PageSeeder::class);

        $response = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'aanvraag']));

        $response->assertOk();
        $response->assertSee('Offerte voor een waterverzachter');
        $response->assertDontSee('Ik zoek een waterverzachter');
        $response->assertSee('Gewenste termijn voor plaatsing');
        $response->assertSee('Binnen 1 maand');
        $response->assertSee('Binnen 3 maanden');
        $response->assertSee('Nog geen beslissing');
        $response->assertSee('Geschat jaarlijks waterverbruik');
        $response->assertSee('U vindt dit meestal terug op uw waterfactuur.');
        $response->assertSee('Ik weet het jaarlijkse verbruik niet');
        $response->assertSee('Aantal badkamers of douchekamers');
        $response->assertSee('Aantal personen in het huishouden');
        $response->assertSee('Voorkeur voor type waterverzachter');
        $response->assertSee('Met zout');
        $response->assertSee('Geen voorkeur, ik ontvang graag advies');
        $response->assertSee('Is er een afvoer aanwezig?');
        $response->assertSee('Binnen 1 meter');
        $response->assertSee('Geen afvoer aanwezig');
        $response->assertSee("Foto's van de plaats van installatie");
        $response->assertSee('waterteller');
    }

    public function test_french_wizard_shows_new_water_softener_flow(): void
    {
        $this->seed(PageSeeder::class);

        $response = $this->get(route('pages.show', ['locale' => 'fr', 'slug' => 'demande']));

        $response->assertOk();
        $response->assertSee("Demande de devis pour un adoucisseur d'eau");
        $response->assertSee("Délai souhaité pour l'installation");
        $response->assertSee('Dans le mois');
        $response->assertSee('Avec sel');
        $response->assertSee('Pas de préférence, je souhaite être conseillé');
    }

    public function test_english_wizard_shows_new_water_softener_flow(): void
    {
        $this->seed(PageSeeder::class);

        $response = $this->get(route('pages.show', ['locale' => 'en', 'slug' => 'request']));

        $response->assertOk();
        $response->assertSee('Request a quote for a water softener');
        $response->assertSee('Preferred installation timeframe');
        $response->assertSee('Within 1 month');
        $response->assertSee('Salt-based');
        $response->assertSee('No preference, I would like advice');
    }

    // ── Submission ────────────────────────────────────────────────────────────

    public function test_valid_water_softener_request_is_stored_with_normalized_values(): void
    {
        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload());

        $response->assertSessionHasNoErrors();

        $request = CustomerRequest::first();
        $this->assertNotNull($request);

        $answers = $request->metadata['answers'];
        $this->assertSame('within_1_month', $answers['installation_timeframe']);
        $this->assertSame('120', $answers['water_usage_m3']);
        $this->assertSame('2', $answers['bathrooms_count']);
        $this->assertSame('4', $answers['household_size']);
        $this->assertSame('salt', $answers['softener_type_preference']);
        $this->assertSame('within_1m', $answers['drain_distance']);
        $this->assertSame('yes', $answers['power_socket_available']);
        $this->assertSame('unknown', $answers['free_space_available']);

        $this->assertSame('within_1_month', $request->preferred_time);
    }

    public function test_urgency_is_not_required_for_water_softener_quote(): void
    {
        $payload = $this->validPayload();
        unset($payload['urgency']);

        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $payload);

        $response->assertSessionDoesntHaveErrors('urgency');
        $response->assertSessionHasNoErrors();
    }

    public function test_other_timeframe_stores_custom_text(): void
    {
        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'installation_timeframe'       => 'other',
            'installation_timeframe_other' => 'Na de verbouwing in november',
        ]));

        $response->assertSessionHasNoErrors();

        $answers = CustomerRequest::first()->metadata['answers'];
        $this->assertSame('other', $answers['installation_timeframe']);
        $this->assertSame('Na de verbouwing in november', $answers['installation_timeframe_other']);
    }

    public function test_unknown_yearly_usage_is_allowed(): void
    {
        $payload = $this->validPayload([
            'water_usage_unknown' => '1',
        ]);
        unset($payload['water_usage_m3']);

        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $payload);

        $response->assertSessionHasNoErrors();

        $answers = CustomerRequest::first()->metadata['answers'];
        $this->assertTrue($answers['water_usage_unknown']);
        $this->assertNull($answers['water_usage_m3']);
    }

    public function test_missing_timeframe_is_rejected(): void
    {
        $payload = $this->validPayload();
        unset($payload['installation_timeframe']);

        $this->post(route('customer-requests.store', ['locale' => 'nl']), $payload)
            ->assertSessionHasErrors('installation_timeframe');
    }

    public function test_zero_bathrooms_or_household_is_rejected(): void
    {
        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'bathrooms_count' => '0',
        ]))->assertSessionHasErrors('bathrooms_count');

        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'household_size' => '0',
        ]))->assertSessionHasErrors('household_size');
    }

    public function test_non_numeric_water_usage_is_rejected(): void
    {
        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'water_usage_m3' => 'veel',
        ]))->assertSessionHasErrors('water_usage_m3');
    }

    public function test_other_softener_type_stores_custom_text(): void
    {
        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'softener_type_preference' => 'other',
            'softener_type_other'      => 'CO2-systeem',
        ]));

        $response->assertSessionHasNoErrors();

        $answers = CustomerRequest::first()->metadata['answers'];
        $this->assertSame('other', $answers['softener_type_preference']);
        $this->assertSame('CO2-systeem', $answers['softener_type_other']);
    }

    public function test_french_submission_works(): void
    {
        $response = $this->post(route('customer-requests.store', ['locale' => 'fr']), $this->validPayload([
            'customer_email' => 'marie@example.com',
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertSame('fr', CustomerRequest::first()->locale);
    }
}
