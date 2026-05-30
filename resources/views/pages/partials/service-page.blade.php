@php
    $siteName = config('site.name');

    $labels = [
        'nl' => [
            'type' => 'Service',
            'quote' => 'Start aanvraag',
            'what' => 'Wat kunnen we voor u doen?',
            'why' => 'Waarom kiezen voor ' . $siteName . '?',
            'benefits' => [
                [
                    'title' => 'Snelle opvolging',
                    'description' =>
                        'Aanvragen worden gestructureerd verzameld zodat er sneller en duidelijker opgevolgd kan worden.',
                ],
                [
                    'title' => 'Voor particulieren en bedrijven',
                    'description' =>
                        'De website en intakeflow zijn voorbereid voor zowel residentiële als commerciële technische aanvragen.',
                ],
                [
                    'title' => 'Duidelijke technische intake',
                    'description' =>
                        'Belangrijke informatie zoals type installatie, merk, model en foto's kan later via het formulier verzameld worden.',
                ],
            ],
        ],
        'fr' => [
            'type' => 'Service',
            'quote' => 'Démarrer ma demande',
            'what' => 'Que pouvons-nous faire pour vous ?',
            'why' => 'Pourquoi choisir ' . $siteName . ' ?',
            'benefits' => [
                [
                    'title' => 'Suivi rapide',
                    'description' =>
                        'Les demandes sont collectées de manière structurée afin de permettre un suivi plus rapide et plus clair.',
                ],
                [
                    'title' => 'Pour particuliers et entreprises',
                    'description' =>
                        'Le site et le flux de prise en charge sont préparés pour les demandes techniques résidentielles et commerciales.',
                ],
                [
                    'title' => 'Prise en charge technique claire',
                    'description' =>
                        'Les informations importantes comme le type d'installation, la marque, le modèle et les photos pourront être collectées via le formulaire.',
                ],
            ],
        ],
        'en' => [
            'type' => 'Service',
            'quote' => 'Start request',
            'what' => 'How can we help?',
            'why' => 'Why choose ' . $siteName . '?',
            'benefits' => [
                [
                    'title' => 'Fast follow-up',
                    'description' =>
                        'Requests are collected in a structured way so they can be followed up faster and more clearly.',
                ],
                [
                    'title' => 'For homes and businesses',
                    'description' =>
                        'The website and intake flow are prepared for both residential and commercial technical requests.',
                ],
                [
                    'title' => 'Clear technical intake',
                    'description' =>
                        'Important information such as installation type, brand, model and photos can later be collected through the form.',
                ],
            ],
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];

    $requestSlug = $locale === 'fr' ? 'demande' : ($locale === 'en' ? 'request' : 'aanvraag');
@endphp

<section class="service-hero">
    <div class="container">
        <span class="eyebrow">{{ $text['type'] }}</span>

        <h1>{{ $translation->title }}</h1>

        @if ($translation->intro)
            <p class="service-intro">{{ $translation->intro }}</p>
        @endif
    </div>
</section>

<section class="section section-white">
    <div class="container">
        <div class="section-header">
            <h2>{{ $text['what'] }}</h2>

            @if ($translation->content)
                <p>{{ $translation->content }}</p>
            @endif

            <div class="button-row service-content-button">
                <a class="button button-primary button-large"
                    href="{{ route('pages.show', [
                        'locale' => $locale,
                        'slug' => $requestSlug,
                    ]) }}">
                    {{ $text['quote'] }}
                </a>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-header">
            <h2>{{ $text['why'] }}</h2>
        </div>

        <div class="service-grid">
            @foreach ($text['benefits'] as $benefit)
                <article class="service-card">
                    <h3>{{ $benefit['title'] }}</h3>
                    <p>{{ $benefit['description'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
