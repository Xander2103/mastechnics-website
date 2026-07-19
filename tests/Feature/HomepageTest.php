<?php

namespace Tests\Feature;

use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PageSeeder::class);
    }

    public function test_homepage_nl_renders_reviews_section(): void
    {
        $this->get(route('pages.home', ['locale' => 'nl']))
            ->assertOk()
            ->assertSee('id="reviews"', false)
            ->assertSee('Wat klanten zeggen');
    }

    public function test_homepage_fr_renders_reviews_section(): void
    {
        $this->get(route('pages.home', ['locale' => 'fr']))
            ->assertOk()
            ->assertSee('Ce que disent les clients');
    }

    public function test_homepage_en_renders_reviews_section(): void
    {
        $this->get(route('pages.home', ['locale' => 'en']))
            ->assertOk()
            ->assertSee('What customers say');
    }

    public function test_homepage_leave_review_button_has_google_url(): void
    {
        $this->get(route('pages.home', ['locale' => 'nl']))
            ->assertOk()
            ->assertSee(config('reviews.google_review_url'));
    }

    public function test_homepage_nl_nav_contains_reviews_link(): void
    {
        $this->get(route('pages.home', ['locale' => 'nl']))
            ->assertOk()
            ->assertSee('Reviews')
            ->assertSee('#reviews', false);
    }

    public function test_homepage_fr_nav_contains_avis_link(): void
    {
        $this->get(route('pages.home', ['locale' => 'fr']))
            ->assertOk()
            ->assertSee('Avis');
    }

    public function test_services_nav_has_clickable_anchor_link(): void
    {
        $this->get(route('pages.home', ['locale' => 'nl']))
            ->assertOk()
            ->assertSee('class="services-dropdown-link"', false)
            ->assertSee('#diensten', false);
    }

    public function test_homepage_reviews_hidden_when_disabled(): void
    {
        config(['reviews.enabled' => false]);

        $this->get(route('pages.home', ['locale' => 'nl']))
            ->assertOk()
            ->assertDontSee('id="reviews"', false);
    }

    public function test_homepage_includes_favicon_links(): void
    {
        $this->get(route('pages.home', ['locale' => 'nl']))
            ->assertOk()
            ->assertSee('rel="icon" href="' . asset('favicon.ico') . '"', false)
            ->assertSee('rel="apple-touch-icon"', false)
            ->assertSee('rel="manifest" href="' . asset('site.webmanifest') . '"', false);
    }

    public function test_homepage_includes_footer_credit_link(): void
    {
        $this->get(route('pages.home', ['locale' => 'nl']))
            ->assertOk()
            ->assertSee('href="https://vanmalderstudio.be/nl"', false)
            ->assertSee('VanMalderStudio');
    }
}
