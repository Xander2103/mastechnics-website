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
            ->with('page')
            ->get();

        $urls = $translations->map(function (PageTranslation $translation): array {
            $page = $translation->page;

            $url = $page->type === 'home'
                ? route('pages.home', ['locale' => $translation->locale])
                : route('pages.show', ['locale' => $translation->locale, 'slug' => $translation->slug]);

            return [
                'loc'        => $url,
                'lastmod'    => $translation->updated_at->toDateString(),
                'changefreq' => $page->type === 'home' ? 'weekly' : 'monthly',
                'priority'   => $page->type === 'home' ? '1.0' : '0.7',
            ];
        });

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
