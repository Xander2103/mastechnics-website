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

    public function test_service_page_links_to_other_services(): void
    {
        $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'verwarming']))
            ->assertOk()
            ->assertSee('class="service-related-link"', false)
            ->assertSee('Airco')
            ->assertSee('Sanitair');
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

    public function test_all_six_core_service_pages_render_nl(): void
    {
        $slugs = ['verwarming', 'airco', 'sanitair', 'ventilatie', 'waterverzachters', 'koelcellen'];

        foreach ($slugs as $slug) {
            $this->get(route('pages.show', ['locale' => 'nl', 'slug' => $slug]))
                ->assertOk();
        }
    }
}
