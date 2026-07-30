<?php

namespace Tests\Feature;

use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PageSeeder::class);
    }

    public function test_sitemap_lists_every_locale_of_every_active_page(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee(route('pages.home', ['locale' => 'nl']), false)
            ->assertSee(route('pages.home', ['locale' => 'fr']), false)
            ->assertSee(route('pages.home', ['locale' => 'en']), false)
            ->assertSee(route('pages.show', ['locale' => 'nl', 'slug' => 'aanvraag']), false)
            ->assertSee(route('pages.show', ['locale' => 'fr', 'slug' => 'demande']), false)
            ->assertSee(route('pages.show', ['locale' => 'en', 'slug' => 'request']), false);
    }

    public function test_sitemap_includes_hreflang_alternates_and_x_default(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('xmlns:xhtml="http://www.w3.org/1999/xhtml"', false)
            ->assertSee('hreflang="nl"', false)
            ->assertSee('hreflang="fr"', false)
            ->assertSee('hreflang="en"', false)
            ->assertSee('hreflang="x-default"', false)
            ->assertSee('<lastmod>', false);
    }

    public function test_public_page_head_has_seo_essentials(): void
    {
        $response = $this->get(route('pages.home', ['locale' => 'nl']))->assertOk();

        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, 'rel="canonical"'));
        $this->assertSame(1, substr_count($html, 'rel="manifest"'));

        $response
            ->assertSee('name="theme-color"', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false)
            ->assertSee('hreflang="x-default"', false)
            ->assertSee('content="index, follow"', false);
    }

    public function test_admin_login_page_is_noindexed(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('content="noindex, nofollow"', false);
    }
}
