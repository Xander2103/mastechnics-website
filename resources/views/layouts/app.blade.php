@php
    $siteName = config('site.name');
    $siteContact = config('site.contact');

    $currentLocale = $locale ?? config('site.default_locale', 'nl');

    $navLabels = [
        'nl' => [
            'services' => 'Diensten',
            'contact' => 'Contact',
            'request' => 'Start aanvraag',
            'reviews' => 'Reviews',
            'footer_services_text' =>
                'Technische service voor verwarming, airco, sanitair, ventilatie, waterverzachters en koeling.',
            'footer_request_title' => 'Aanvraag',
            'footer_request_text' => 'Start een slimme aanvraag en vul meteen de juiste technische informatie in.',
            'designed_by' => 'Designed by VanMalderStudio',
            'vat_label' => 'BTW',
            'all_areas' => 'Volledig werkgebied',
        ],
        'fr' => [
            'services' => 'Services',
            'contact' => 'Contact',
            'request' => 'Démarrer ma demande',
            'reviews' => 'Avis',
            'footer_services_text' =>
                'Service technique pour chauffage, climatisation, plomberie, ventilation, adoucisseurs d\'eau et réfrigération.',
            'footer_request_title' => 'Demande',
            'footer_request_text' =>
                'Démarrez une demande intelligente et ajoutez directement les bonnes informations techniques.',
            'designed_by' => 'Designed by VanMalderStudio',
            'vat_label' => 'TVA',
            'all_areas' => 'Toute la zone d\'intervention',
        ],
        'en' => [
            'services' => 'Services',
            'contact' => 'Contact',
            'request' => 'Start request',
            'reviews' => 'Reviews',
            'footer_services_text' =>
                'Technical service for heating, air conditioning, plumbing, ventilation, water softeners and refrigeration.',
            'footer_request_title' => 'Request',
            'footer_request_text' => 'Start a smart request and add the right technical information immediately.',
            'designed_by' => 'Designed by VanMalderStudio',
            'vat_label' => 'VAT',
            'all_areas' => 'Full service area',
        ],
    ];

    // Build service nav links from config
    $serviceNav = collect(config('services', []))
        ->filter(fn($s) => $s['is_active'] ?? false)
        ->map(function ($s) use ($currentLocale) {
            $trans = $s['translations'][$currentLocale] ?? $s['translations']['nl'];
            return ['title' => $trans['title'], 'slug' => $trans['slug']];
        })
        ->values()
        ->toArray();

    $nav = $navLabels[$currentLocale] ?? $navLabels['nl'];

    $seoService = app(\App\Services\SeoService::class);

    // Slugs and labels of the fixed pages come from config('site.page_slugs')
    // and config('site.page_labels'). They used to be duplicated inline here
    // and in four other templates, which is how the French privacy label ended
    // up without its accent.
    $requestSlug  = $seoService->pageSlug('request', $currentLocale);
    $contactSlug  = $seoService->pageSlug('contact', $currentLocale);
    $privacySlug  = $seoService->pageSlug('privacy', $currentLocale);
    $privacyLabel = $seoService->pageLabel('privacy', $currentLocale);

    $footerAreas = collect(config('site.service_areas', []))
        ->filter(fn ($area) => $area['page'] ?? false)
        ->take(4)
        ->values();

    // Public page views pass $seo from PageController; admin views do not.
    $isPublicPage = isset($page, $seo);

    $canonical = $isPublicPage ? $seo['canonical'] : url()->current();

    // The hero backdrop is discovered late (it sits inside the content section,
    // not the head), so preloading it moves LCP forward on both the homepage
    // and the six service pages.
    $heroPreloadImage = match (true) {
        !$isPublicPage => null,
        $page->type === 'home' => asset('assets/images/hero.webp'),
        $page->type === 'service' => config("services.{$page->code}.hero_image")
            ? asset(config("services.{$page->code}.hero_image"))
            : null,
        default => null,
    };
@endphp

<!DOCTYPE html>
<html lang="{{ $seoService->htmlLang($currentLocale) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- max-image-preview:large opts every public page into large image
         thumbnails in Google Discover and image-rich SERP layouts. --}}
    <meta name="robots" content="{{ request()->routeIs('admin.*')
        ? 'noindex, nofollow, noarchive'
        : 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1' }}">
    <meta name="description" content="@yield('meta_description', '')">
    <meta name="theme-color" content="#ffffff">

    <title>@yield('title', $siteName)</title>

    {{-- Canonical URL --}}
    <link rel="canonical" href="{{ $canonical }}">

    {{-- Preload hero image (homepage only, it's the LCP element) --}}
    @if ($heroPreloadImage)
        <link rel="preload" as="image" href="{{ $heroPreloadImage }}" fetchpriority="high">
    @endif

    {{-- Scroll-reveal animations start at opacity:0 and are switched on by JS.
         Without this fallback a visitor (or a renderer) without JavaScript
         would see empty sections. --}}
    <noscript>
        <style>.reveal{opacity:1 !important;transform:none !important;}</style>
    </noscript>

    {{-- Favicons / app icons --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('favicon-48x48.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    {{-- Open Graph / Twitter --}}
    @php $socialImage = asset('assets/images/hero.png'); @endphp
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="@yield('title', $siteName)">
    <meta property="og:description" content="@yield('meta_description', '')">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $socialImage }}">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1672">
    <meta property="og:image:height" content="941">
    <meta property="og:image:alt" content="{{ $siteName }}">
    <meta property="og:locale" content="{{ $seoService->ogLocale($currentLocale) }}">
    @if ($isPublicPage)
        @foreach ($seo['alternates'] as $altLocale => $altUrl)
            @if ($altLocale !== 'x-default' && $altLocale !== $currentLocale)
                <meta property="og:locale:alternate" content="{{ $seoService->ogLocale($altLocale) }}">
            @endif
        @endforeach
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', $siteName)">
    <meta name="twitter:description" content="@yield('meta_description', '')">
    <meta name="twitter:image" content="{{ $socialImage }}">
    <meta name="twitter:image:alt" content="{{ $siteName }}">

    {{-- Hreflang alternates (only on public page views). Built from the same
         SeoService method the XML sitemap uses, so the two annotations can
         never contradict each other — conflicting pairs get dropped by Google.
         Each cluster includes a self-referencing entry and is reciprocal. --}}
    @if ($isPublicPage)
        @foreach ($seo['alternates'] as $hreflang => $altUrl)
            <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $altUrl }}">
        @endforeach
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Structured data (public pages only).
         One connected @graph with stable @id values instead of several
         stand-alone blocks: Organization, WebSite, WebPage and BreadcrumbList
         reference each other, and page templates contribute Service / FAQPage
         nodes through SeoService::addNode(). --}}
    @if ($isPublicPage)
        @php
            $schemaNodes = [
                $seoService->organizationNode($currentLocale),
                $seoService->websiteNode($currentLocale),
                $seoService->webPageNode(
                    $canonical,
                    $seo['title'],
                    $seo['description'],
                    $currentLocale,
                    !empty($seo['breadcrumbs']),
                    $socialImage,
                ),
            ];

            if (!empty($seo['breadcrumbs'])) {
                $schemaNodes[] = $seoService->breadcrumbNode($canonical, $seo['breadcrumbs']);
            }

            $schemaNodes = array_merge($schemaNodes, $seoService->extraNodes());
        @endphp

        <script type="application/ld+json">{!! json_encode(
            $seoService->graph($schemaNodes),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) !!}</script>
    @endif
</head>

<body>
@php
    // Admin pages get a dedicated header; the public marketing nav (with its
    // CTA and language switcher) is only for visitors. The session check keeps
    // admin links away from logged-out visitors on /admin/login.
    $isAdminContext = request()->routeIs('admin.*') && session()->has('admin_user_email');
@endphp

@if ($isAdminContext)
    @include('partials.admin-header')
@else
  <header class="site-header">
    <div class="container header-container">
        <a class="site-logo" href="{{ route('pages.home', ['locale' => $locale ?? 'nl']) }}">
            <img
                src="{{ asset('assets/images/Logo.webp') }}"
                alt="{{ $siteName }}"
                class="site-logo-img"
                width="176"
                height="44"
                decoding="async"
            >
        </a>

        <div class="header-mobile-right">
            <a class="header-whatsapp-btn"
               href="https://wa.me/{{ config('site.contact.whatsapp_link') }}"
               target="_blank"
               rel="noopener noreferrer"
               aria-label="{{ ($locale ?? 'nl') === 'fr' ? 'Discuter via WhatsApp' : (($locale ?? 'nl') === 'en' ? 'Chat via WhatsApp' : 'Chat via WhatsApp') }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
            </a>

            <button
                class="mobile-menu-toggle"
                type="button"
                aria-label="{{ $currentLocale === 'fr' ? 'Ouvrir le menu' : ($currentLocale === 'en' ? 'Open menu' : 'Menu openen') }}"
                aria-haspopup="true"
                aria-expanded="false"
                aria-controls="siteHeaderMenu"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>

        <div class="header-menu" id="siteHeaderMenu">
            <nav class="site-nav">
                <div class="services-dropdown">
                    <div class="services-dropdown-trigger">
                        {{-- Points at the services hub page rather than the
                             homepage anchor: a category page can rank and can
                             act as the breadcrumb parent, an anchor cannot. --}}
                        <a
                            class="services-dropdown-link"
                            href="{{ $seoService->pageUrl('services', $currentLocale) }}"
                        >
                            {{ $nav['services'] }}
                        </a>
                        <button
                            type="button"
                            class="services-dropdown-toggle"
                            aria-haspopup="true"
                            aria-expanded="false"
                            aria-controls="servicesDropdownMenu"
                            aria-label="{{ $currentLocale === 'fr' ? 'Afficher le menu des services' : ($currentLocale === 'en' ? 'Show services menu' : 'Toon dienstenmenu') }}"
                        >
                            <svg class="services-dropdown-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true" focusable="false">
                                <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                    <div class="services-dropdown-menu" id="servicesDropdownMenu" role="menu">
                        @foreach ($serviceNav as $service)
                            <a href="{{ route('pages.show', ['locale' => $currentLocale, 'slug' => $service['slug']]) }}" role="menuitem">
                                {{ $service['title'] }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <a href="{{ route('pages.home', ['locale' => $locale ?? 'nl']) }}#waarom-mastechnics">
                    {{ ($locale ?? 'nl') === 'fr' ? 'À propos' : (($locale ?? 'nl') === 'en' ? 'About' : 'Over ons') }}
                </a>

                <a href="{{ route('pages.home', ['locale' => $locale ?? 'nl']) }}#werkwijze">
                    {{ ($locale ?? 'nl') === 'fr' ? 'Méthode' : (($locale ?? 'nl') === 'en' ? 'Process' : 'Werkwijze') }}
                </a>

                <a href="{{ route('pages.home', ['locale' => $locale ?? 'nl']) }}#reviews">
                    {{ $nav['reviews'] }}
                </a>

                <a href="{{ $seoService->pageUrl('contact', $currentLocale) }}">
                    {{ $nav['contact'] }}
                </a>

                <a class="button button-primary" href="{{ $seoService->pageUrl('request', $currentLocale) }}">
                    {{ $currentLocale === 'fr' ? 'Démarrer' : ($currentLocale === 'en' ? 'Start request' : 'Start aanvraag') }}
                </a>
            </nav>

            {{-- Switching language keeps the visitor on the same page instead
                 of dumping them on the homepage. That preserves intent, and it
                 gives crawlers a real link between the hreflang alternates. --}}
            <div class="language-switcher">
                @foreach (['en', 'fr', 'nl'] as $switchLocale)
                    @php
                        $switchUrl = $isPublicPage
                            ? ($seo['alternates'][$switchLocale] ?? route('pages.home', ['locale' => $switchLocale]))
                            : route('pages.home', ['locale' => $switchLocale]);
                    @endphp
                    <a
                        href="{{ $switchUrl }}"
                        hreflang="{{ $switchLocale }}"
                        lang="{{ $switchLocale }}"
                        @if ($switchLocale === $currentLocale) aria-current="true" @endif
                    >{{ strtoupper($switchLocale) }}</a>
                @endforeach
            </div>
        </div>
    </div>
</header>
@endif

    <main>
        @yield('content')
    </main>

    <footer id="contact" class="site-footer">
        <div class="container footer-grid">
            <div>
                <a class="footer-brand" href="{{ route('pages.home', ['locale' => $currentLocale]) }}">
                    {{ $siteName }}
                </a>

                <p>
                    {{ $nav['footer_services_text'] }}
                </p>
            </div>

            <div>
                <h3>{{ $nav['contact'] }}</h3>

                <ul class="footer-list">
                    <li>
                        <a href="tel:{{ $siteContact['phone_link'] }}">
                            {{ $siteContact['phone_display'] }}
                        </a>
                    </li>

                    <li>
                        <a href="mailto:{{ $siteContact['email'] }}">
                            {{ $siteContact['email'] }}
                        </a>
                    </li>

                    <li>
                        <a href="https://wa.me/{{ $siteContact['whatsapp_link'] }}" target="_blank" rel="noopener">
                            WhatsApp
                        </a>
                    </li>

                    <li>
                        <a href="https://m.me/{{ $siteContact['messenger'] }}" target="_blank" rel="noopener noreferrer">
                            Messenger
                        </a>
                    </li>

                    @if (!empty($siteContact['address']))
                    <li>{{ $siteContact['address'] }}</li>
                    @endif

                    @if (!empty($siteContact['company_number']))
                    <li>{{ $siteContact['company_number'] }}</li>
                    @endif

                    @if (!empty(config('site.vat_number')))
                    <li>{{ $nav['vat_label'] }} {{ config('site.vat_number') }}</li>
                    @endif
                </ul>
            </div>

            @unless ($isAdminContext)
                <div>
                    <h3>{{ $nav['footer_request_title'] }}</h3>

                    <p>
                        {{ $nav['footer_request_text'] }}
                    </p>

                    <a class="footer-link"
                        href="{{ route('pages.show', [
                            'locale' => $currentLocale,
                            'slug' => $requestSlug,
                        ]) }}">
                        {{ $nav['request'] }}
                    </a>
                </div>

                {{-- Site-wide links into the two hub pages and the busiest
                     municipalities. Keeps every location page within two
                     clicks of any page on the site. --}}
                <div>
                    <h3>{{ $seoService->pageLabel('service_area', $currentLocale) }}</h3>

                    <ul class="footer-list">
                        @foreach ($footerAreas as $footerArea)
                            <li>
                                <a href="{{ route('pages.show', [
                                    'locale' => $currentLocale,
                                    'slug' => \Illuminate\Support\Str::slug($footerArea['name']),
                                ]) }}">{{ $footerArea['name'] }}</a>
                            </li>
                        @endforeach

                        <li>
                            <a href="{{ $seoService->pageUrl('service_area', $currentLocale) }}">
                                {{ $nav['all_areas'] }}
                            </a>
                        </li>

                        <li>
                            <a href="{{ $seoService->pageUrl('services', $currentLocale) }}">
                                {{ $seoService->pageLabel('services', $currentLocale) }}
                            </a>
                        </li>
                    </ul>
                </div>
            @endunless
        </div>

        <div class="container footer-bottom">
            <p>
                &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
                &nbsp;&middot;&nbsp;
                <a class="footer-privacy-link" href="{{ route('pages.show', ['locale' => $currentLocale, 'slug' => $privacySlug]) }}">
                    {{ $privacyLabel }}
                </a>
                &nbsp;&middot;&nbsp;
                <a class="footer-credit-link" href="https://vanmalderstudio.be/nl" target="_blank" rel="noopener">
                    {{ $nav['designed_by'] }}
                </a>
            </p>

            <div class="footer-bottom-right">


                @if ($isAdminContext)
                    {{-- Admin pages: Account and Uitloggen live in the admin
                         top navigation; the footer does not duplicate them. --}}
                @elseif (session()->has('admin_user_email'))
                    <a class="footer-admin-link" href="{{ route('admin.requests.index') }}">
                        Admin panel
                    </a>
                @else
                    <a class="footer-admin-link" href="{{ route('admin.login') }}">
                        Admin
                    </a>
                @endif
            </div>
        </div>
    </footer>
</body>

</html>
