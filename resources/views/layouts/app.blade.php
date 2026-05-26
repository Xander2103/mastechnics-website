@php
    $siteName = config('site.name');
    $siteContact = config('site.contact');

    $currentLocale = $locale ?? config('site.default_locale', 'nl');

    $navLabels = [
        'nl' => [
            'services' => 'Diensten',
            'contact' => 'Contact',
            'request' => 'Start aanvraag',
            'footer_services_text' => 'Technische service voor verwarming, airco, sanitair, ventilatie, waterverzachters en koeling.',
            'footer_request_title' => 'Aanvraag',
            'footer_request_text' => 'Start een slimme aanvraag en vul meteen de juiste technische informatie in.',
        ],
        'fr' => [
            'services' => 'Services',
            'contact' => 'Contact',
            'request' => 'Démarrer ma demande',
            'footer_services_text' => 'Service technique pour chauffage, climatisation, plomberie, ventilation, adoucisseurs d’eau et réfrigération.',
            'footer_request_title' => 'Demande',
            'footer_request_text' => 'Démarrez une demande intelligente et ajoutez directement les bonnes informations techniques.',
        ],
        'en' => [
            'services' => 'Services',
            'contact' => 'Contact',
            'request' => 'Start request',
            'footer_services_text' => 'Technical service for heating, air conditioning, plumbing, ventilation, water softeners and refrigeration.',
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

    $nav = $navLabels[$currentLocale] ?? $navLabels['nl'];
    $serviceSlug = $serviceSlugs[$currentLocale] ?? $serviceSlugs['nl'];
    $requestSlug = $requestSlugs[$currentLocale] ?? $requestSlugs['nl'];
    $contactSlug = $contactSlugs[$currentLocale] ?? $contactSlugs['nl'];
@endphp

<!DOCTYPE html>
<html lang="{{ $currentLocale }}">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="@yield('meta_description', '')">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', $siteName)</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="site-header">
        <div class="container">
            <nav class="navbar">
                <a class="brand" href="{{ route('pages.home', ['locale' => $currentLocale]) }}">
                    {{ $siteName }}
                </a>

                <div class="nav-links">
                    @if (isset($page) && $page->type === 'home')
                        <a href="#diensten">
                            {{ $nav['services'] }}
                        </a>
                    @else
                        <a href="{{ route('pages.home', ['locale' => $currentLocale]) }}#diensten">
                            {{ $nav['services'] }}
                        </a>
                    @endif

                    <a href="{{ route('pages.show', [
                        'locale' => $currentLocale,
                        'slug' => $contactSlug,
                    ]) }}">
                        {{ $nav['contact'] }}
                    </a>

                    <a href="{{ route('pages.show', [
                        'locale' => $currentLocale,
                        'slug' => $requestSlug,
                    ]) }}" class="nav-cta">
                        {{ $nav['request'] }}
                    </a>

                    @isset($page)
                        <div class="language-switcher">
                            @foreach ($page->translations as $languageVersion)
                                @if ($page->type === 'home')
                                    <a href="{{ route('pages.home', [
                                        'locale' => $languageVersion->locale,
                                    ]) }}">
                                        {{ strtoupper($languageVersion->locale) }}
                                    </a>
                                @else
                                    <a href="{{ route('pages.show', [
                                        'locale' => $languageVersion->locale,
                                        'slug' => $languageVersion->slug,
                                    ]) }}">
                                        {{ strtoupper($languageVersion->locale) }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @endisset
                </div>
            </nav>
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

                <a class="footer-link" href="{{ route('pages.show', [
                    'locale' => $currentLocale,
                    'slug' => $requestSlug,
                ]) }}">
                    {{ $nav['request'] }}
                </a>
            </div>
        </div>

        <div class="container footer-bottom">
            <p>&copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>