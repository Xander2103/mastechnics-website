@php
    /**
     * Shared body for the 419 / 500 / 503 pages. Expects:
     *   $code  (string)  HTTP status shown as eyebrow
     *   $copy  (array)   nl|fr|en => ['title', 'meta_title', 'intro', 'home', 'cta' (optional), 'cta_url' (optional)]
     *
     * Deliberately self-contained (no layout, no SeoService lookups that could
     * fail): an error page must render even when the rest of the app is down.
     */
    $segment = request()->segment(1);
    $locale = in_array($segment, config('site.locales', ['nl', 'fr', 'en']), true)
        ? $segment
        : config('site.default_locale', 'nl');

    $text = $copy[$locale] ?? $copy['nl'];
    $htmlLang = ['nl' => 'nl-BE', 'fr' => 'fr-BE', 'en' => 'en'][$locale] ?? 'nl-BE';
    $homeUrl = url('/' . $locale);
@endphp
<!DOCTYPE html>
<html lang="{{ $htmlLang }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $text['meta_title'] }}</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    @vite(['resources/css/app.css'])
</head>

<body>
    <main>
        <section class="section section-white error-page">
            <div class="container">
                <div class="section-header">
                    <span class="eyebrow">{{ $code }}</span>
                    <h1>{{ $text['title'] }}</h1>
                    <p>{{ $text['intro'] }}</p>
                </div>

                <div class="button-row error-page-actions">
                    @if (! empty($text['cta']) && ! empty($text['cta_url']))
                        <a class="button button-primary button-large" href="{{ $text['cta_url'] }}">
                            {{ $text['cta'] }}
                        </a>
                        <a class="button button-secondary" href="{{ $homeUrl }}">
                            {{ $text['home'] }}
                        </a>
                    @else
                        <a class="button button-primary button-large" href="{{ $homeUrl }}">
                            {{ $text['home'] }}
                        </a>
                    @endif
                </div>
            </div>
        </section>
    </main>
</body>

</html>
