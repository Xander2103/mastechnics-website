<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PageTranslation;
use App\Services\SeoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(private readonly SeoService $seo)
    {
    }

    public function home(string $locale): View
    {
        $translation = PageTranslation::query()
            ->where('locale', $locale)
            ->whereHas('page', function ($query): void {
                $query->where('code', 'home');
            })
            ->with('page.translations')
            ->firstOrFail();

        return $this->renderPage($translation, $locale);
    }

    public function show(string $locale, string $slug): View|RedirectResponse
    {
        $translation = PageTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->whereHas('page', fn ($query) => $query->where('is_active', true))
            ->with('page.translations')
            ->firstOrFail();

        // The home page's own slugs (/nl/home, /fr/accueil, /en/home) are a
        // full duplicate of the locale root; consolidate them with a 301
        // instead of serving the same indexable page twice.
        if ($translation->page->code === 'home') {
            return redirect()->route('pages.home', ['locale' => $locale], 301);
        }

        return $this->renderPage($translation, $locale);
    }

    private function renderPage(PageTranslation $translation, string $locale): View
    {
        /** @var Page $page */
        $page = $translation->page;

        // Page templates append Service / FAQPage / ItemList nodes while the
        // content section renders; start from a clean slate every request.
        $this->seo->resetNodes();

        return view('pages.show', [
            'translation' => $translation,
            'page' => $page,
            'locale' => $locale,
            'seo' => [
                'canonical' => $this->seo->urlForTranslation($page, $translation),
                'alternates' => $this->seo->alternateUrls($page),
                'title' => $this->seo->metaTitle($translation),
                'description' => $this->seo->metaDescription($translation, $locale),
                'breadcrumbs' => $this->seo->breadcrumbsFor($page, $translation, $locale),
            ],
        ]);
    }
}
