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


    $labels = [
        'nl' => [
            'primary_cta'    => 'Start aanvraag',
            'secondary_cta'  => 'Bekijk diensten',
            'hero_badge'     => 'Technische service — particulieren & bedrijven',
            'hero_headline'  => 'Technische oplossingen voor comfort en zekerheid.',
            'hero_intro'     => 'Uw partner voor sanitair, verwarming, airco, ventilatie, waterverzachters en koelcellen. Duurzame technologie, perfecte afwerking en service op maat.',

            'services_label' => 'Diensten',
            'services_title' => 'Waarvoor zoekt u hulp?',
            'services_intro' =>
                'Kies een dienst en bekijk meteen wat Mastechnics voor u kan doen. ' .
                $siteName . ' helpt zowel particulieren als professionele klanten snel verder.',
            'more_info'      => 'Meer info →',

            'why_label'   => 'Waarom Mastechnics',
            'why_title'   => 'Snelle, duidelijke en vakkundige service.',
            'why_intro'   => 'Mastechnics helpt particulieren en bedrijven met technische installaties en interventies voor sanitair, verwarming, airco, ventilatie, waterverzachters en koelcellen. We werken gestructureerd: eerst de situatie helder krijgen, daarna een correcte inschatting en een nette uitvoering.',
            'why_support' => 'Zo weet u snel waar u aan toe bent — zonder onnodige ingrepen of vaag advies.',
            'why_items'   => [
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
            'panel_soft_sub' => 'Waterbehandeling',
            'panel_ice_sub'  => 'Koelinstallaties',
            'nav_diensten'   => 'Diensten',
            'nav_waarom'     => 'Waarom Mastechnics',
            'nav_werkwijze'  => 'Hoe werkt het?',
            'nav_aanvraag'   => 'Aanvraag',
            'nav_contact'    => 'Contact',
        ],

        'fr' => [
            'primary_cta'    => 'Lancer une demande',
            'secondary_cta'  => 'Voir nos services',
            'hero_badge'     => 'Service technique — particuliers et entreprises',
            'hero_headline'  => 'Des solutions techniques qui créent le confort.',
            'hero_intro'     => 'Votre partenaire pour la plomberie, le chauffage, la climatisation, la ventilation, les adoucisseurs d\'eau et les chambres froides. Technologie durable, finition parfaite et service sur mesure.',

            'services_label' => 'Services',
            'services_title' => 'Tous les services techniques sous un même toit',
            'services_intro' =>
                'De l\'entretien de chauffage à l\'installation de climatisation, en passant par les réparations sanitaires et les chambres froides : ' .
                $siteName . ' aide aussi bien les particuliers que les clients professionnels.',
            'more_info'      => 'Plus d\'infos →',

            'why_label'   => 'Pourquoi Mastechnics',
            'why_title'   => 'Un service rapide, clair et professionnel.',
            'why_intro'   => "Mastechnics aide les particuliers et les entreprises avec des installations techniques et des interventions en plomberie, chauffage, climatisation, ventilation, adoucisseurs d'eau et chambres froides. Nous travaillons de manière structurée : d'abord clarifier la situation, puis une estimation correcte et une exécution soignée.",
            'why_support' => "Vous saurez rapidement à quoi vous en tenir — sans interventions inutiles ni conseils vagues.",
            'why_items'   => [
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
            'panel_soft_sub' => "Traitement de l'eau",
            'panel_ice_sub'  => 'Installations frigorifiques',
            'nav_diensten'   => 'Services',
            'nav_waarom'     => 'Pourquoi Mastechnics',
            'nav_werkwijze'  => 'Comment ça fonctionne ?',
            'nav_aanvraag'   => 'Demande',
            'nav_contact'    => 'Contact',
        ],

        'en' => [
            'primary_cta'    => 'Start request',
            'secondary_cta'  => 'View our services',
            'hero_badge'     => 'Technical service — homes and businesses',
            'hero_headline'  => 'Technical solutions that create comfort.',
            'hero_intro'     => 'Your partner for plumbing, heating, air conditioning, ventilation, water softeners and cold rooms. Durable technology, perfect finish and tailored service.',

            'services_label' => 'Services',
            'services_title' => 'All technical services under one roof',
            'services_intro' =>
                'From heating maintenance and air conditioning installation to plumbing repairs and cold rooms: ' .
                $siteName . ' helps both homeowners and professional clients quickly.',
            'more_info'      => 'More info →',

            'why_label'   => 'Why Mastechnics',
            'why_title'   => 'Fast, clear and professional service.',
            'why_intro'   => 'Mastechnics helps homeowners and businesses with technical installations and call-outs for plumbing, heating, air conditioning, ventilation, water softeners and cold rooms. We work in a structured way: first we clarify the situation, then we give an accurate estimate and carry out the work cleanly.',
            'why_support' => 'So you know quickly where you stand — without unnecessary work or vague advice.',
            'why_items'   => [
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
            'panel_soft_sub' => 'Water treatment',
            'panel_ice_sub'  => 'Refrigeration',
            'nav_diensten'   => 'Services',
            'nav_waarom'     => 'Why Mastechnics',
            'nav_werkwijze'  => 'How it works',
            'nav_aanvraag'   => 'Request',
            'nav_contact'    => 'Contact',
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];
    $requestSlug = $locale === 'fr' ? 'demande' : ($locale === 'en' ? 'request' : 'aanvraag');
    $hexServices = $services->keyBy('key');
@endphp

<section class="home-hero">
    <img
        src="{{ asset('assets/images/hero.webp') }}"
        alt=""
        class="home-hero-bg"
        aria-hidden="true"
        loading="eager"
        fetchpriority="high"
    >
    <div class="container">
        <div class="home-hero-layout">

            <div class="home-hero-content">
                <span class="eyebrow">{{ $text['hero_badge'] }}</span>

                <h1>{{ $text['hero_headline'] }}</h1>

                <p class="hero-intro">{{ $text['hero_intro'] }}</p>

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
            </div>{{-- end .home-hero-content --}}

            <div class="hero-service-visual" aria-label="{{ $text['services_label'] }}">

                <div class="hero-hex-pyramid">

                    {{-- Row 1: Sanitair --}}
                    <div class="hxp-row">
                        <a class="hero-hex hero-hex--water"
                           href="{{ route('pages.show', ['locale' => $locale, 'slug' => $hexServices['plumbing']['slug']]) }}"
                           aria-label="{{ $hexServices['plumbing']['title'] }}">
                            <span class="hero-hex-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg></span>
                            <span class="hero-hex-label">{{ $hexServices['plumbing']['title'] }}</span>
                        </a>
                    </div>

                    {{-- Row 2: Verwarming + Airco --}}
                    <div class="hxp-row">
                        <a class="hero-hex hero-hex--heat"
                           href="{{ route('pages.show', ['locale' => $locale, 'slug' => $hexServices['heating']['slug']]) }}"
                           aria-label="{{ $hexServices['heating']['title'] }}">
                            <span class="hero-hex-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg></span>
                            <span class="hero-hex-label">{{ $hexServices['heating']['title'] }}</span>
                        </a>
                        <a class="hero-hex hero-hex--cool"
                           href="{{ route('pages.show', ['locale' => $locale, 'slug' => $hexServices['airco']['slug']]) }}"
                           aria-label="{{ $hexServices['airco']['title'] }}">
                            <span class="hero-hex-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24"><path d="M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2"/><path d="M9.6 4.6A2 2 0 1 1 11 8H2"/><path d="M12.6 19.4A2 2 0 1 0 14 16H2"/></svg></span>
                            <span class="hero-hex-label">{{ $hexServices['airco']['title'] }}</span>
                        </a>
                    </div>

                    {{-- Row 3: Ventilatie + Waterverzachters + Koelcellen --}}
                    <div class="hxp-row">
                        <a class="hero-hex hero-hex--vent"
                           href="{{ route('pages.show', ['locale' => $locale, 'slug' => $hexServices['ventilation']['slug']]) }}"
                           aria-label="{{ $hexServices['ventilation']['title'] }}">
                            <span class="hero-hex-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24"><path d="M21 2v6h-6"/><path d="M21 13a9 9 0 1 1-3-7.7L21 8"/></svg></span>
                            <span class="hero-hex-label">{{ $hexServices['ventilation']['title'] }}</span>
                        </a>
                        <a class="hero-hex hero-hex--soft"
                           href="{{ route('pages.show', ['locale' => $locale, 'slug' => $hexServices['water-softeners']['slug']]) }}"
                           aria-label="{{ $hexServices['water-softeners']['title'] }}">
                            <span class="hero-hex-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24"><path d="M7 16.3c2.2 0 4-1.83 4-4.05 0-1.16-.57-2.26-1.71-3.19S7.29 6.75 7 5.3c-.29 1.45-1.14 2.84-2.29 3.76S3 11.1 3 12.25c0 2.22 1.8 4.05 4 4.05z"/><path d="M12.56 6.6A10.97 10.97 0 0 0 14 3.02c.5 2.5 2 4.9 4 6.5s3 3.5 3 5.5a6.98 6.98 0 0 1-11.91 4.97"/></svg></span>
                            <span class="hero-hex-label">{{ $hexServices['water-softeners']['title'] }}</span>
                        </a>
                        <a class="hero-hex hero-hex--ice"
                           href="{{ route('pages.show', ['locale' => $locale, 'slug' => $hexServices['cold-rooms']['slug']]) }}"
                           aria-label="{{ $hexServices['cold-rooms']['title'] }}">
                            <span class="hero-hex-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24"><line x1="12" y1="2" x2="12" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><path d="m20 16-4-4 4-4"/><path d="m4 8 4 4-4 4"/><path d="m16 4-4 4-4-4"/><path d="m8 20 4-4 4 4"/></svg></span>
                            <span class="hero-hex-label">{{ $hexServices['cold-rooms']['title'] }}</span>
                        </a>
                    </div>

                </div>{{-- .hero-hex-pyramid --}}

            </div>{{-- .hero-service-visual --}}

        </div>{{-- end .home-hero-layout --}}
    </div>
</section>

<section class="section section-diensten" id="diensten">
    <div class="container">
        <div class="section-header">
            <span class="eyebrow">{{ $text['services_label'] }}</span>

            <h2>{{ $text['services_title'] }}</h2>

            <p>{{ $text['services_intro'] }}</p>
        </div>

        <div class="service-grid">
            @foreach ($services as $service)
                <a
                    class="service-card service-card-link reveal reveal-stagger {{ $service['key'] === 'heating' ? 'service-card--heat' : '' }} {{ in_array($service['key'], ['airco', 'cold-rooms']) ? 'service-card--cool' : '' }}"
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

<section class="section section-waarom" id="waarom-mastechnics">
    <div class="container">
        <div class="section-header">
            <span class="eyebrow">{{ $text['why_label'] }}</span>
            <h2>{{ $text['why_title'] }}</h2>
        </div>

        <div class="about-intro reveal">
            <div class="about-intro-visual">
                <img
                    src="{{ asset('assets/images/hero.webp') }}"
                    alt=""
                    aria-hidden="true"
                    loading="lazy"
                >
            </div>
            <div class="about-intro-text">
                <p class="about-intro-body">{{ $text['why_intro'] }}</p>
                <p class="about-intro-support">{{ $text['why_support'] }}</p>
            </div>
        </div>

        <div class="why-grid">
            @foreach ($text['why_items'] as $item)
                <article class="why-card reveal reveal-stagger">
                    <h3>{{ $item['title'] }}</h3>
                    <p>{{ $item['description'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="section section-werkwijze" id="werkwijze">
    <div class="container">
        <div class="section-header">
            <span class="eyebrow">{{ $text['process_label'] }}</span>

            <h2>{{ $text['process_title'] }}</h2>

            <p>{{ $text['process_intro'] }}</p>
        </div>

        <div class="process-grid">
            @foreach ($text['process_steps'] as $step)
                <article class="process-card reveal reveal-stagger">
                    <h3>{{ $step['title'] }}</h3>
                    <p>{{ $step['description'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="section section-cta" id="aanvraag">
    <div class="container">
        <div class="home-cta reveal">
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