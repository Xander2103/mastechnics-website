<?php

namespace Tests\Feature;

use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regression tests for the public-site fixes: duplicate home URLs, branded
 * error pages, grammatical validation messages in three languages, a
 * social image that link previews accept, and a localised footer.
 */
class PublicSiteHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PageSeeder::class);
    }

    public function test_home_slug_urls_redirect_permanently_to_the_locale_root(): void
    {
        $this->get('/nl/home')->assertRedirect(config('app.url') . '/nl')->assertStatus(301);
        $this->get('/fr/accueil')->assertRedirect(config('app.url') . '/fr')->assertStatus(301);
        $this->get('/en/home')->assertRedirect(config('app.url') . '/en')->assertStatus(301);

        // The sitemap only lists the canonical roots.
        $this->get('/sitemap.xml')->assertDontSee('/nl/home')->assertDontSee('/fr/accueil');
    }

    public function test_expired_session_page_defaults_to_dutch_and_links_back_to_the_form(): void
    {
        // VerifyCsrfToken is skipped under unit tests; render the view the
        // handler uses for a TokenMismatchException directly.
        $html = view('errors.419')->render();

        $this->assertStringContainsString('noindex', $html);
        $this->assertStringContainsString('Uw sessie is verlopen', $html);
        $this->assertStringContainsString('/nl/aanvraag', $html);
    }

    public function test_419_page_follows_the_locale_of_the_url(): void
    {
        Route::get('/fr/_test/419', fn () => abort(419));

        $this->get('/fr/_test/419')
            ->assertStatus(419)
            ->assertSee('Votre session a expiré')
            ->assertSee('/fr/demande', false);
    }

    public function test_500_page_is_localised_and_never_indexed(): void
    {
        Route::get('/en/_test/500', fn () => abort(500));

        $this->get('/en/_test/500')
            ->assertStatus(500)
            ->assertSee('Something went wrong')
            ->assertSee('noindex', false)
            ->assertDontSee('Whoops');
    }

    public function test_503_page_renders_for_maintenance(): void
    {
        Route::get('/nl/_test/503', fn () => abort(503));

        $this->get('/nl/_test/503')
            ->assertStatus(503)
            ->assertSee('Even in onderhoud');
    }

    public function test_validation_messages_are_grammatical_in_every_language(): void
    {
        $nl = $this->post(route('customer-requests.store', ['locale' => 'nl']), ['service_category' => 'sanitair'])
            ->getSession()->get('errors')->all();
        $this->assertContains('Het veld "naam" is verplicht.', $nl);
        $this->assertContains('Het veld "e-mailadres" is verplicht.', $nl);
        $this->assertNotContains('Het naam is verplicht.', $nl);

        $fr = $this->post(route('customer-requests.store', ['locale' => 'fr']), ['service_category' => 'sanitair'])
            ->getSession()->get('errors')->all();
        $this->assertContains('Le champ "nom" est obligatoire.', $fr);
        $this->assertContains('Le champ "adresse e-mail" est obligatoire.', $fr);

        $en = $this->post(route('customer-requests.store', ['locale' => 'en']), ['service_category' => 'sanitair'])
            ->getSession()->get('errors')->all();
        $this->assertContains('The "name" field is required.', $en);
        $this->assertContains('You must agree to the privacy policy before sending the request.', $en);
        $this->assertNotContains('The privacy consent field is required.', $en);
    }

    public function test_social_image_is_small_enough_for_link_previews(): void
    {
        $html = $this->get('/nl')->assertOk()->getContent();

        preg_match('/<meta property="og:image" content="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m[1] ?? null);

        $path = public_path(ltrim((string) parse_url($m[1], PHP_URL_PATH), '/'));
        $this->assertFileExists($path);
        $this->assertLessThan(300 * 1024, filesize($path));

        [$width, $height] = getimagesize($path);
        $this->assertSame(1200, $width);
        $this->assertSame(630, $height);
        $this->assertStringContainsString('<meta property="og:image:width" content="1200">', $html);
    }

    public function test_footer_copyright_line_is_localised(): void
    {
        $this->get('/nl')->assertSee('Alle rechten voorbehouden');
        $this->get('/fr')->assertSee('Tous droits réservés');
        $this->get('/en')->assertSee('All rights reserved');
    }
}
