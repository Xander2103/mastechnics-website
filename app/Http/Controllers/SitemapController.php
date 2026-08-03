<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PageTranslation;
use App\Services\SeoService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __construct(private readonly SeoService $seo)
    {
    }

    public function index(): Response
    {
        $translations = PageTranslation::query()
            ->whereHas('page', fn ($q) => $q->where('is_active', true))
            ->with('page.translations')
            ->get();

        $urls = $translations
            ->map(fn (PageTranslation $translation): array => $this->urlEntry($translation))
            ->sortByDesc('priority')
            ->values();

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml')
            // Crawlers re-fetch the sitemap often; an hour of shared caching
            // keeps that off the database without delaying real updates.
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * @return array<string, mixed>
     */
    private function urlEntry(PageTranslation $translation): array
    {
        /** @var Page $page */
        $page = $translation->page;

        $alternates = [];

        foreach ($this->seo->alternateUrls($page) as $hreflang => $href) {
            $alternates[] = ['hreflang' => $hreflang, 'href' => $href];
        }

        return [
            'loc' => $this->seo->urlForTranslation($page, $translation),
            'lastmod' => $translation->updated_at->toAtomString(),
            'changefreq' => $this->changeFrequency($page),
            'priority' => $this->priority($page),
            'alternates' => $alternates,
            'images' => $this->images($page),
        ];
    }

    private function changeFrequency(Page $page): string
    {
        return match ($page->type) {
            'home' => 'weekly',
            'privacy' => 'yearly',
            default => 'monthly',
        };
    }

    private function priority(Page $page): string
    {
        return match ($page->type) {
            'home' => '1.0',
            'service', 'request' => '0.9',
            'services', 'service_area', 'location' => '0.8',
            'contact' => '0.7',
            'privacy' => '0.3',
            default => '0.6',
        };
    }

    /**
     * Hero artwork of a page, so Google Images can associate the photo with the
     * page it actually illustrates.
     *
     * @return array<int, string>
     */
    private function images(Page $page): array
    {
        if ($page->type === 'home') {
            return [asset('assets/images/hero.webp')];
        }

        $heroImage = config("services.{$page->code}.hero_image");

        return $heroImage !== null ? [asset($heroImage)] : [];
    }
}
