@php
    $labels = [
        'nl' => [
            'quote' => 'Offerte aanvragen',
            'services_button' => 'Bekijk diensten',
            'intake_label' => 'Slimme intake',
            'intake_title' => 'Van aanvraag naar duidelijke opvolging.',
            'intake_points' => [
                'Technische aanvragen gestructureerd verzamelen',
                'Geschikt voor residentiële en commerciële diensten',
                'Voorbereid op formulieren, CRM en automatisatie',
            ],
            'services_label' => 'Diensten',
            'services_title' => 'Technische service voor woningen en bedrijven',
            'services_intro' => 'Een schaalbare websitebasis voor verwarming, airco, sanitair, ventilatie, waterverzachters en professionele koeling.',
            'more_info' => 'Meer info',
            'soon' => 'Binnenkort',
            'next_step' => 'Volgende stap',
            'cta_title' => 'Een aanvraag meteen correct laten binnenkomen.',
            'cta_text' => 'Later bouwen we hier een slim offerteformulier op dat per dienst de juiste technische info vraagt.',
            'start_request' => 'Start aanvraag',
            'services' => [
                [
                    'title' => 'Verwarming',
                    'description' => 'Onderhoud, herstelling en installatie van verwarmingssystemen.',
                ],
                [
                    'title' => 'Airco',
                    'description' => 'Installatie, onderhoud en herstelling van airconditioningsystemen.',
                ],
                [
                    'title' => 'Sanitair',
                    'description' => 'Professionele hulp bij sanitaire installaties en herstellingen.',
                ],
                [
                    'title' => 'Ventilatie',
                    'description' => 'Ventilatie-oplossingen voor woningen, appartementen en bedrijven.',
                ],
                [
                    'title' => 'Waterverzachters',
                    'description' => 'Advies, installatie en onderhoud van waterverzachters.',
                ],
                [
                    'title' => 'Koelcellen',
                    'description' => 'Koeling en koelcellen voor commerciële en industriële toepassingen.',
                ],
            ],
        ],
        'fr' => [
            'quote' => 'Demander un devis',
            'services_button' => 'Voir les services',
            'intake_label' => 'Prise en charge intelligente',
            'intake_title' => 'De la demande au suivi clair.',
            'intake_points' => [
                'Collecte structurée des demandes techniques',
                'Adapté aux services résidentiels et commerciaux',
                'Préparé pour les formulaires, le CRM et l’automatisation',
            ],
            'services_label' => 'Services',
            'services_title' => 'Service technique pour particuliers et entreprises',
            'services_intro' => 'Une base web évolutive pour le chauffage, la climatisation, la plomberie, la ventilation, les adoucisseurs d’eau et la réfrigération professionnelle.',
            'more_info' => 'Plus d’infos',
            'soon' => 'Bientôt',
            'next_step' => 'Prochaine étape',
            'cta_title' => 'Recevoir une demande directement avec les bonnes informations.',
            'cta_text' => 'Nous construirons ensuite un formulaire de devis intelligent qui demande les informations techniques adaptées à chaque service.',
            'start_request' => 'Démarrer la demande',
            'services' => [
                [
                    'title' => 'Chauffage',
                    'description' => 'Entretien, réparation et installation de systèmes de chauffage.',
                ],
                [
                    'title' => 'Climatisation',
                    'description' => 'Installation, entretien et réparation de systèmes de climatisation.',
                ],
                [
                    'title' => 'Plomberie',
                    'description' => 'Aide professionnelle pour les installations et réparations sanitaires.',
                ],
                [
                    'title' => 'Ventilation',
                    'description' => 'Solutions de ventilation pour habitations, appartements et entreprises.',
                ],
                [
                    'title' => 'Adoucisseurs d’eau',
                    'description' => 'Conseil, installation et entretien d’adoucisseurs d’eau.',
                ],
                [
                    'title' => 'Chambres froides',
                    'description' => 'Réfrigération et chambres froides pour applications commerciales et industrielles.',
                ],
            ],
        ],
        'en' => [
            'quote' => 'Request a quote',
            'services_button' => 'View services',
            'intake_label' => 'Smart intake',
            'intake_title' => 'From request to clear follow-up.',
            'intake_points' => [
                'Collect technical requests in a structured way',
                'Suitable for residential and commercial services',
                'Prepared for forms, CRM and automation',
            ],
            'services_label' => 'Services',
            'services_title' => 'Technical service for homes and businesses',
            'services_intro' => 'A scalable website foundation for heating, air conditioning, plumbing, ventilation, water softeners and professional refrigeration.',
            'more_info' => 'More info',
            'soon' => 'Coming soon',
            'next_step' => 'Next step',
            'cta_title' => 'Make every request come in correctly from the start.',
            'cta_text' => 'Next, we will build a smart quote form that asks the right technical questions for each service.',
            'start_request' => 'Start request',
            'services' => [
                [
                    'title' => 'Heating',
                    'description' => 'Maintenance, repair and installation of heating systems.',
                ],
                [
                    'title' => 'Air conditioning',
                    'description' => 'Installation, maintenance and repair of air conditioning systems.',
                ],
                [
                    'title' => 'Plumbing',
                    'description' => 'Professional help with plumbing installations and repairs.',
                ],
                [
                    'title' => 'Ventilation',
                    'description' => 'Ventilation solutions for homes, apartments and businesses.',
                ],
                [
                    'title' => 'Water softeners',
                    'description' => 'Advice, installation and maintenance of water softeners.',
                ],
                [
                    'title' => 'Cold rooms',
                    'description' => 'Refrigeration and cold rooms for commercial and industrial applications.',
                ],
            ],
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];

    $heatingSlug = $locale === 'fr' ? 'chauffage' : ($locale === 'en' ? 'heating' : 'verwarming');
@endphp

<section class="home-hero">
    <div class="container">
        <div class="home-hero-grid">
            <div class="home-hero-content">
                <span class="eyebrow">mastechnics</span>

                <h1>{{ $translation->title }}</h1>

                @if ($translation->intro)
                    <p class="hero-intro">{{ $translation->intro }}</p>
                @endif

                <div class="button-row">
                    <a class="button button-primary" href="#">
                        {{ $text['quote'] }}
                    </a>

                    <a class="button button-secondary" href="{{ route('pages.show', [
                        'locale' => $locale,
                        'slug' => $heatingSlug,
                    ]) }}">
                        {{ $text['services_button'] }}
                    </a>
                </div>
            </div>

            <aside class="hero-panel">
                <p class="panel-label">{{ $text['intake_label'] }}</p>

                <h2>{{ $text['intake_title'] }}</h2>

                <ul>
                    @foreach ($text['intake_points'] as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ul>
            </aside>
        </div>
    </div>
</section>

<section class="section section-white">
    <div class="container">
        <div class="section-header">
            <span class="eyebrow">{{ $text['services_label'] }}</span>

            <h2>{{ $text['services_title'] }}</h2>

            <p>{{ $text['services_intro'] }}</p>
        </div>

        <div class="service-grid">
            @foreach ($text['services'] as $index => $service)
                <article class="service-card">
                    <h3>{{ $service['title'] }}</h3>

                    <p>{{ $service['description'] }}</p>

                    @if ($index === 0)
                        <a href="{{ route('pages.show', [
                            'locale' => $locale,
                            'slug' => $heatingSlug,
                        ]) }}">
                            {{ $text['more_info'] }}
                        </a>
                    @else
                        <a href="#">
                            {{ $text['soon'] }}
                        </a>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="home-cta">
            <div>
                <span class="eyebrow eyebrow-dark">{{ $text['next_step'] }}</span>

                <h2>{{ $text['cta_title'] }}</h2>

                <p>{{ $text['cta_text'] }}</p>
            </div>

            <a class="button button-light" href="#">
                {{ $text['start_request'] }}
            </a>
        </div>
    </div>
</section>