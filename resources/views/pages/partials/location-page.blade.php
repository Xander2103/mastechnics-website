@php
    $seoService = app(\App\Services\SeoService::class);
    $siteName = config('site.name');

    // pages.code is "location-<slug>"; the slug is also the config key.
    $areaKey = \Illuminate\Support\Str::after($page->code, 'location-');
    $area = config('service-areas.' . $areaKey, []);

    $areaMeta = collect(config('site.service_areas', []))
        ->first(fn ($candidate) => \Illuminate\Support\Str::slug($candidate['name']) === $areaKey);

    $areaName = $areaMeta['name'] ?? \Illuminate\Support\Str::title($areaKey);
    $postalCode = $area['postal_code'] ?? ($areaMeta['postal_code'] ?? null);

    $content = $area[$locale] ?? $area['nl'] ?? [];
    $neighbourhoods = $area['neighbourhoods'] ?? [];
    $faqs = $content['faqs'] ?? [];

    $services = collect(config('services', []))
        ->filter(fn ($service) => $service['is_active'] ?? false)
        ->map(function ($service) use ($locale) {
            $trans = $service['translations'][$locale] ?? $service['translations']['nl'];

            return ['title' => $trans['title'], 'slug' => $trans['slug'], 'description' => $trans['description']];
        })
        ->values();

    // Other municipalities with a landing page — lateral internal links so the
    // location cluster is crawlable without going back through the hub.
    $otherAreas = collect(config('site.service_areas', []))
        ->filter(fn ($candidate) => ($candidate['page'] ?? false)
            && \Illuminate\Support\Str::slug($candidate['name']) !== $areaKey)
        ->values();

    $labels = [
        'nl' => [
            'eyebrow' => 'Werkgebied',
            'covered' => 'Deelgemeenten en kernen',
            'specifics' => 'Wat is hier anders?',
            'services_title' => 'Diensten in ' . $areaName,
            'services_intro' => 'Alle disciplines van Mastechnics zijn beschikbaar in ' . $areaName . '. Klik door voor de details per dienst.',
            'faq_title' => 'Veelgestelde vragen over ' . $areaName,
            'nearby' => 'Andere gemeenten in het werkgebied',
            'hub_link' => 'Bekijk het volledige werkgebied',
            'cta_eyebrow' => 'Direct starten',
            'cta_title' => 'Een aanvraag indienen voor een adres in ' . $areaName . '?',
            'cta_text' => 'Beschrijf uw situatie in de aanvraagflow. Met het adres, het type toestel en enkele foto\'s kunnen wij meteen correct inschatten wat nodig is.',
            'cta_button' => 'Start aanvraag',
        ],
        'fr' => [
            'eyebrow' => 'Zone d\'intervention',
            'covered' => 'Sections et noyaux desservis',
            'specifics' => 'Qu\'est-ce qui change ici ?',
            'services_title' => 'Services à ' . $areaName,
            'services_intro' => 'Toutes les disciplines de Mastechnics sont disponibles à ' . $areaName . '. Cliquez pour le détail de chaque service.',
            'faq_title' => 'Questions fréquentes sur ' . $areaName,
            'nearby' => 'Autres communes de la zone d\'intervention',
            'hub_link' => 'Voir toute la zone d\'intervention',
            'cta_eyebrow' => 'Commencer maintenant',
            'cta_title' => 'Introduire une demande pour une adresse à ' . $areaName . ' ?',
            'cta_text' => 'Décrivez votre situation dans le formulaire de demande. Avec l\'adresse, le type d\'appareil et quelques photos, nous pouvons estimer correctement ce qui est nécessaire.',
            'cta_button' => 'Démarrer ma demande',
        ],
        'en' => [
            'eyebrow' => 'Service area',
            'covered' => 'Villages and areas covered',
            'specifics' => 'What is different here?',
            'services_title' => 'Services in ' . $areaName,
            'services_intro' => 'Every Mastechnics discipline is available in ' . $areaName . '. Click through for the detail of each service.',
            'faq_title' => 'Frequently asked questions about ' . $areaName,
            'nearby' => 'Other municipalities in the service area',
            'hub_link' => 'View the full service area',
            'cta_eyebrow' => 'Get started',
            'cta_title' => 'Submitting a request for an address in ' . $areaName . '?',
            'cta_text' => 'Describe your situation in the request flow. With the address, the appliance type and a few photos we can assess what is needed straight away.',
            'cta_button' => 'Start request',
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];

    // Service node scoped to this municipality, plus the visible FAQ mirrored
    // as FAQPage. Both are attached to the shared @graph in the layout head.
    $seoService->addNode($seoService->serviceNode(
        $seo['canonical'],
        $translation->title,
        $seo['description'],
        $locale,
        $areaName,
    ));

    if ($faqs !== []) {
        $seoService->addNode($seoService->faqNode($seo['canonical'], $faqs));
    }
@endphp

<section class="section section-white location-hero">
    <div class="container">
        <div class="section-header">
            <span class="eyebrow">{{ $text['eyebrow'] }}{{ $postalCode ? ' · ' . $postalCode : '' }}</span>
            <h1>{{ $translation->title }}</h1>
            @if (!empty($content['intro']))
                <p>{{ $content['intro'] }}</p>
            @endif
        </div>

        <div class="button-row">
            <a class="button button-primary button-large" href="{{ $seoService->pageUrl('request', $locale) }}">
                {{ $text['cta_button'] }}
            </a>
            <a class="button button-secondary" href="tel:{{ config('site.contact.phone_link') }}">
                {{ config('site.contact.phone_display') }}
            </a>
        </div>

        @if (!empty($neighbourhoods))
            <div class="location-neighbourhoods">
                <h2>{{ $text['covered'] }}</h2>
                <ul class="location-neighbourhood-list">
                    @foreach ($neighbourhoods as $neighbourhood)
                        <li>{{ $neighbourhood }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</section>

@if (!empty($content['points']))
    <section class="section section-waarom">
        <div class="container">
            <div class="section-header">
                <h2>{{ $text['specifics'] }}</h2>
            </div>

            <div class="why-grid">
                @foreach ($content['points'] as $point)
                    <article class="why-card reveal reveal-stagger">
                        <h3>{{ $point['title'] }}</h3>
                        <p>{{ $point['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif

<section class="section section-diensten">
    <div class="container">
        <div class="section-header">
            <h2>{{ $text['services_title'] }}</h2>
            <p>{{ $text['services_intro'] }}</p>
        </div>

        <div class="service-grid">
            @foreach ($services as $service)
                <a class="service-card service-card-link reveal reveal-stagger"
                   href="{{ route('pages.show', ['locale' => $locale, 'slug' => $service['slug']]) }}">
                    <h3>{{ $service['title'] }}</h3>
                    <p>{{ $service['description'] }}</p>
                </a>
            @endforeach
        </div>
    </div>
</section>

@if ($faqs !== [])
    @include('partials.faq', ['faqs' => $faqs, 'faqTitle' => $text['faq_title']])
@endif

<section class="section section-white">
    <div class="container">
        <div class="section-header">
            <h2>{{ $text['nearby'] }}</h2>
        </div>

        <div class="service-related-links">
            @foreach ($otherAreas as $other)
                <a class="service-related-link"
                   href="{{ route('pages.show', ['locale' => $locale, 'slug' => \Illuminate\Support\Str::slug($other['name'])]) }}">
                    {{ $other['name'] }}
                </a>
            @endforeach
        </div>

        <p class="services-hub-areas-link">
            <a href="{{ $seoService->pageUrl('service_area', $locale) }}">{{ $text['hub_link'] }}</a>
        </p>
    </div>
</section>

<section class="section section-cta">
    <div class="container">
        <div class="home-cta reveal">
            <div>
                <span class="eyebrow eyebrow-dark">{{ $text['cta_eyebrow'] }}</span>
                <h2>{{ $text['cta_title'] }}</h2>
                <p>{{ $text['cta_text'] }}</p>
            </div>

            <a class="button button-light button-large" href="{{ $seoService->pageUrl('request', $locale) }}">
                {{ $text['cta_button'] }}
            </a>
        </div>
    </div>
</section>
