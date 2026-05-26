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
                'Service technique pour chauffage, climatisation, plomberie, ventilation, adoucisseurs d’eau et réfrigération.',
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
    <div class="container header-container">
        <a class="site-logo" href="{{ route('pages.home', ['locale' => $locale ?? 'nl']) }}">
            {{ config('site.name') }}
        </a>

        <button class="mobile-menu-toggle" type="button" aria-label="Menu openen">
            <span></span>
            <span></span>
            <span></span>
        </button>

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
            <p>&copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>

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
</body>

</html>
