<!DOCTYPE html>
<html lang="{{ $locale ?? 'nl' }}">
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
                <a class="brand" href="{{ route('pages.home', ['locale' => $locale ?? 'nl']) }}">
                    mastechnics
                </a>

                <div class="nav-links">
                    <a href="{{ route('pages.home', ['locale' => $locale ?? 'nl']) }}">Home</a>
                    <a href="{{ route('pages.show', [
                        'locale' => $locale ?? 'nl',
                        'slug' => ($locale ?? 'nl') === 'fr' ? 'chauffage' : (($locale ?? 'nl') === 'en' ? 'heating' : 'verwarming'),
                    ]) }}">Diensten</a>
                    <a href="#" class="nav-cta">Offerte aanvragen</a>

                    @isset($page)
                        <div class="language-switcher">
                            @foreach ($page->translations as $languageVersion)
                                @if ($page->type === 'home')
                                    <a href="{{ route('pages.home', ['locale' => $languageVersion->locale]) }}">
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