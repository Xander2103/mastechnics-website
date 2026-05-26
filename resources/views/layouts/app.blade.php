@php
    $currentLocale = $locale ?? 'nl';

    $navLabels = [
        'nl' => [
            'services' => 'Diensten',
            'contact' => 'Contact',
            'request' => 'Start aanvraag',
        ],
        'fr' => [
            'services' => 'Services',
            'contact' => 'Contact',
            'request' => 'Démarrer ma demande',
        ],
        'en' => [
            'services' => 'Services',
            'contact' => 'Contact',
            'request' => 'Start request',
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

    <title>@yield('title', 'mastechnics')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="site-header">
        <div class="container">
            <nav class="navbar">
                <a class="brand" href="{{ route('pages.home', ['locale' => $currentLocale]) }}">
                    mastechnics
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
                    mastechnics
                </a>

                <p>
                    Technische service voor verwarming, airco, sanitair, ventilatie,
                    waterverzachters en koeling.
                </p>
            </div>

            <div>
                <h3>Contact</h3>

                <ul class="footer-list">
                    <li>
                        <a href="tel:+32495121178">0495 12 11 78</a>
                    </li>
                    <li>
                        <a href="mailto:martin@mastechnics.be">martin@mastechnics.be</a>
                    </li>
                    <li>
                        <a href="https://wa.me/32495121178" target="_blank" rel="noopener">
                            WhatsApp
                        </a>
                    </li>
                    <li>
                        Messenger: mastechnics
                    </li>
                </ul>
            </div>

            <div>
                <h3>Aanvraag</h3>

                <p>
                    Start een slimme aanvraag en vul meteen de juiste technische informatie in.
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
            <p>&copy; {{ date('Y') }} mastechnics. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>