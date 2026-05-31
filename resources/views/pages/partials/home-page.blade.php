@php
    $siteName = config('site.name');
    $configuredServices = config('services');

    $services = collect($configuredServices)
        ->filter(fn($service) => $service['is_active'] ?? false)
        ->map(function ($service) use ($locale) {
            return $service['translations'][$locale] ?? $service['translations']['nl'];
        })
        ->values();

    $labels = [
        'nl' => [
            'primary_cta' => 'Start aanvraag',
            'secondary_cta' => 'Bekijk diensten',
            'hero_badge' => 'Slimme technische aanvraag',

            'panel_label' => 'Hoe werkt het?',
            'panel_title' => 'Van probleem naar duidelijke inschatting.',
            'panel_points' => [
                'Kies de juiste dienst',
                'Vul technische info in over je installatie of probleem',
                'Voeg later foto\'s toe indien nodig',
                'Ontvang sneller een richtprijs of oplossing',
            ],

            'services_label' => 'Diensten',
            'services_title' => 'Technische service voor particulieren en bedrijven',
            'services_intro' =>
                $siteName .
                ' focust op technische diensten waar duidelijke informatie belangrijk is: van klassieke residentiële installaties tot commerciële koeling.',
            'more_info' => 'Meer info',

            'process_label' => 'Slimme intake',
            'process_title' => 'Geen losse berichten, maar meteen de juiste informatie.',
            'process_intro' =>
                'De aanvraagflow wordt opgebouwd om technische aanvragen duidelijker binnen te laten komen. Zo hoeft er minder heen-en-weer gemaild of gebeld te worden.',
            'process_steps' => [
                [
                    'title' => '1. Kies je dienst',
                    'description' => 'Selecteer bijvoorbeeld verwarming, airco, sanitair, ventilatie of koeling.',
                ],
                [
                    'title' => '2. Vul je situatie in',
                    'description' => 'Geef aan of het gaat om een storing, onderhoud, installatie of nieuw project.',
                ],
                [
                    'title' => '3. Voeg technische info toe',
                    'description' => 'Denk aan type toestel, merk, model, serienummer of foto\'s van het typeplaatje.',
                ],
                [
                    'title' => '4. Snellere inschatting',
                    'description' =>
                        'Met volledige info kan er sneller een richtprijs, advies of vervolgstap voorgesteld worden.',
                ],
            ],

            'cta_label' => 'Start slim',
            'cta_title' => 'Beschrijf je probleem of project meteen duidelijk.',
            'cta_text' =>
                'Beantwoord enkele gerichte vragen en voeg later foto\'s toe. Zo komt je aanvraag gestructureerd binnen en kan er sneller ingeschat worden wat nodig is.',
            'cta_button' => 'Start aanvraag',
        ],

        'fr' => [
            'primary_cta' => 'Démarrer ma demande',
            'secondary_cta' => 'Voir les services',
            'hero_badge' => 'Demande technique intelligente',

            'panel_label' => 'Comment ça fonctionne ?',
            'panel_title' => 'Du problème à une estimation claire.',
            'panel_points' => [
                'Choisissez le bon service',
                'Ajoutez les informations techniques de votre installation ou problème',
                'Ajoutez des photos si nécessaire',
                'Recevez plus rapidement une estimation ou une solution',
            ],

            'services_label' => 'Services',
            'services_title' => 'Service technique pour particuliers et entreprises',
            'services_intro' =>
                $siteName .
                ' se concentre sur les services techniques où des informations claires sont essentielles : des installations résidentielles classiques à la réfrigération commerciale.',
            'more_info' => 'Plus d\'infos',

            'process_label' => 'Prise en charge intelligente',
            'process_title' => 'Pas de messages incomplets, mais les bonnes informations dès le départ.',
            'process_intro' =>
                'Le flux de demande est conçu pour recevoir les informations techniques de manière claire. Cela réduit les échanges inutiles par e-mail ou téléphone.',
            'process_steps' => [
                [
                    'title' => '1. Choisissez votre service',
                    'description' => 'Par exemple chauffage, climatisation, plomberie, ventilation ou réfrigération.',
                ],
                [
                    'title' => '2. Décrivez votre situation',
                    'description' =>
                        'Indiquez s\'il s\'agit d\'une panne, d\'un entretien, d\'une installation ou d\'un nouveau projet.',
                ],
                [
                    'title' => '3. Ajoutez les infos techniques',
                    'description' =>
                        'Type d\'appareil, marque, modèle, numéro de série ou photo de la plaque signalétique.',
                ],
                [
                    'title' => '4. Estimation plus rapide',
                    'description' =>
                        'Avec des informations complètes, il est plus facile de proposer une estimation, un conseil ou une prochaine étape.',
                ],
            ],

            'cta_label' => 'Commencez clairement',
            'cta_title' => 'Décrivez votre problème ou projet de manière structurée.',
            'cta_text' =>
                'Répondez à quelques questions ciblées et ajoutez des photos si nécessaire. Votre demande arrive ainsi complète et peut être estimée plus rapidement.',
            'cta_button' => 'Démarrer ma demande',
        ],

        'en' => [
            'primary_cta' => 'Start request',
            'secondary_cta' => 'View services',
            'hero_badge' => 'Smart technical request',

            'panel_label' => 'How it works',
            'panel_title' => 'From problem to clear estimate.',
            'panel_points' => [
                'Choose the right service',
                'Add technical information about your installation or issue',
                'Upload photos if needed',
                'Receive a faster estimate or solution',
            ],

            'services_label' => 'Services',
            'services_title' => 'Technical service for homes and businesses',
            'services_intro' =>
                $siteName .
                ' focuses on technical services where clear information matters: from standard residential installations to commercial refrigeration.',
            'more_info' => 'More info',

            'process_label' => 'Smart intake',
            'process_title' => 'No incomplete messages, but the right information from the start.',
            'process_intro' =>
                'The request flow is designed to collect technical information clearly. This reduces unnecessary back-and-forth by email or phone.',
            'process_steps' => [
                [
                    'title' => '1. Choose your service',
                    'description' => 'For example heating, air conditioning, plumbing, ventilation or refrigeration.',
                ],
                [
                    'title' => '2. Describe your situation',
                    'description' => 'Indicate whether it is a breakdown, maintenance, installation or new project.',
                ],
                [
                    'title' => '3. Add technical details',
                    'description' => 'Device type, brand, model, serial number or a photo of the nameplate.',
                ],
                [
                    'title' => '4. Faster estimate',
                    'description' =>
                        'With complete information, it is easier to provide an estimate, advice or next step.',
                ],
            ],

            'cta_label' => 'Start clearly',
            'cta_title' => 'Describe your issue or project in a structured way.',
            'cta_text' =>
                'Answer a few targeted questions and upload photos if needed. Your request comes in complete and can be estimated faster.',
            'cta_button' => 'Start request',
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];
    $requestSlug = $locale === 'fr' ? 'demande' : ($locale === 'en' ? 'request' : 'aanvraag');
@endphp

<section class="home-hero">
    <div class="container">
        <div class="home-hero-grid">
            <div class="home-hero-content">
                <span class="eyebrow">{{ $text['hero_badge'] }}</span>

                <h1>{{ $translation->title }}</h1>

                @if ($translation->intro)
                    <p class="hero-intro">{{ $translation->intro }}</p>
                @endif

                <div class="button-row">
                    <a
                        class="button button-primary button-large"
                        href="{{ route('pages.show', [
                            'locale' => $locale,
                            'slug' => $requestSlug,
                        ]) }}"
                    >
                        {{ $text['primary_cta'] }}
                    </a>

                    <a class="button button-secondary" href="#diensten">
                        {{ $text['secondary_cta'] }}
                    </a>
                </div>
            </div>

            <aside class="hero-panel">
                <p class="panel-label">{{ $text['panel_label'] }}</p>

                <h2>{{ $text['panel_title'] }}</h2>

                <ul>
                    @foreach ($text['panel_points'] as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ul>
            </aside>
        </div>
    </div>
</section>

<section class="section" id="diensten">
    <div class="container">
        <div class="section-header">
            <span class="eyebrow">{{ $text['services_label'] }}</span>

            <h2>{{ $text['services_title'] }}</h2>

            <p>{{ $text['services_intro'] }}</p>
        </div>

        <div class="service-grid">
            @foreach ($services as $service)
                <a
                    class="service-card service-card-link"
                    href="{{ route('pages.show', [
                        'locale' => $locale,
                        'slug' => $service['slug'],
                    ]) }}"
                >
                    <h3>{{ $service['title'] }}</h3>

                    <p>{{ $service['description'] }}</p>

                    <span>{{ $text['more_info'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="section section-white">
    <div class="container">
        <div class="section-header">
            <span class="eyebrow">{{ $text['process_label'] }}</span>

            <h2>{{ $text['process_title'] }}</h2>

            <p>{{ $text['process_intro'] }}</p>
        </div>

        <div class="process-grid">
            @foreach ($text['process_steps'] as $step)
                <article class="process-card">
                    <h3>{{ $step['title'] }}</h3>
                    <p>{{ $step['description'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="section section-white">
    <div class="container">
        <div class="home-cta">
            <div>
                <span class="eyebrow eyebrow-dark">{{ $text['cta_label'] }}</span>

                <h2>{{ $text['cta_title'] }}</h2>

                <p>{{ $text['cta_text'] }}</p>
            </div>

            <a
                class="button button-light button-large"
                href="{{ route('pages.show', [
                    'locale' => $locale,
                    'slug' => $requestSlug,
                ]) }}"
            >
                {{ $text['cta_button'] }}
            </a>
        </div>
    </div>
</section>