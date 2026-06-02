@php
    $siteName = config('site.name');
    $configuredServices = config('services');

    $services = collect($configuredServices)
        ->filter(fn($service) => $service['is_active'] ?? false)
        ->map(function ($service, $key) use ($locale) {
            $trans = $service['translations'][$locale] ?? $service['translations']['nl'];
            return array_merge($trans, ['key' => $key]);
        })
        ->values();

    $serviceIcons = [
        'heating' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>',
        'airco' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2"/><path d="M9.6 4.6A2 2 0 1 1 11 8H2"/><path d="M12.6 19.4A2 2 0 1 0 14 16H2"/></svg>',
        'plumbing' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>',
        'ventilation' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 2v6h-6"/><path d="M21 13a9 9 0 1 1-3-7.7L21 8"/></svg>',
        'water-softeners' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 16.3c2.2 0 4-1.83 4-4.05 0-1.16-.57-2.26-1.71-3.19S7.29 6.75 7 5.3c-.29 1.45-1.14 2.84-2.29 3.76S3 11.1 3 12.25c0 2.22 1.8 4.05 4 4.05z"/><path d="M12.56 6.6A10.97 10.97 0 0 0 14 3.02c.5 2.5 2 4.9 4 6.5s3 3.5 3 5.5a6.98 6.98 0 0 1-11.91 4.97"/></svg>',
        'cold-rooms' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="2" x2="12" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><path d="m20 16-4-4 4-4"/><path d="m4 8 4 4-4 4"/><path d="m16 4-4 4-4-4"/><path d="m8 20 4-4 4 4"/></svg>',
    ];

    $labels = [
        'nl' => [
            'primary_cta'    => 'Vraag een offerte aan',
            'secondary_cta'  => 'Bekijk onze diensten',
            'hero_badge'     => 'Technische service — particulieren & bedrijven',
            'hero_services_label' => 'Onze diensten',

            'panel_label'  => 'Slimme intake',
            'panel_title'  => 'Beschrijf uw situatie eenmalig duidelijk.',
            'panel_points' => [
                'Kies de juiste dienst: verwarming, airco, sanitair…',
                'Vul technische gegevens in over uw installatie of probleem',
                'Voeg desgewenst foto\'s toe voor snellere inschatting',
                'Ontvang sneller een richtprijs of concreet voorstel',
            ],

            'services_label' => 'Diensten',
            'services_title' => 'Alle technische diensten onder één dak',
            'services_intro' =>
                'Van verwarmingsonderhoud en airco-installatie tot sanitaire herstellingen en koelcellen: ' .
                $siteName . ' helpt zowel particulieren als professionele klanten snel verder.',
            'more_info'      => 'Meer info →',

            'why_label' => 'Waarom Mastechnics',
            'why_title' => 'Snelle, duidelijke en vakkundige service.',
            'why_items' => [
                [
                    'title'       => 'Erkende technici',
                    'description' => 'Gecertificeerde installateurs voor gas, F-gas en verwante disciplines. Correcte uitvoering, conform de geldende normen.',
                ],
                [
                    'title'       => 'Snelle opvolging',
                    'description' => 'Via de online aanvraagflow komt alle informatie gestructureerd binnen, zodat er sneller een inschatting of afspraak gemaakt kan worden.',
                ],
                [
                    'title'       => 'Voor particulieren én bedrijven',
                    'description' => 'Van éénmalige interventie bij een panne tot periodiek onderhoud voor vaste klanten — zowel residentieel als commercieel.',
                ],
                [
                    'title'       => 'Eerlijk advies',
                    'description' => 'Geen onnodige ingrepen. Wij geven een correcte inschatting op basis van de feiten en de technische situatie.',
                ],
            ],

            'process_label' => 'Hoe werkt het?',
            'process_title' => 'Van aanvraag tot oplossing — in vier stappen.',
            'process_intro' =>
                'De aanvraagflow verzamelt de juiste technische informatie meteen bij de eerste contactopname. ' .
                'Dat bespaart heen-en-weer bellen en mailen, en versnelt de opvolging.',
            'process_steps' => [
                [
                    'title'       => '1. Kies je dienst',
                    'description' => 'Verwarming, airco, sanitair, ventilatie, waterverzachter of koeling — selecteer wat van toepassing is.',
                ],
                [
                    'title'       => '2. Beschrijf je situatie',
                    'description' => 'Gaat het om een storing, onderhoud, nieuwe installatie of een project? Geef de context mee.',
                ],
                [
                    'title'       => '3. Voeg technische info toe',
                    'description' => 'Type toestel, merk, model, serienummer of foto\'s van het typeplaatje helpen voor een snellere inschatting.',
                ],
                [
                    'title'       => '4. Snellere inschatting',
                    'description' => 'Met volledige info kan er sneller een richtprijs, advies of concrete afspraak voorgesteld worden.',
                ],
            ],

            'cta_label'  => 'Direct starten',
            'cta_title'  => 'Snel een offerte of interventie aanvragen?',
            'cta_text'   =>
                'Vul de slimme aanvraagflow in en beschrijf uw situatie zo concreet mogelijk. ' .
                'Wij nemen zo snel mogelijk contact op met een voorstel of vervolgstap.',
            'cta_button' => 'Start aanvraag',
        ],

        'fr' => [
            'primary_cta'    => 'Demander un devis',
            'secondary_cta'  => 'Voir nos services',
            'hero_badge'     => 'Service technique — particuliers et entreprises',
            'hero_services_label' => 'Nos services',

            'panel_label'  => 'Prise en charge intelligente',
            'panel_title'  => 'Décrivez votre situation une seule fois, clairement.',
            'panel_points' => [
                'Choisissez le bon service : chauffage, climatisation, plomberie…',
                'Ajoutez les données techniques de votre installation ou problème',
                'Joignez des photos pour une estimation plus rapide',
                'Recevez plus vite une estimation ou une proposition concrète',
            ],

            'services_label' => 'Services',
            'services_title' => 'Tous les services techniques sous un même toit',
            'services_intro' =>
                'De l\'entretien de chauffage à l\'installation de climatisation, en passant par les réparations sanitaires et les chambres froides : ' .
                $siteName . ' aide aussi bien les particuliers que les clients professionnels.',
            'more_info'      => 'Plus d\'infos →',

            'why_label' => 'Pourquoi Mastechnics',
            'why_title' => 'Un service rapide, clair et professionnel.',
            'why_items' => [
                [
                    'title'       => 'Techniciens certifiés',
                    'description' => 'Installateurs certifiés pour le gaz, les fluides frigorigènes (F-gaz) et les disciplines connexes. Exécution correcte, conforme aux normes en vigueur.',
                ],
                [
                    'title'       => 'Suivi rapide',
                    'description' => 'Via le flux de demande en ligne, toutes les informations arrivent de manière structurée, ce qui permet une estimation ou une prise de rendez-vous plus rapide.',
                ],
                [
                    'title'       => 'Pour particuliers et entreprises',
                    'description' => 'D\'une intervention ponctuelle en cas de panne à un entretien périodique pour clients réguliers — résidentiel comme commercial.',
                ],
                [
                    'title'       => 'Conseil honnête',
                    'description' => 'Pas d\'interventions inutiles. Nous donnons une estimation correcte basée sur les faits et la situation technique.',
                ],
            ],

            'process_label' => 'Comment ça fonctionne ?',
            'process_title' => 'De la demande à la solution — en quatre étapes.',
            'process_intro' =>
                'Le flux de demande recueille les bonnes informations techniques dès le premier contact. ' .
                'Cela évite les allers-retours par téléphone ou e-mail et accélère le suivi.',
            'process_steps' => [
                [
                    'title'       => '1. Choisissez votre service',
                    'description' => 'Chauffage, climatisation, plomberie, ventilation, adoucisseur ou réfrigération — sélectionnez ce qui s\'applique.',
                ],
                [
                    'title'       => '2. Décrivez votre situation',
                    'description' => 'S\'agit-il d\'une panne, d\'un entretien, d\'une nouvelle installation ou d\'un projet ? Donnez le contexte.',
                ],
                [
                    'title'       => '3. Ajoutez les infos techniques',
                    'description' => 'Type d\'appareil, marque, modèle, numéro de série ou photos de la plaque signalétique pour une estimation plus rapide.',
                ],
                [
                    'title'       => '4. Estimation plus rapide',
                    'description' => 'Avec des informations complètes, il est plus facile de proposer une estimation, un conseil ou un rendez-vous concret.',
                ],
            ],

            'cta_label'  => 'Commencer maintenant',
            'cta_title'  => 'Besoin d\'un devis ou d\'une intervention rapide ?',
            'cta_text'   =>
                'Remplissez le formulaire de demande intelligent et décrivez votre situation aussi concrètement que possible. ' .
                'Nous vous contacterons dès que possible avec une proposition ou une prochaine étape.',
            'cta_button' => 'Démarrer ma demande',
        ],

        'en' => [
            'primary_cta'    => 'Request a quote',
            'secondary_cta'  => 'View our services',
            'hero_badge'     => 'Technical service — homes and businesses',
            'hero_services_label' => 'Our services',

            'panel_label'  => 'Smart intake',
            'panel_title'  => 'Describe your situation once, clearly.',
            'panel_points' => [
                'Choose the right service: heating, air conditioning, plumbing…',
                'Add technical details about your installation or issue',
                'Attach photos for a faster assessment',
                'Receive a faster estimate or concrete proposal',
            ],

            'services_label' => 'Services',
            'services_title' => 'All technical services under one roof',
            'services_intro' =>
                'From heating maintenance and air conditioning installation to plumbing repairs and cold rooms: ' .
                $siteName . ' helps both homeowners and professional clients quickly.',
            'more_info'      => 'More info →',

            'why_label' => 'Why Mastechnics',
            'why_title' => 'Fast, clear and professional service.',
            'why_items' => [
                [
                    'title'       => 'Certified technicians',
                    'description' => 'Certified installers for gas, F-gas refrigerants and related disciplines. Correct execution, in line with applicable standards.',
                ],
                [
                    'title'       => 'Fast follow-up',
                    'description' => 'The online request flow collects all information in a structured way, enabling a faster estimate or appointment.',
                ],
                [
                    'title'       => 'For homes and businesses',
                    'description' => 'From a one-off emergency call-out to periodic maintenance contracts — both residential and commercial.',
                ],
                [
                    'title'       => 'Honest advice',
                    'description' => 'No unnecessary work. We give a correct assessment based on the facts and the technical situation on site.',
                ],
            ],

            'process_label' => 'How it works',
            'process_title' => 'From request to solution — in four steps.',
            'process_intro' =>
                'The request flow collects the right technical information at first contact. ' .
                'This eliminates unnecessary back-and-forth and speeds up follow-up.',
            'process_steps' => [
                [
                    'title'       => '1. Choose your service',
                    'description' => 'Heating, air conditioning, plumbing, ventilation, water softener or refrigeration — select what applies.',
                ],
                [
                    'title'       => '2. Describe your situation',
                    'description' => 'Is it a breakdown, maintenance, new installation or a project? Provide the context.',
                ],
                [
                    'title'       => '3. Add technical details',
                    'description' => 'Device type, brand, model, serial number or photos of the nameplate help for a faster assessment.',
                ],
                [
                    'title'       => '4. Faster estimate',
                    'description' => 'With complete information it is easier to propose an estimate, advice or a concrete next step.',
                ],
            ],

            'cta_label'  => 'Get started',
            'cta_title'  => 'Need a quote or fast call-out?',
            'cta_text'   =>
                'Complete the smart request form and describe your situation as concretely as possible. ' .
                'We will contact you as soon as possible with a proposal or next step.',
            'cta_button' => 'Start request',
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];
    $requestSlug = $locale === 'fr' ? 'demande' : ($locale === 'en' ? 'request' : 'aanvraag');
@endphp

<section class="home-hero">
    <div class="hero-env" aria-hidden="true">
        <div class="hero-env-grid"></div>
        <div class="hero-env-glow"  data-parallax="0.4"></div>
        <div class="hero-env-lines" data-parallax="0.25"></div>
        <div class="hero-env-air" data-parallax="0.6">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <div class="hero-env-water" data-parallax="0.3"></div>
        <div class="hero-env-heat"  data-parallax="0.5"></div>
    </div>
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

            <aside class="hero-services-visual">
                <p class="hero-services-visual-label">{{ $text['hero_services_label'] }}</p>

                <div class="hero-services-grid">
                    @foreach ($services as $service)
                        <a
                            class="service-chip"
                            href="{{ route('pages.show', [
                                'locale' => $locale,
                                'slug' => $service['slug'],
                            ]) }}"
                        >
                            <span class="service-chip-icon">
                                {!! $serviceIcons[$service['key']] ?? '' !!}
                            </span>
                            <span class="service-chip-name">{{ $service['title'] }}</span>
                        </a>
                    @endforeach
                </div>
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

<section class="section section-alt">
    <div class="container">
        <div class="section-header">
            <span class="eyebrow">{{ $text['why_label'] }}</span>
            <h2>{{ $text['why_title'] }}</h2>
        </div>

        <div class="why-grid">
            @foreach ($text['why_items'] as $item)
                <article class="why-card">
                    <h3>{{ $item['title'] }}</h3>
                    <p>{{ $item['description'] }}</p>
                </article>
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