@php
    $siteName = config('site.name');
    $siteContact = config('site.contact');

    $currentLocale = $locale ?? config('site.default_locale', 'nl');

    $navLabels = [
        'nl' => [
            'services' => 'Diensten',
            'contact' => 'Contact',
            'request' => 'Start aanvraag',
            'footer_services_text' =>
                'Technische service voor verwarming, airco, sanitair, ventilatie, waterverzachters en koeling.',
            'footer_request_title' => 'Aanvraag',
            'footer_request_text' => 'Start een slimme aanvraag en vul meteen de juiste technische informatie in.',
        ],
        'fr' => [
            'services' => 'Services',
            'contact' => 'Contact',
            'request' => 'Démarrer ma demande',
            'footer_services_text' =>
                'Service technique pour chauffage, climatisation, plomberie, ventilation, adoucisseurs d\'eau et réfrigération.',
            'footer_request_title' => 'Demande',
            'footer_request_text' =>
                'Démarrez une demande intelligente et ajoutez directement les bonnes informations techniques.',
        ],
        'en' => [
            'services' => 'Services',
            'contact' => 'Contact',
            'request' => 'Start request',
            'footer_services_text' =>
                'Technical service for heating, air conditioning, plumbing, ventilation, water softeners and refrigeration.',
            'footer_request_title' => 'Request',
            'footer_request_text' => 'Start a smart request and add the right technical information immediately.',
        ],
    ];

    $serviceSlugs = [
        'nl' => 'verwarming',
        'fr' => 'chauffage',
        'en' => 'heating',
    ];

    $requestSlugs = [
        'nl' => 'aanvraag',
        'fr' => 'demande',
        'en' => 'request',
    ];

    $contactSlugs = [
        'nl' => 'contact',
        'fr' => 'contact',
        'en' => 'contact',
    ];

    $privacySlugs = [
        'nl' => 'privacybeleid',
        'fr' => 'politique-confidentialite',
        'en' => 'privacy-policy',
    ];

    $privacyLabels = [
        'nl' => 'Privacybeleid',
        'fr' => 'Politique de confidentialite',
        'en' => 'Privacy Policy',
    ];

    $nav = $navLabels[$currentLocale] ?? $navLabels['nl'];
    $serviceSlug  = $serviceSlugs[$currentLocale]  ?? $serviceSlugs['nl'];
    $requestSlug  = $requestSlugs[$currentLocale]  ?? $requestSlugs['nl'];
    $contactSlug  = $contactSlugs[$currentLocale]  ?? $contactSlugs['nl'];
    $privacySlug  = $privacySlugs[$currentLocale]  ?? $privacySlugs['nl'];
    $privacyLabel = $privacyLabels[$currentLocale] ?? $privacyLabels['nl'];
@endphp

<!DOCTYPE html>
<html lang="{{ $currentLocale }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <meta name="description" content="@yield('meta_description', '')">

    <title>@yield('title', $siteName)</title>

    {{-- Canonical URL --}}
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Open Graph --}}
    <meta property="og:title" content="@yield('title', $siteName)">
    <meta property="og:description" content="@yield('meta_description', '')">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="{{ $currentLocale === 'fr' ? 'fr_BE' : ($currentLocale === 'en' ? 'en_GB' : 'nl_BE') }}">

    {{-- Hreflang alternates (only on public page views) --}}
    @if (isset($page))
        @foreach ($page->translations as $alt)
            @php
                $altUrl = $page->type === 'home'
                    ? route('pages.home', ['locale' => $alt->locale])
                    : route('pages.show', ['locale' => $alt->locale, 'slug' => $alt->slug]);
            @endphp
            <link rel="alternate" hreflang="{{ $alt->locale }}" href="{{ $altUrl }}">
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ route('pages.home', ['locale' => 'nl']) }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- LocalBusiness schema (public pages only) --}}
    @if (isset($page))
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "{{ config('site.name') }}",
        "telephone": "{{ config('site.contact.phone_display') }}",
        "email": "{{ config('site.contact.email') }}",
        "url": "{{ url('/nl') }}",
        "priceRange": "€€",
        "areaServed": "Belgium"
    }
    </script>
    @endif
</head>

<body>
  <header class="site-header">
    <div class="container header-container">
        <a class="site-logo" href="{{ route('pages.home', ['locale' => $locale ?? 'nl']) }}">
            <img
                src="{{ asset('assets/images/Logo.webp') }}"
                alt="MAS Technics"
                class="site-logo-img"
                width="176"
                height="44"
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

            <button class="mobile-menu-toggle" type="button" aria-label="Menu openen">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>

        <div class="header-menu">
            <nav class="site-nav">
                <a href="{{ route('pages.home', ['locale' => $locale ?? 'nl']) }}#diensten">
                    {{ ($locale ?? 'nl') === 'fr' ? 'Services' : (($locale ?? 'nl') === 'en' ? 'Services' : 'Diensten') }}
                </a>

                <a href="{{ route('pages.show', ['locale' => $locale ?? 'nl', 'slug' => ($locale ?? 'nl') === 'fr' ? 'contact' : 'contact']) }}">
                    Contact
                </a>

                <a class="button button-primary" href="{{ route('pages.show', [
                    'locale' => $locale ?? 'nl',
                    'slug' => ($locale ?? 'nl') === 'fr' ? 'demande' : (($locale ?? 'nl') === 'en' ? 'request' : 'aanvraag'),
                ]) }}">
                    {{ ($locale ?? 'nl') === 'fr' ? 'Démarrer' : (($locale ?? 'nl') === 'en' ? 'Start request' : 'Start aanvraag') }}
                </a>
            </nav>

            <div class="language-switcher">
                <a href="{{ route('pages.home', ['locale' => 'en']) }}">EN</a>
                <a href="{{ route('pages.home', ['locale' => 'fr']) }}">FR</a>
                <a href="{{ route('pages.home', ['locale' => 'nl']) }}">NL</a>
            </div>
        </div>
    </div>
</header>

    <main>
        @yield('content')
    </main>

    <footer class="site-footer">
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
                        Messenger: {{ $siteContact['messenger'] }}
                    </li>
                </ul>
            </div>

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
        </div>

        <div class="container footer-bottom">
            <p>
                &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
                &nbsp;&middot;&nbsp;
                <a class="footer-privacy-link" href="{{ route('pages.show', ['locale' => $currentLocale, 'slug' => $privacySlug]) }}">
                    {{ $privacyLabel }}
                </a>
            </p>

            @if (session()->has('admin_user_email'))
                <div class="footer-admin-actions">
                    <a class="footer-admin-link" href="{{ route('admin.requests.index') }}">
                        Admin panel
                    </a>

                    <form method="POST" action="{{ route('admin.logout') }}" class="footer-admin-form">
                        @csrf

                        <button type="submit" class="footer-admin-link">
                            Uitloggen
                        </button>
                    </form>
                </div>
            @else
                <a class="footer-admin-link" href="{{ route('admin.login') }}">
                    Admin
                </a>
            @endif
        </div>
    </footer>
</body>

</html>
