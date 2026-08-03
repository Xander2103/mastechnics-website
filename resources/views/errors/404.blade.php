@php
    $seoService = app(\App\Services\SeoService::class);

    // The URL that 404'd still tells us which language the visitor was in.
    $segment = request()->segment(1);
    $locale = in_array($segment, config('site.locales', ['nl']), true)
        ? $segment
        : config('site.default_locale', 'nl');

    $copy = [
        'nl' => [
            'title' => 'Pagina niet gevonden',
            'meta_title' => 'Pagina niet gevonden | Mastechnics',
            'intro' => 'Deze pagina bestaat niet (meer). Mogelijk is de link verouderd of bevat het adres een typfout. Hieronder vindt u de belangrijkste pagina\'s.',
            'links_title' => 'Waar wilt u naartoe?',
            'home' => 'Naar de homepage',
            'cta' => 'Start aanvraag',
        ],
        'fr' => [
            'title' => 'Page introuvable',
            'meta_title' => 'Page introuvable | Mastechnics',
            'intro' => 'Cette page n\'existe pas ou plus. Le lien est peut-être obsolète ou l\'adresse contient une faute de frappe. Vous trouverez ci-dessous les pages principales.',
            'links_title' => 'Où souhaitez-vous aller ?',
            'home' => 'Retour à l\'accueil',
            'cta' => 'Démarrer ma demande',
        ],
        'en' => [
            'title' => 'Page not found',
            'meta_title' => 'Page not found | Mastechnics',
            'intro' => 'This page does not exist, or no longer does. The link may be outdated or the address may contain a typo. The main pages are listed below.',
            'links_title' => 'Where would you like to go?',
            'home' => 'Back to the homepage',
            'cta' => 'Start request',
        ],
    ];

    $text = $copy[$locale] ?? $copy['nl'];

    $services = collect(config('services', []))
        ->filter(fn ($service) => $service['is_active'] ?? false)
        ->map(function ($service) use ($locale) {
            $trans = $service['translations'][$locale] ?? $service['translations']['nl'];

            return ['title' => $trans['title'], 'slug' => $trans['slug']];
        })
        ->values();
@endphp

<!DOCTYPE html>
<html lang="{{ $seoService->htmlLang($locale) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- A 404 must never be indexed, but it must stay followable: the links
         below are how a crawler that hit a dead URL finds its way back into
         the site. --}}
    <meta name="robots" content="noindex, follow">
    <meta name="description" content="{{ $text['intro'] }}">
    <title>{{ $text['meta_title'] }}</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    <main>
        <section class="section section-white error-page">
            <div class="container">
                <div class="section-header">
                    <span class="eyebrow">404</span>
                    <h1>{{ $text['title'] }}</h1>
                    <p>{{ $text['intro'] }}</p>
                </div>

                <div class="button-row error-page-actions">
                    <a class="button button-primary button-large"
                       href="{{ route('pages.home', ['locale' => $locale]) }}">
                        {{ $text['home'] }}
                    </a>
                    <a class="button button-secondary"
                       href="{{ $seoService->pageUrl('request', $locale) }}">
                        {{ $text['cta'] }}
                    </a>
                </div>

                <h2 class="error-page-links-title">{{ $text['links_title'] }}</h2>

                <div class="service-related-links error-page-links">
                    <a class="service-related-link" href="{{ $seoService->pageUrl('services', $locale) }}">
                        {{ $seoService->pageLabel('services', $locale) }}
                    </a>

                    @foreach ($services as $service)
                        <a class="service-related-link"
                           href="{{ route('pages.show', ['locale' => $locale, 'slug' => $service['slug']]) }}">
                            {{ $service['title'] }}
                        </a>
                    @endforeach

                    <a class="service-related-link" href="{{ $seoService->pageUrl('service_area', $locale) }}">
                        {{ $seoService->pageLabel('service_area', $locale) }}
                    </a>

                    <a class="service-related-link" href="{{ $seoService->pageUrl('contact', $locale) }}">
                        {{ $seoService->pageLabel('contact', $locale) }}
                    </a>
                </div>
            </div>
        </section>
    </main>
</body>

</html>
