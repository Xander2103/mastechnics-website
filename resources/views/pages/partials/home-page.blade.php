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
            'reviews_eyebrow' => 'Klantenervaringen',
            'reviews_title'   => 'Wat klanten zeggen',
            'reviews_intro'   => 'Lees ervaringen van klanten die Mastechnics inschakelden voor installatie, onderhoud of herstelling.',
            'reviews_button'  => 'Bekijk alle reviews',
            'reviews_modal_title' => 'Reviews en sociale kanalen',
            'reviews_modal_intro' => 'Bekijk ervaringen of laat zelf een beoordeling achter via uw favoriete platform.',
            'reviews_modal_close' => 'Sluiten',
            'reviews_read_more'   => 'Lees verder',
            'reviews_platform_helpers' => [
                'google'     => 'Bekijk Google-reviews of schrijf er zelf één.',
                'trustpilot' => 'Bekijk de beoordelingen op Trustpilot.',
                'facebook'   => 'Bezoek Mastechnics op Facebook.',
            ],
            'reviews_translated_from' => [
                'fr' => 'Vertaald uit het Frans',
                'en' => 'Vertaald uit het Engels',
            ],
            'reviews_rating_aria' => ':rating van 5 sterren',
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
            'reviews_eyebrow' => 'Avis clients',
            'reviews_title'   => 'Ce que disent nos clients',
            'reviews_intro'   => "Découvrez l'expérience de clients ayant fait appel à Mastechnics pour une installation, un entretien ou une réparation.",
            'reviews_button'  => 'Voir tous les avis',
            'reviews_modal_title' => 'Avis et réseaux sociaux',
            'reviews_modal_intro' => 'Consultez les expériences de nos clients ou laissez vous-même un avis sur la plateforme de votre choix.',
            'reviews_modal_close' => 'Fermer',
            'reviews_read_more'   => 'Lire la suite',
            'reviews_platform_helpers' => [
                'google'     => 'Consultez les avis Google ou laissez le vôtre.',
                'trustpilot' => 'Consultez les évaluations sur Trustpilot.',
                'facebook'   => 'Retrouvez Mastechnics sur Facebook.',
            ],
            'reviews_translated_from' => [
                'nl' => 'Traduit du néerlandais',
                'en' => "Traduit de l'anglais",
            ],
            'reviews_rating_aria' => ':rating sur 5 étoiles',
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
            'reviews_eyebrow' => 'Customer experiences',
            'reviews_title'   => 'What our customers say',
            'reviews_intro'   => 'Read experiences from customers who chose Mastechnics for installation, maintenance or repairs.',
            'reviews_button'  => 'View all reviews',
            'reviews_modal_title' => 'Reviews and social channels',
            'reviews_modal_intro' => 'Read customer experiences or leave your own review on your preferred platform.',
            'reviews_modal_close' => 'Close',
            'reviews_read_more'   => 'Read more',
            'reviews_platform_helpers' => [
                'google'     => 'Read Google reviews or leave your own.',
                'trustpilot' => 'View ratings on Trustpilot.',
                'facebook'   => 'Visit Mastechnics on Facebook.',
            ],
            'reviews_translated_from' => [
                'nl' => 'Translated from Dutch',
                'fr' => 'Translated from French',
            ],
            'reviews_rating_aria' => ':rating out of 5 stars',
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];
    $requestSlug = $locale === 'fr' ? 'demande' : ($locale === 'en' ? 'request' : 'aanvraag');
    $hexServices = $services->keyBy('key');

    // ── Reviews: source-labeled, locale-aware, faithfully translated ───────────
    $platformIcons = [
        'google' => '<svg viewBox="0 0 48 48" width="26" height="26" aria-hidden="true" focusable="false"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.6 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.1 8.1 3l6-6C34.5 5.5 29.5 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21 21-9.4 21-21c0-1.4-.1-2.7-.4-4z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.5 15.9 18.9 13 24 13c3.1 0 5.9 1.1 8.1 3l6-6C34.5 5.5 29.5 3 24 3 16.1 3 9.2 7.5 6.3 14.7z"/><path fill="#4CAF50" d="M24 45c5.4 0 10.3-1.8 14.1-5l-6.5-5.5C29.6 36 27 37 24 37c-5.2 0-9.6-3.3-11.2-8l-6.5 5C9.1 40.4 16 45 24 45z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.3-4.2 5.6l6.5 5.5C41.5 36.6 45 30.9 45 24c0-1.4-.1-2.7-.4-3.5z"/></svg>',
        'trustpilot' => '<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="11" fill="#00b67a"/><path fill="#ffffff" d="M12 5.5l1.9 4.4 4.8.4-3.6 3.2 1.1 4.7L12 15.9l-4.2 2.3 1.1-4.7-3.6-3.2 4.8-.4z"/></svg>',
        'facebook' => '<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="11" fill="#1877F2"/><path fill="#ffffff" d="M13.5 21v-7.5h2.5l.4-3H13.5V8.5c0-.9.2-1.5 1.5-1.5H16V4.3C15.7 4.3 14.7 4 13.6 4 11.3 4 9.8 5.4 9.8 8.2v2.3H7.3v3h2.5V21z"/></svg>',
    ];

    $reviewPlatforms = collect(config('reviews.platforms', []))
        ->map(function ($platform, $key) use ($text) {
            return array_merge($platform, [
                'key'    => $key,
                'helper' => $text['reviews_platform_helpers'][$key] ?? '',
            ]);
        });

    $reviewExcerptLimit = 260;
    $translatedFromLabels = $text['reviews_translated_from'] ?? [];

    $reviewItems = collect(config('reviews.reviews', []))
        ->map(function ($review) use ($locale, $reviewExcerptLimit, $translatedFromLabels) {
            $originalLocale = $review['original_locale'] ?? $locale;
            $displayText    = $review['translations'][$locale] ?? $review['original_text'];

            $isTranslated        = $originalLocale !== $locale && isset($review['translations'][$locale]);
            $translatedFromLabel = $isTranslated ? ($translatedFromLabels[$originalLocale] ?? null) : null;

            $isLong  = mb_strlen($displayText) > $reviewExcerptLimit;
            $excerpt = $isLong
                ? rtrim(mb_substr($displayText, 0, $reviewExcerptLimit)) . '…'
                : $displayText;

            return array_merge($review, [
                'display_text'          => $excerpt,
                'is_truncated'          => $isLong,
                'is_translated'         => $isTranslated,
                'translated_from_label' => $translatedFromLabel,
            ]);
        })
        ->values();
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

@if (config('reviews.enabled'))
<section class="section section-reviews" id="reviews">
    <div class="container">
        <div class="section-header">
            <span class="eyebrow">{{ $text['reviews_eyebrow'] }}</span>
            <h2>{{ $text['reviews_title'] }}</h2>
            <p>{{ $text['reviews_intro'] }}</p>
        </div>

        @if ($reviewItems->isNotEmpty())
            <div class="reviews-carousel" aria-label="{{ $text['reviews_title'] }}">
                <div class="reviews-track-wrapper">
                    <div class="reviews-track" id="reviewsTrack">
                        @foreach ($reviewItems as $review)
                            <article class="review-card">
                                <div class="review-card-head">
                                    @if (!empty($platformIcons[$review['source']]))
                                        <span class="review-source" aria-hidden="true">
                                            {!! $platformIcons[$review['source']] !!}
                                        </span>
                                    @endif
                                    <span class="review-source-label">
                                        {{ config('reviews.platforms.' . $review['source'] . '.label', $review['source']) }}
                                    </span>
                                    @if (!empty($review['date']))
                                        <span class="review-date">{{ $review['date'] }}</span>
                                    @endif
                                </div>

                                <div class="review-stars" aria-label="{{ str_replace(':rating', $review['rating'], $text['reviews_rating_aria']) }}">
                                    @for ($s = 1; $s <= 5; $s++)
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="{{ $s <= $review['rating'] ? '#f59e0b' : 'none' }}" stroke="#f59e0b" stroke-width="1.5" aria-hidden="true" focusable="false">
                                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                        </svg>
                                    @endfor
                                </div>

                                <blockquote class="review-text">
                                    <p>{{ $review['display_text'] }}</p>
                                </blockquote>

                                @if ($review['is_translated'] && $review['translated_from_label'])
                                    <p class="review-translated-note">{{ $review['translated_from_label'] }}</p>
                                @endif

                                @if ($review['is_truncated'] && !empty($review['source_url']))
                                    <a class="review-read-more" href="{{ $review['source_url'] }}" target="_blank" rel="noopener noreferrer">
                                        {{ $text['reviews_read_more'] }}
                                    </a>
                                @endif

                                <footer class="review-footer">
                                    <strong>{{ $review['author'] }}</strong>
                                </footer>
                            </article>
                        @endforeach
                    </div>
                </div>

                <div class="reviews-controls">
                    <button class="reviews-prev" aria-label="{{ $locale === 'fr' ? 'Précédent' : ($locale === 'en' ? 'Previous' : 'Vorige') }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <div class="reviews-dots" id="reviewsDots" role="tablist" aria-label="{{ $locale === 'fr' ? 'Navigation des avis' : ($locale === 'en' ? 'Review navigation' : 'Review navigatie') }}"></div>
                    <button class="reviews-next" aria-label="{{ $locale === 'fr' ? 'Suivant' : ($locale === 'en' ? 'Next' : 'Volgende') }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>
        @endif

        <div class="reviews-cta-row">
            <button
                type="button"
                id="reviewsModalTrigger"
                class="button button-secondary"
                aria-haspopup="dialog"
                aria-controls="reviewsModal"
            >
                {{ $text['reviews_button'] }}
            </button>
        </div>
    </div>
</section>

<div class="reviews-modal-backdrop" id="reviewsModalBackdrop" hidden></div>
<div
    class="reviews-modal"
    id="reviewsModal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="reviewsModalTitle"
    aria-describedby="reviewsModalIntro"
    hidden
>
    <div class="reviews-modal-inner">
        <button type="button" class="reviews-modal-close" id="reviewsModalClose" aria-label="{{ $text['reviews_modal_close'] }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>

        <h2 id="reviewsModalTitle">{{ $text['reviews_modal_title'] }}</h2>
        <p id="reviewsModalIntro">{{ $text['reviews_modal_intro'] }}</p>

        <div class="reviews-platform-grid">
            @foreach ($reviewPlatforms as $platform)
                <a
                    class="reviews-platform-card"
                    href="{{ $platform['url'] }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="{{ $platform['label'] }} — {{ $platform['helper'] }}"
                >
                    <span class="reviews-platform-icon" aria-hidden="true">
                        {!! $platformIcons[$platform['key']] ?? '' !!}
                    </span>
                    <span class="reviews-platform-name">{{ $platform['label'] }}</span>
                    <span class="reviews-platform-helper">{{ $platform['helper'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endif

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