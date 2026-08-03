<?php

namespace Tests\Feature;

use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the SEO invariants that are easy to break silently: a canonical that
 * stops pointing at itself, a hreflang cluster that loses reciprocity, a page
 * that ships an empty description, a location page that quietly turns into a
 * copy of its neighbour.
 */
class SeoStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PageSeeder::class);
    }

    /**
     * @return array<int, string>
     */
    private function sitemapUrls(): array
    {
        $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());

        $urls = [];

        foreach ($xml->url as $url) {
            $urls[] = (string) $url->loc;
        }

        return $urls;
    }

    public function test_every_sitemap_url_returns_200(): void
    {
        $urls = $this->sitemapUrls();

        $this->assertNotEmpty($urls);

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_every_page_self_canonicalises(): void
    {
        foreach ($this->sitemapUrls() as $url) {
            $html = $this->get($url)->getContent();

            preg_match('#<link rel="canonical" href="([^"]+)">#', $html, $matches);

            $this->assertSame(
                $url,
                $matches[1] ?? null,
                "Canonical on {$url} does not point at itself."
            );
        }
    }

    public function test_hreflang_clusters_are_self_referencing_and_reciprocal(): void
    {
        foreach ($this->sitemapUrls() as $url) {
            $html = $this->get($url)->getContent();

            preg_match_all(
                '#<link rel="alternate" hreflang="([^"]+)" href="([^"]+)">#',
                $html,
                $matches,
                PREG_SET_ORDER
            );

            $alternates = [];

            foreach ($matches as $match) {
                $alternates[$match[1]] = $match[2];
            }

            // nl + fr + en + x-default
            $this->assertCount(4, $alternates, "Wrong hreflang count on {$url}");
            $this->assertArrayHasKey('x-default', $alternates, "No x-default on {$url}");

            $locale = explode('/', trim(parse_url($url, PHP_URL_PATH), '/'))[0];

            $this->assertSame(
                $url,
                $alternates[$locale] ?? null,
                "Missing self-referencing hreflang on {$url}"
            );

            // Reciprocity: each alternate must point back at this page.
            foreach ($alternates as $hreflang => $alternateUrl) {
                if ($hreflang === 'x-default') {
                    continue;
                }

                $alternateHtml = $this->get($alternateUrl)->getContent();

                $this->assertStringContainsString(
                    'hreflang="' . $locale . '" href="' . $url . '"',
                    $alternateHtml,
                    "{$alternateUrl} does not link back to {$url}"
                );
            }
        }
    }

    public function test_head_annotations_match_the_sitemap_annotations(): void
    {
        $xml = simplexml_load_string($this->get('/sitemap.xml')->getContent());

        foreach ($xml->url as $url) {
            $loc = (string) $url->loc;
            $html = $this->get($loc)->getContent();

            foreach ($url->children('http://www.w3.org/1999/xhtml')->link as $link) {
                $hreflang = (string) $link->attributes()->hreflang;
                $href = (string) $link->attributes()->href;

                $this->assertStringContainsString(
                    '<link rel="alternate" hreflang="' . $hreflang . '" href="' . $href . '">',
                    $html,
                    "Sitemap and <head> hreflang disagree for {$loc} ({$hreflang}). " .
                    'Conflicting annotations make Google drop the pair.'
                );
            }
        }
    }

    public function test_no_page_ships_an_empty_or_duplicated_title_or_description(): void
    {
        $titles = [];
        $descriptions = [];

        foreach ($this->sitemapUrls() as $url) {
            $html = $this->get($url)->getContent();

            preg_match('#<title>(.*?)</title>#s', $html, $t);
            preg_match('#<meta name="description" content="(.*?)">#s', $html, $d);

            $title = trim($t[1] ?? '');
            $description = trim($d[1] ?? '');

            $this->assertNotSame('', $title, "Empty title on {$url}");
            $this->assertNotSame('', $description, "Empty meta description on {$url}");

            $this->assertArrayNotHasKey(
                $title,
                $titles,
                "Duplicate title on {$url} and " . ($titles[$title] ?? '')
            );
            $this->assertArrayNotHasKey(
                $description,
                $descriptions,
                "Duplicate description on {$url} and " . ($descriptions[$description] ?? '')
            );

            $titles[$title] = $url;
            $descriptions[$description] = $url;
        }
    }

    public function test_every_page_has_exactly_one_h1(): void
    {
        foreach ($this->sitemapUrls() as $url) {
            $this->assertSame(
                1,
                preg_match_all('#<h1[\s>]#', $this->get($url)->getContent()),
                "Expected exactly one <h1> on {$url}"
            );
        }
    }

    public function test_structured_data_is_a_single_valid_graph_on_every_page(): void
    {
        foreach ($this->sitemapUrls() as $url) {
            $response = $this->get($url);

            $this->assertSame(
                1,
                substr_count($response->getContent(), 'application/ld+json'),
                "Expected a single JSON-LD document on {$url}"
            );

            // schemaNodes() fails the test if the JSON does not parse.
            $nodes = $this->schemaNodes($response);

            $this->assertNotNull(
                $this->schemaNode($nodes, 'WebPage'),
                "No WebPage node on {$url}"
            );
        }
    }

    public function test_language_switcher_keeps_the_visitor_on_the_same_page(): void
    {
        $html = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'verwarming']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            route('pages.show', ['locale' => 'fr', 'slug' => 'chauffage']),
            $html
        );
        $this->assertStringContainsString(
            route('pages.show', ['locale' => 'en', 'slug' => 'heating']),
            $html
        );
    }

    public function test_root_redirects_permanently_to_the_default_locale(): void
    {
        $this->get('/')
            ->assertStatus(301)
            ->assertRedirect('/nl');
    }

    public function test_location_pages_are_not_a_template_with_the_town_name_swapped(): void
    {
        $bodies = [];

        foreach (config('site.service_areas') as $area) {
            if (!($area['page'] ?? false)) {
                continue;
            }

            $slug = Str::slug($area['name']);
            $html = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => $slug]))
                ->assertOk()
                ->getContent();

            $intro = config('service-areas.' . $slug . '.nl.intro');

            $this->assertNotEmpty($intro, "No local copy for {$area['name']}");
            $this->assertStringContainsString(e($intro), $html);

            foreach ($bodies as $otherName => $otherIntro) {
                $this->assertNotSame(
                    // Strip the town name before comparing: two pages that only
                    // differ by the municipality are doorway pages.
                    str_replace($otherName, '', $otherIntro),
                    str_replace($area['name'], '', $intro),
                    "{$area['name']} and {$otherName} share their body copy."
                );
            }

            $bodies[$area['name']] = $intro;
        }

        $this->assertCount(6, $bodies);
    }

    public function test_location_and_service_pages_expose_faq_structured_data(): void
    {
        $pages = [
            route('pages.show', ['locale' => 'nl', 'slug' => 'tervuren']),
            route('pages.show', ['locale' => 'nl', 'slug' => 'verwarming']),
        ];

        foreach ($pages as $url) {
            $response = $this->get($url)->assertOk();
            $faq = $this->schemaNode($this->schemaNodes($response), 'FAQPage');

            $this->assertNotNull($faq, "No FAQPage node on {$url}");
            $this->assertNotEmpty($faq['mainEntity']);

            // Every marked-up answer must also be visible in the page body,
            // otherwise the markup is not eligible for rich results.
            foreach ($faq['mainEntity'] as $question) {
                $response->assertSee($question['name'], false);
                $response->assertSee($question['acceptedAnswer']['text'], false);
            }
        }
    }

    public function test_no_self_serving_review_markup_is_emitted(): void
    {
        $response = $this->get(route('pages.home', ['locale' => 'nl']))->assertOk();
        $nodes = $this->schemaNodes($response);

        // Google disallows self-serving review markup on LocalBusiness and
        // Organization. The site has real testimonials on the homepage; they
        // must stay out of the structured data.
        $this->assertNull($this->schemaNode($nodes, 'Review'));
        $this->assertNull($this->schemaNode($nodes, 'AggregateRating'));

        foreach ($nodes as $node) {
            $this->assertArrayNotHasKey('aggregateRating', $node);
            $this->assertArrayNotHasKey('review', $node);
        }
    }

    public function test_missing_pages_return_a_followable_404(): void
    {
        foreach (['nl', 'fr', 'en'] as $locale) {
            $this->get("/{$locale}/deze-pagina-bestaat-niet")
                ->assertNotFound()
                ->assertSee('content="noindex, follow"', false)
                ->assertSee('service-related-link', false);
        }
    }

    public function test_robots_txt_allows_the_public_site_and_blocks_the_rest(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Allow: /', $robots);
        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('Disallow: /storage/', $robots);
        $this->assertStringContainsString('Disallow: /up', $robots);
        $this->assertStringContainsString('Sitemap: https://mastechnics.be/sitemap.xml', $robots);

        // A blanket disallow would de-index the live site.
        $this->assertStringNotContainsString("Disallow: /\n", $robots);
    }

    public function test_sitemap_carries_image_entries_for_illustrated_pages(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', false)
            ->assertSee('<image:loc>', false)
            ->assertSee('hero.webp', false);
    }
}
