<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AircoInstallationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function validRoom(array $overrides = []): array
    {
        return array_merge([
            'type'        => 'slaapkamer',
            'width'       => '4',
            'length'      => '5',
            'height'      => '2.6',
            'roof_type'   => 'none',
            'windows'     => 'large',
            'orientation' => 'south',
        ], $overrides);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'service_category' => 'airco_offerte',
            'customer_type'    => 'residential',
            'airco_house_age'  => 'yes',
            'insulation_level' => 'good',
            'rooms'            => [$this->validRoom()],
            'street'           => 'Voorbeeldstraat 12',
            'postal_code'      => '1000',
            'city'             => 'Brussel',
            'customer_name'    => 'Jan Janssens',
            'customer_email'   => 'jan@example.com',
            'privacy_consent'  => '1',
        ], $overrides);
    }

    // ── Rendering ─────────────────────────────────────────────────────────────

    public function test_dutch_wizard_shows_new_room_fields(): void
    {
        $this->seed(PageSeeder::class);

        $response = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'aanvraag']));

        $response->assertOk();
        $response->assertSee('Breedte (m)');
        $response->assertSee('Lengte (m)');
        $response->assertSee('Hoogte (m)');
        $response->assertSee('Type dak of zolderkamer');
        $response->assertSee('Plat dak');
        $response->assertSee('Zolderkamer zonder dakraam');
        $response->assertSee('Zolderkamer met dakraam');
        $response->assertSee('Geen van deze');
        $response->assertSee('Grote ramen');
        $response->assertSee('Kleine ramen');
        $response->assertSee('Gemengde ramen');
        $response->assertSee('Weinig of geen ramen');
        $response->assertSee('Ligging van de ruimte');
        $response->assertSee('Noord');
        $response->assertSee('Zuid');
        $response->assertSee('Isolatiegraad van de woning');
        $response->assertSee('Uitstekend — nieuwbouw, EPB of passiefwoning');
        $response->assertSee('Goed — woning vanaf ongeveer 2015');
        $response->assertSee('Gemiddeld — woning van ongeveer 2000 tot 2015');
        $response->assertSee('Beperkt — oudere woning');

        // The old yes/no room questions are gone.
        $response->assertDontSee('Zolderkamer of onder plat dak?');
        $response->assertDontSee('Veel grote ramen?');
    }

    public function test_french_wizard_shows_new_room_fields(): void
    {
        $this->seed(PageSeeder::class);

        $response = $this->get(route('pages.show', ['locale' => 'fr', 'slug' => 'demande']));

        $response->assertOk();
        $response->assertSee('Hauteur (m)');
        $response->assertSee('Type de toiture ou de chambre mansardée');
        $response->assertSee('Toit plat');
        $response->assertSee('Chambre mansardée sans fenêtre de toit');
        $response->assertSee('Grandes fenêtres');
        $response->assertSee('Orientation de la pièce');
        $response->assertSee('Excellente — construction neuve, PEB ou maison passive');
    }

    public function test_english_wizard_shows_new_room_fields(): void
    {
        $this->seed(PageSeeder::class);

        $response = $this->get(route('pages.show', ['locale' => 'en', 'slug' => 'request']));

        $response->assertOk();
        $response->assertSee('Height (m)');
        $response->assertSee('Roof or attic room type');
        $response->assertSee('Flat roof');
        $response->assertSee('Attic room without roof window');
        $response->assertSee('Large windows');
        $response->assertSee('Room orientation');
        $response->assertSee('Excellent — new build, energy-efficient or passive house');
    }

    // ── Submission ────────────────────────────────────────────────────────────

    public function test_valid_airco_request_stores_normalized_room_data(): void
    {
        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload());

        $response->assertSessionHasNoErrors();

        $answers = CustomerRequest::first()->metadata['answers'];
        $room = $answers['rooms'][0];

        $this->assertEquals(4.0, $room['width']);
        $this->assertEquals(5.0, $room['length']);
        $this->assertEquals(2.6, $room['height']);
        $this->assertEquals(20.0, $room['surface']);
        $this->assertSame('none', $room['roof_type']);
        $this->assertSame('large', $room['windows']);
        $this->assertSame('south', $room['orientation']);
        $this->assertSame('good', $answers['insulation_level']);

        $this->assertArrayNotHasKey('attic_or_flat_roof', $room);
        $this->assertArrayNotHasKey('large_windows', $room);
    }

    public function test_missing_room_height_is_rejected(): void
    {
        $room = $this->validRoom();
        unset($room['height']);

        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'rooms' => [$room],
        ]))->assertSessionHasErrors('rooms.0.height');
    }

    public function test_zero_or_impossible_dimensions_are_rejected(): void
    {
        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'rooms' => [$this->validRoom(['width' => '0'])],
        ]))->assertSessionHasErrors('rooms.0.width');

        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'rooms' => [$this->validRoom(['height' => '25'])],
        ]))->assertSessionHasErrors('rooms.0.height');
    }

    public function test_other_roof_type_stores_custom_text(): void
    {
        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'rooms' => [$this->validRoom([
                'roof_type'       => 'other',
                'roof_type_other' => 'Lessenaarsdak',
            ])],
        ]));

        $response->assertSessionHasNoErrors();

        $room = CustomerRequest::first()->metadata['answers']['rooms'][0];
        $this->assertSame('other', $room['roof_type']);
        $this->assertSame('Lessenaarsdak', $room['roof_type_other']);
    }

    public function test_custom_text_is_dropped_when_not_other(): void
    {
        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'rooms' => [$this->validRoom([
                'roof_type'       => 'flat_roof',
                'roof_type_other' => 'Sluikwaarde',
            ])],
        ]));

        $response->assertSessionHasNoErrors();

        $room = CustomerRequest::first()->metadata['answers']['rooms'][0];
        $this->assertSame('flat_roof', $room['roof_type']);
        $this->assertNull($room['roof_type_other']);
    }

    public function test_invalid_roof_type_is_rejected(): void
    {
        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'rooms' => [$this->validRoom(['roof_type' => 'yes'])],
        ]))->assertSessionHasErrors('rooms.0.roof_type');
    }

    public function test_other_insulation_stores_custom_text(): void
    {
        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'insulation_level'       => 'other',
            'insulation_level_other' => 'Deels gerenoveerd in 2020',
        ]));

        $response->assertSessionHasNoErrors();

        $answers = CustomerRequest::first()->metadata['answers'];
        $this->assertSame('other', $answers['insulation_level']);
        $this->assertSame('Deels gerenoveerd in 2020', $answers['insulation_level_other']);
    }
}
