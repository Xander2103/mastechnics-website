@php
    $currentLocale = $locale ?? 'nl';

    $navLabels = [
        'nl' => [
            'home' => 'Home',
            'services' => 'Diensten',
            'request' => 'Start aanvraag',
        ],
        'fr' => [
            'home' => 'Accueil',
            'services' => 'Services',
            'request' => 'Démarrer ma demande',
        ],
        'en' => [
            'home' => 'Home',
            'services' => 'Services',
            'request' => 'Start request',
        ],
    ];

    $serviceSlugs = [
        'nl' => 'verwarming',
        'fr' => 'chauffage',
        'en' => 'heating',
    ];

    $nav = $navLabels[$currentLocale] ?? $navLabels['nl'];
    $serviceSlug = $serviceSlugs[$currentLocale] ?? $serviceSlugs['nl'];
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
                    <a href="{{ route('pages.home', ['locale' => $currentLocale]) }}">
                        {{ $nav['home'] }}
                    </a>

                    <a href="{{ route('pages.show', [
                        'locale' => $currentLocale,
                        'slug' => $serviceSlug,
                    ]) }}">
                        {{ $nav['services'] }}
                    </a>

                    <a href="#" class="nav-cta">
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
        <div class="container footer-inner">
            <p>&copy; {{ date('Y') }} mastechnics. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>