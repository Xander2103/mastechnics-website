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

    public function test_all_six_core_service_pages_render_nl(): void
    {
        $slugs = ['verwarming', 'airco', 'sanitair', 'ventilatie', 'waterverzachters', 'koelcellen'];

        foreach ($slugs as $slug) {
            $this->get(route('pages.show', ['locale' => 'nl', 'slug' => $slug]))
                ->assertOk();
        }
    }
}
