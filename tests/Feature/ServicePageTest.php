<?php

namespace Tests\Feature;

use Database\Seeders\PageContentSeeder;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PageSeeder::class);
        $this->seed(PageContentSeeder::class);
    }

    public function test_heating_service_page_renders_with_meta_and_h1(): void
    {
        $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'verwarming']))
            ->assertOk()
            ->assertSee('Verwarming')
            ->assertSee('Onderhoud, herstelling en installatie', false);
    }

    /**
     * The "where we deliver this service" municipality pills and the "our
     * other services" pills were removed from every service page in every
     * locale (2026-09-12). The header dropdown still lists the services and
     * the location pages stay reachable through the hub and the footer.
     */
    public function test_service_pages_no_longer_render_area_or_related_service_sections(): void
    {
        $headings = [
            'nl' => ['Waar we deze dienst uitvoeren', 'Onze andere diensten', 'Volledig werkgebied'],
            'fr' => ['Où nous réalisons ce service', 'Nos autres services', 'Toute la zone d\'intervention'],
            'en' => ['Where we deliver this service', 'Our other services', 'Full service area'],
        ];

        // config('services') also carries the framework's third-party keys
        // (postmark, ses, …); only entries with translations are services.
        $services = array_filter(config('services'), fn ($service) => isset($service['translations']));
        $this->assertCount(7, $services);

        foreach ($services as $service) {
            foreach ($headings as $locale => $texts) {
                $response = $this->get(route('pages.show', ['locale' => $locale, 'slug' => $service['translations'][$locale]['slug']]))
                    ->assertOk()
                    ->assertDontSee('service-areas-section', false)
                    ->assertDontSee('service-related-section', false)
                    ->assertDontSee('class="service-related-link"', false);

                foreach ($texts as $text) {
                    $response->assertDontSee($text);
                }

                // Still on the page: dropdown navigation, breadcrumb parent and CTA.
                $response->assertSee('role="menuitem"', false);
                $response->assertSee('class="breadcrumbs"', false);
                $response->assertSee('class="service-cta-section"', false);
            }
        }
    }

    public function test_service_page_includes_breadcrumb_structured_data(): void
    {
        $response = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'verwarming']))
            ->assertOk();

        $nodes = $this->schemaNodes($response);
        $breadcrumb = $this->schemaNode($nodes, 'BreadcrumbList');

        $this->assertNotNull($breadcrumb);
        $this->assertNotNull($this->schemaNode($nodes, 'LocalBusiness'));

        // Home > Diensten > Verwarming: a service page hangs off the services
        // hub, not straight off the homepage.
        $this->assertCount(3, $breadcrumb['itemListElement']);
        $this->assertSame('Diensten', $breadcrumb['itemListElement'][1]['name']);
        $this->assertSame('Verwarming', $breadcrumb['itemListElement'][2]['name']);

        // The current page carries no `item` — it should not link to itself.
        $this->assertArrayNotHasKey('item', $breadcrumb['itemListElement'][2]);
    }

    public function test_breadcrumb_trail_is_also_visible_to_visitors(): void
    {
        $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'verwarming']))
            ->assertOk()
            ->assertSee('class="breadcrumbs"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_all_seven_core_service_pages_render_nl(): void
    {
        $slugs = ['verwarming', 'airco', 'sanitair', 'ventilatie', 'waterverzachters', 'koelcellen', 'schoorsteenvegen'];

        foreach ($slugs as $slug) {
            $this->get(route('pages.show', ['locale' => 'nl', 'slug' => $slug]))
                ->assertOk();
        }
    }
}
