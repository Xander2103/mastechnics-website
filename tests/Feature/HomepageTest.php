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
            ->assertSee('Ce que disent nos clients');
    }

    public function test_homepage_en_renders_reviews_section(): void
    {
        $this->get(route('pages.home', ['locale' => 'en']))
            ->assertOk()
            ->assertSee('What our customers say');
    }

    public function test_homepage_reviews_button_opens_modal_not_direct_link(): void
    {
        $this->get(route('pages.home', ['locale' => 'nl']))
            ->assertOk()
            ->assertSee('id="reviewsModalTrigger"', false)
            ->assertSee('aria-haspopup="dialog"', false)
            ->assertSee('id="reviewsModal"', false)
            ->assertDontSee('Laat een review achter');
    }

    public function test_homepage_review_modal_lists_three_platforms(): void
    {
        $this->get(route('pages.home', ['locale' => 'nl']))
            ->assertOk()
            ->assertSee('class="reviews-platform-card"', false)
            ->assertSee(config('reviews.platforms.google.url'), false)
            ->assertSee(config('reviews.platforms.trustpilot.url'), false)
            ->assertSee(config('reviews.platforms.facebook.url'), false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_homepage_reviews_section_shows_no_placeholder_reviews(): void
    {
        // config/reviews.php ships with an empty 'reviews' array — no fake
        // testimonials should ever render.
        $this->get(route('pages.home', ['locale' => 'nl']))
            ->assertOk()
            ->assertDontSee('Thomas V.')
            ->assertDontSee('Nathalie D.')
            ->assertDontSee('class="reviews-carousel"', false);
    }

    public function test_homepage_review_card_shows_source_and_translation_note(): void
    {
        config(['reviews.reviews' => [
            [
                'author'          => 'Jean D.',
                'rating'          => 5,
                'date'            => '2026-05-01',
                'source'          => 'trustpilot',
                'source_url'      => 'https://nl.trustpilot.com/review/mastechnics.be',
                'original_locale' => 'fr',
                'original_text'   => 'Service rapide et professionnel.',
                'translations'    => [
                    'nl' => 'Snelle en professionele service.',
                ],
            ],
        ]]);

        $response = $this->get(route('pages.home', ['locale' => 'nl']));

        $response->assertOk()
            ->assertSee('Snelle en professionele service.')
            ->assertSee('Vertaald uit het Frans')
            ->assertSee('Trustpilot')
            ->assertDontSee('Service rapide et professionnel.');
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
