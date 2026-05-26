<!DOCTYPE html>
<html lang="{{ $locale ?? 'nl' }}">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="@yield('meta_description', '')">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Website Martin')</title>
</head>
<body>
    <header>
        <nav>
            <a href="/nl/verwarming">Mastechnics</a>

            @isset($page)
                <div>
                    @foreach ($page->translations as $languageVersion)
                        <a href="{{ route('pages.show', [
                            'locale' => $languageVersion->locale,
                            'slug' => $languageVersion->slug,
                        ]) }}">
                            {{ strtoupper($languageVersion->locale) }}
                        </a>
                    @endforeach
                </div>
            @endisset
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    <footer>
        <p>&copy; {{ date('Y') }} Mastechnics</p>
    </footer>
</body>
</html>