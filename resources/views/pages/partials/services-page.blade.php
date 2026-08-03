@php
    $seoService = app(\App\Services\SeoService::class);
    $siteName = config('site.name');

    $services = collect(config('services', []))
        ->filter(fn ($service) => $service['is_active'] ?? false)
        ->map(function ($service, $key) use ($locale) {
            $trans = $service['translations'][$locale] ?? $service['translations']['nl'];

            return array_merge($trans, [
                'key' => $key,
                'hero_image' => $service['hero_image'] ?? null,
            ]);
        })
        ->values();

    $areasWithPage = collect(config('site.service_areas', []))
        ->filter(fn ($area) => $area['page'] ?? false)
        ->values();

    $labels = [
        'nl' => [
            'eyebrow' => 'Diensten',
            'overview' => 'Kies uw discipline',
            'more_info' => 'Bekijk deze dienst →',
            'combined_title' => 'Waarom één partij voor al deze installaties?',
            'combined_items' => [
                ['title' => 'Eén diagnose in plaats van drie', 'description' => 'Vocht in de badkamer kan even goed een ventilatieprobleem als een sanitair probleem zijn. Eén technicus die beide beheerst, vindt de oorzaak sneller.'],
                ['title' => 'Eén dossier, één aanspreekpunt', 'description' => 'Alle installatiegegevens, foto\'s en interventies van uw gebouw komen in hetzelfde dossier terecht — ook jaren later bij een herstelling.'],
                ['title' => 'Minder verplaatsingen, minder kosten', 'description' => 'Onderhoud van de ketel en de airco kan in dezelfde afspraak. Dat scheelt voorrijkosten en een tweede halve dag wachten.'],
            ],
            'areas_title' => 'Waar werken we?',
            'areas_intro' => 'Mastechnics werkt in de Druivenstreek en het oosten van Vlaams-Brabant. Bekijk de pagina van uw gemeente voor de praktische informatie.',
            'areas_link' => 'Volledig werkgebied bekijken',
            'cta_eyebrow' => 'Direct starten',
            'cta_title' => 'Weet u niet zeker onder welke dienst uw vraag valt?',
            'cta_text' => 'Beschrijf uw situatie in de aanvraagflow. Wij bepalen dan welke discipline nodig is en nemen zo snel mogelijk contact op.',
            'cta_button' => 'Start aanvraag',
        ],
        'fr' => [
            'eyebrow' => 'Services',
            'overview' => 'Choisissez votre discipline',
            'more_info' => 'Voir ce service →',
            'combined_title' => 'Pourquoi confier toutes ces installations au même intervenant ?',
            'combined_items' => [
                ['title' => 'Un diagnostic au lieu de trois', 'description' => 'L\'humidité dans une salle de bain peut être un problème de ventilation autant qu\'un problème sanitaire. Un technicien qui maîtrise les deux trouve la cause plus vite.'],
                ['title' => 'Un dossier, un interlocuteur', 'description' => 'Toutes les données d\'installation, photos et interventions de votre bâtiment se retrouvent dans le même dossier — même des années plus tard.'],
                ['title' => 'Moins de déplacements, moins de frais', 'description' => 'L\'entretien de la chaudière et de la climatisation peut se faire lors du même rendez-vous. Cela évite des frais de déplacement et une seconde demi-journée d\'attente.'],
            ],
            'areas_title' => 'Où intervenons-nous ?',
            'areas_intro' => 'Mastechnics intervient dans le Druivenstreek et l\'est du Brabant flamand. Consultez la page de votre commune pour les informations pratiques.',
            'areas_link' => 'Voir toute la zone d\'intervention',
            'cta_eyebrow' => 'Commencer maintenant',
            'cta_title' => 'Vous ne savez pas de quel service relève votre demande ?',
            'cta_text' => 'Décrivez votre situation dans le formulaire de demande. Nous déterminons la discipline nécessaire et vous contactons au plus vite.',
            'cta_button' => 'Démarrer ma demande',
        ],
        'en' => [
            'eyebrow' => 'Services',
            'overview' => 'Choose your discipline',
            'more_info' => 'View this service →',
            'combined_title' => 'Why put all these installations with one party?',
            'combined_items' => [
                ['title' => 'One diagnosis instead of three', 'description' => 'Damp in a bathroom can just as easily be a ventilation problem as a plumbing problem. One technician who understands both finds the cause faster.'],
                ['title' => 'One file, one point of contact', 'description' => 'All installation data, photos and call-outs for your building end up in the same file — still there years later when something needs repairing.'],
                ['title' => 'Fewer visits, lower cost', 'description' => 'Boiler and air conditioning servicing can happen in the same appointment. That saves a call-out fee and a second half-day of waiting.'],
            ],
            'areas_title' => 'Where do we work?',
            'areas_intro' => 'Mastechnics works throughout the Druivenstreek region and the east of Flemish Brabant. Check your municipality page for practical details.',
            'areas_link' => 'View the full service area',
            'cta_eyebrow' => 'Get started',
            'cta_title' => 'Not sure which service your question falls under?',
            'cta_text' => 'Describe your situation in the request flow. We work out which discipline is needed and get back to you as soon as possible.',
            'cta_button' => 'Start request',
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];

    // ItemList of the service pages: an explicit machine-readable table of
    // contents for this hub, on top of the OfferCatalog on the organisation.
    $seoService->addNode([
        '@type' => 'ItemList',
        '@id' => $seo['canonical'] . '#services',
        'name' => $translation->title,
        'itemListElement' => $services->values()->map(fn ($service, $index) => [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $service['title'],
            'url' => route('pages.show', ['locale' => $locale, 'slug' => $service['slug']]),
        ])->all(),
    ]);
@endphp

<section class="section section-white services-hub-intro">
    <div class="container">
        <div class="section-header">
            <span class="eyebrow">{{ $text['eyebrow'] }}</span>
            <h1>{{ $translation->title }}</h1>
            @if ($translation->intro)
                <p>{{ $translation->intro }}</p>
            @endif
        </div>

        @if ($translation->content)
            <p class="services-hub-body">{{ $translation->content }}</p>
        @endif
    </div>
</section>

<section class="section section-diensten">
    <div class="container">
        <h2 class="services-hub-heading">{{ $text['overview'] }}</h2>

        <div class="service-grid">
            @foreach ($services as $service)
                <a
                    class="service-card service-card-link reveal reveal-stagger {{ $service['key'] === 'heating' ? 'service-card--heat' : '' }} {{ in_array($service['key'], ['airco', 'cold-rooms']) ? 'service-card--cool' : '' }}"
                    href="{{ route('pages.show', ['locale' => $locale, 'slug' => $service['slug']]) }}"
                >
                    <h3>{{ $service['title'] }}</h3>
                    <p>{{ $service['description'] }}</p>
                    <span>{{ $text['more_info'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="section section-waarom">
    <div class="container">
        <div class="section-header">
            <h2>{{ $text['combined_title'] }}</h2>
        </div>

        <div class="why-grid">
            @foreach ($text['combined_items'] as $item)
                <article class="why-card reveal reveal-stagger">
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
            <h2>{{ $text['areas_title'] }}</h2>
            <p>{{ $text['areas_intro'] }}</p>
        </div>

        <div class="service-related-links">
            @foreach ($areasWithPage as $area)
                <a class="service-related-link"
                   href="{{ route('pages.show', ['locale' => $locale, 'slug' => \Illuminate\Support\Str::slug($area['name'])]) }}">
                    {{ $area['name'] }}
                </a>
            @endforeach
        </div>

        <p class="services-hub-areas-link">
            <a href="{{ $seoService->pageUrl('service_area', $locale) }}">{{ $text['areas_link'] }}</a>
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

            <a class="button button-light button-large"
               href="{{ $seoService->pageUrl('request', $locale) }}">
                {{ $text['cta_button'] }}
            </a>
        </div>
    </div>
</section>
