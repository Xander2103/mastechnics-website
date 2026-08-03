<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PageTranslation;
use App\Services\SeoService;
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

    public function show(string $locale, string $slug): View
    {
        $translation = PageTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->whereHas('page', fn ($query) => $query->where('is_active', true))
            ->with('page.translations')
            ->firstOrFail();

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
