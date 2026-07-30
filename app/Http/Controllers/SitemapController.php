<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $translations = PageTranslation::query()
            ->whereHas('page', fn ($q) => $q->where('is_active', true))
            ->with('page.translations')
            ->get();

        $urls = $translations->map(function (PageTranslation $translation): array {
            $page = $translation->page;

            $urlFor = fn (PageTranslation $t): string => $page->type === 'home'
                ? route('pages.home', ['locale' => $t->locale])
                : route('pages.show', ['locale' => $t->locale, 'slug' => $t->slug]);

            // Same hreflang cluster as the <head> alternates: every locale
            // plus x-default on the NL version (site default locale).
            $alternates = $page->translations
                ->map(fn (PageTranslation $t): array => [
                    'hreflang' => $t->locale,
                    'href'     => $urlFor($t),
                ])
                ->values()
                ->all();

            $nl = $page->translations->firstWhere('locale', 'nl');

            if ($nl !== null) {
                $alternates[] = [
                    'hreflang' => 'x-default',
                    'href'     => $urlFor($nl),
                ];
            }

            return [
                'loc'        => $urlFor($translation),
                'lastmod'    => $translation->updated_at->toDateString(),
                'changefreq' => $page->type === 'home' ? 'weekly' : 'monthly',
                'priority'   => $page->type === 'home' ? '1.0' : '0.7',
                'alternates' => $alternates,
            ];
        });

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
