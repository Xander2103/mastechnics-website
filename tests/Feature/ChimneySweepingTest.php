<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithFormProtection;
use Tests\TestCase;

/**
 * Regression coverage for the seventh public service, "Schoorsteenvegen".
 *
 * The service is registered in config/services.php and must show up in every
 * place that renders the full service list, get its own page in nl/fr/en,
 * carry its own wizard flow and render readable in the admin.
 */
class ChimneySweepingTest extends TestCase
{
    use InteractsWithFormProtection;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(PageSeeder::class);
    }

    // ── Service page ──────────────────────────────────────────────────────────

    public function test_dutch_service_page_renders_with_hero_image_badge_and_cta(): void
    {
        $response = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'schoorsteenvegen']));

        $response->assertOk();
        $response->assertSee('<h1>Schoorsteenvegen</h1>', false);
        $response->assertSee('class="service-page service-page--chimney-sweeping"', false);
        $response->assertSee('class="service-hero-bg-img"', false);
        $response->assertSee(asset('assets/images/schoorsteenveger.webp'), false);
        $response->assertSee('class="service-hero-badge-icon"', false);
        $response->assertSee('<span class="eyebrow">Service</span>', false);
        $response->assertSee('Vraag een offerte of interventie aan');
        $response->assertSee(route('pages.show', ['locale' => 'nl', 'slug' => 'aanvraag']), false);

        // Core content: mechanical cleaning, appliance types, clean working, certificate.
        $response->assertSee('mechanisch');
        $response->assertSee('reinigingsattest');
        $response->assertSee('speksteenkachel');
        $response->assertSee('inzethaard');

        // No prices on the public page (the LocalBusiness priceRange "€€" is a
        // site-wide class indicator, not an amount).
        $this->assertNoAmounts($response->getContent());
    }

    public function test_french_and_english_service_pages_render(): void
    {
        $this->get(route('pages.show', ['locale' => 'fr', 'slug' => 'ramonage']))
            ->assertOk()
            ->assertSee('<h1>Ramonage</h1>', false)
            ->assertSee(asset('assets/images/schoorsteenveger.webp'), false)
            ->assertSee('Demander un devis ou une intervention')
            ->assertSee('attestation de ramonage');

        $this->get(route('pages.show', ['locale' => 'en', 'slug' => 'chimney-sweeping']))
            ->assertOk()
            ->assertSee('<h1>Chimney sweeping</h1>', false)
            ->assertSee(asset('assets/images/schoorsteenveger.webp'), false)
            ->assertSee('Request a quote or call-out')
            ->assertSee('cleaning certificate');

        foreach (['fr' => 'ramonage', 'en' => 'chimney-sweeping'] as $locale => $slug) {
            $this->assertNoAmounts($this->get(route('pages.show', ['locale' => $locale, 'slug' => $slug]))->getContent());
        }
    }

    public function test_service_page_has_unique_meta_canonical_hreflang_and_schema(): void
    {
        $response = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'schoorsteenvegen']))->assertOk();
        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, 'rel="canonical"'));
        $response->assertSee('<link rel="canonical" href="' . route('pages.show', ['locale' => 'nl', 'slug' => 'schoorsteenvegen']) . '">', false);
        $response->assertSee('hreflang="fr" href="' . route('pages.show', ['locale' => 'fr', 'slug' => 'ramonage']) . '"', false);
        $response->assertSee('hreflang="en" href="' . route('pages.show', ['locale' => 'en', 'slug' => 'chimney-sweeping']) . '"', false);
        $response->assertSee('<title>Schoorsteenvegen', false);
        $response->assertSee('<link rel="preload" as="image" href="' . asset('assets/images/schoorsteenveger.webp'), false);

        $nodes = $this->schemaNodes($response);
        $service = $this->schemaNode($nodes, 'Service');
        $this->assertNotNull($service);
        $this->assertSame('Schoorsteenvegen', $service['name']);
        $this->assertNotNull($this->schemaNode($nodes, 'FAQPage'));

        $breadcrumb = $this->schemaNode($nodes, 'BreadcrumbList');
        $this->assertSame('Schoorsteenvegen', $breadcrumb['itemListElement'][2]['name']);
    }

    public function test_sitemap_lists_the_three_locales_and_the_hero_image(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('pages.show', ['locale' => 'nl', 'slug' => 'schoorsteenvegen']), false)
            ->assertSee(route('pages.show', ['locale' => 'fr', 'slug' => 'ramonage']), false)
            ->assertSee(route('pages.show', ['locale' => 'en', 'slug' => 'chimney-sweeping']), false)
            ->assertSee(asset('assets/images/schoorsteenveger.webp'), false);
    }

    public function test_service_appears_in_navigation_hub_home_offer_catalog_and_related_links(): void
    {
        $chimneyUrl = route('pages.show', ['locale' => 'nl', 'slug' => 'schoorsteenvegen']);

        // Header dropdown + hub ItemList + service grid
        $hub = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'diensten']))->assertOk();
        $hub->assertSee('href="' . $chimneyUrl . '" role="menuitem"', false);
        $hub->assertSee('Schoorsteenvegen');
        $itemList = $this->schemaNode($this->schemaNodes($hub), 'ItemList');
        $this->assertContains('Schoorsteenvegen', array_column($itemList['itemListElement'], 'name'));

        // Homepage service grid + hex cluster + OfferCatalog
        $home = $this->get(route('pages.home', ['locale' => 'nl']))->assertOk();
        $home->assertSee('href="' . $chimneyUrl . '"', false);
        $home->assertSee('hero-hex--soot', false);
        $this->assertStringContainsString('"name":"Schoorsteenvegen"', $home->getContent());

        // Other service pages still reach it through the header dropdown (the
        // "other services" pills were removed from service pages on 2026-09-12)
        $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'verwarming']))
            ->assertOk()
            ->assertSee('href="' . $chimneyUrl . '" role="menuitem"', false);

        // Local SEO: municipality page lists every service, including this one
        $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'tervuren']))
            ->assertOk()
            ->assertSee('href="' . $chimneyUrl . '"', false);
    }

    private function assertNoAmounts(string $html): void
    {
        $this->assertDoesNotMatchRegularExpression('/(\d+[,.]?\d*\s?(€|euro)|€\s?\d)/iu', $html, 'A concrete price is rendered on the public page.');
    }

    // ── Wizard ────────────────────────────────────────────────────────────────

    public function test_wizard_offers_chimney_sweeping_with_its_own_fields_in_three_locales(): void
    {
        $nl = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'aanvraag']))->assertOk();
        $nl->assertSee('value="schoorsteenvegen"', false);
        $nl->assertSee('Ik wil mijn schoorsteen laten vegen');
        $nl->assertSee('data-step-code="schoorsteen_installatie"', false);
        $nl->assertSee('data-step-code="schoorsteen_aansluiting"', false);
        $nl->assertSee('name="chimney_appliance_type"', false);
        $nl->assertSee('Vrijstaande houtkachel');
        $nl->assertSee('Inzethaard / cassette');
        $nl->assertSee('Speksteenkachel');
        $nl->assertSee('CV-haard');
        $nl->assertSee('Open haard');
        $nl->assertSee('Foto van de haard of installatie');
        $nl->assertSee('Foto van het zijaanzicht of de aansluiting');
        $nl->assertSee('Eventuele opmerkingen');

        $fr = $this->get(route('pages.show', ['locale' => 'fr', 'slug' => 'demande']))->assertOk();
        $fr->assertSee('Je veux faire ramoner ma cheminée');
        $fr->assertSee('Poêle à bois indépendant');
        $fr->assertSee('Photo du foyer ou de l\'installation');

        $en = $this->get(route('pages.show', ['locale' => 'en', 'slug' => 'request']))->assertOk();
        $en->assertSee('I want my chimney swept');
        $en->assertSee('Freestanding wood stove');
        $en->assertSee('Photo of the fireplace or stove');
    }

    public function test_wizard_steps_are_only_shown_for_the_chimney_category(): void
    {
        $response = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'aanvraag']))->assertOk();
        $html = $response->getContent();

        $response->assertSee('data-fields="chimney_appliance_type,chimney_appliance_type_other"', false);
        $response->assertSee('data-fields="description"', false);

        // Both chimney steps are conditional on the chimney category only, and
        // they are hidden until that category is chosen.
        $this->assertSame(2, substr_count($html, 'data-condition-service-categories="schoorsteenvegen"'));
        $this->assertMatchesRegularExpression(
            '/class="form-section is-condition-hidden"\s+data-step="\d+"\s+data-step-code="schoorsteen_installatie"/',
            $html
        );

        // The generic description / device / customer-context steps stay
        // reserved for the flows that had them; chimney is not added there.
        $this->assertStringNotContainsString(
            'schoorsteenvegen,andere',
            $html,
            'Chimney sweeping must not be appended to the shared step conditions.'
        );
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge($this->protectionFields('request'), [
            'service_category'       => 'schoorsteenvegen',
            'chimney_appliance_type' => 'insert_cassette',
            'description'            => 'De kachel trekt slecht sinds vorige winter.',
            'street'                 => 'Voorbeeldstraat 12',
            'postal_code'            => '3080',
            'city'                   => 'Tervuren',
            'customer_name'          => 'Jan Janssens',
            'customer_email'         => 'jan@example.com',
            'customer_phone'         => '0495 12 11 78',
            'privacy_consent'        => '1',
        ], $overrides);
    }

    public function test_valid_chimney_request_is_stored_with_service_and_attachments(): void
    {
        Storage::fake('local');

        $response = $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'attachments' => [
                UploadedFile::fake()->image('haard.jpg', 800, 600),
                UploadedFile::fake()->image('zijaanzicht.png', 800, 600),
            ],
        ]));

        $response->assertSessionHasNoErrors();

        $request = CustomerRequest::with('attachments')->first();
        $this->assertNotNull($request);
        $this->assertSame('schoorsteenvegen', $request->service_category);
        $this->assertSame('schoorsteenvegen', $request->service_slug);
        $this->assertSame('maintenance', $request->request_type);
        $this->assertSame('Schoorsteenvegen', $request->metadata['service']['title']);
        $this->assertSame('Schoorsteen laten vegen', $request->metadata['answers']['service_category_label']);
        $this->assertSame('insert_cassette', $request->metadata['answers']['chimney_appliance_type']);
        $this->assertSame('De kachel trekt slecht sinds vorige winter.', $request->description);

        $this->assertCount(2, $request->attachments);
        foreach ($request->attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->path);
        }
    }

    public function test_other_appliance_type_stores_free_text(): void
    {
        $this->post(route('customer-requests.store', ['locale' => 'fr']), $this->validPayload([
            'chimney_appliance_type'       => 'other',
            'chimney_appliance_type_other' => 'Poêle à pellets',
        ]))->assertSessionHasNoErrors();

        $answers = CustomerRequest::first()->metadata['answers'];
        $this->assertSame('other', $answers['chimney_appliance_type']);
        $this->assertSame('Poêle à pellets', $answers['chimney_appliance_type_other']);
    }

    public function test_remarks_are_optional_for_chimney_sweeping(): void
    {
        $payload = $this->validPayload();
        unset($payload['description']);

        $this->post(route('customer-requests.store', ['locale' => 'nl']), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame('', CustomerRequest::first()->description);
    }

    public function test_appliance_type_is_required_and_must_be_a_known_value(): void
    {
        $payload = $this->validPayload();
        unset($payload['chimney_appliance_type']);

        $this->post(route('customer-requests.store', ['locale' => 'nl']), $payload)
            ->assertSessionHasErrors('chimney_appliance_type');

        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'chimney_appliance_type' => 'barbecue',
        ]))->assertSessionHasErrors('chimney_appliance_type');

        $this->assertSame(0, CustomerRequest::count());
    }

    public function test_chimney_flow_does_not_require_brand_or_urgency(): void
    {
        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload())
            ->assertSessionDoesntHaveErrors(['brand', 'device_model', 'urgency', 'customer_type']);
    }

    public function test_chimney_uploads_use_the_shared_validation(): void
    {
        Storage::fake('local');

        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'attachments' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')],
        ]))->assertSessionHasErrors('attachments.0');

        $this->post(route('customer-requests.store', ['locale' => 'nl']), $this->validPayload([
            'attachments' => [UploadedFile::fake()->create('groot.pdf', 6000, 'application/pdf')],
        ]))->assertSessionHasErrors('attachments.0');

        $this->assertSame(0, CustomerRequest::count());
    }

    // ── Admin ─────────────────────────────────────────────────────────────────

    public function test_admin_detail_shows_category_and_appliance_as_labels(): void
    {
        $request = CustomerRequest::create([
            'locale'           => 'nl',
            'service_slug'     => 'schoorsteenvegen',
            'service_category' => 'schoorsteenvegen',
            'request_type'     => 'maintenance',
            'customer_name'    => 'Test Klant',
            'customer_email'   => 'test@example.com',
            'description'      => 'Laatste veegbeurt was twee jaar geleden.',
            'status'           => 'new',
            'metadata'         => [
                'service' => ['slug' => 'schoorsteenvegen', 'title' => 'Schoorsteenvegen'],
                'answers' => [
                    'chimney_appliance_type'       => 'other',
                    'chimney_appliance_type_other' => 'Pelletkachel',
                    'description'                  => 'Laatste veegbeurt was twee jaar geleden.',
                ],
            ],
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request));

        $response->assertOk();
        $response->assertSee('Schoorsteen laten vegen');
        $response->assertSee('Type haard of installatie');
        $response->assertSee('Andere');
        $response->assertSee('Pelletkachel');
        $response->assertSee('Laatste veegbeurt was twee jaar geleden.');
        $response->assertDontSee('chimney_appliance_type');
        $response->assertDontSee('Merk/model ontbreekt');
    }
}
