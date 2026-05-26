<?php

namespace App\Http\Controllers;

use App\Models\PageTranslation;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(string $locale): View
    {
        $translation = PageTranslation::query()
            ->where('locale', $locale)
            ->whereHas('page', function ($query): void {
                $query->where('code', 'home');
            })
            ->with('page.translations')
            ->firstOrFail();

        return view('pages.show', [
            'translation' => $translation,
            'page' => $translation->page,
            'locale' => $locale,
        ]);
    }

    public function show(string $locale, string $slug): View
    {
        $translation = PageTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->with('page.translations')
            ->firstOrFail();

        return view('pages.show', [
            'translation' => $translation,
            'page' => $translation->page,
            'locale' => $locale,
        ]);
    }
}