@php
    $seoService = app(\App\Services\SeoService::class);

    $areaConfig = config('service-areas', []);

    $areasWithPage = collect(config('site.service_areas', []))
        ->filter(fn ($area) => $area['page'] ?? false)
        ->map(function ($area) use ($areaConfig, $locale) {
            $key = \Illuminate\Support\Str::slug($area['name']);
            $content = $areaConfig[$key][$locale] ?? $areaConfig[$key]['nl'] ?? [];

            return [
                'name' => $area['name'],
                'postal_code' => $area['postal_code'] ?? null,
                'slug' => $key,
                'intro' => $content['intro'] ?? null,
                'neighbourhoods' => $areaConfig[$key]['neighbourhoods'] ?? [],
            ];
        })
        ->values();

    $areasWithoutPage = collect(config('site.service_areas', []))
        ->reject(fn ($area) => $area['page'] ?? false)
        ->values();

    $labels = [
        'nl' => [
            'eyebrow' => 'Werkgebied',
            'covered_title' => 'Gemeenten met een eigen pagina',
            'read_more' => 'Meer over :name →',
            'other_title' => 'Ook actief in',
            'other_intro' => 'In deze aangrenzende gemeenten voeren wij eveneens opdrachten uit. Er is geen aparte pagina voor, maar aanvragen zijn er even welkom.',
            'services_title' => 'Wat we in het hele werkgebied doen',
            'cta_eyebrow' => 'Direct starten',
            'cta_title' => 'Twijfelt u of uw adres binnen het werkgebied ligt?',
            'cta_text' => 'Dien uw aanvraag in met het adres erbij. Ligt het net buiten de gebruikelijke zone, dan laten wij dat meteen weten in plaats van u te laten wachten.',
            'cta_button' => 'Start aanvraag',
        ],
        'fr' => [
            'eyebrow' => 'Zone d\'intervention',
            'covered_title' => 'Communes disposant de leur propre page',
            'read_more' => 'En savoir plus sur :name →',
            'other_title' => 'Également actifs à',
            'other_intro' => 'Nous réalisons également des missions dans ces communes limitrophes. Il n\'y a pas de page dédiée, mais les demandes y sont tout aussi bienvenues.',
            'services_title' => 'Ce que nous faisons dans toute la zone',
            'cta_eyebrow' => 'Commencer maintenant',
            'cta_title' => 'Vous doutez que votre adresse soit dans la zone ?',
            'cta_text' => 'Introduisez votre demande en mentionnant l\'adresse. Si elle se situe juste en dehors de la zone habituelle, nous vous le disons immédiatement plutôt que de vous faire attendre.',
            'cta_button' => 'Démarrer ma demande',
        ],
        'en' => [
            'eyebrow' => 'Service area',
            'covered_title' => 'Municipalities with their own page',
            'read_more' => 'More about :name →',
            'other_title' => 'Also active in',
            'other_intro' => 'We take on work in these neighbouring municipalities too. There is no dedicated page for them, but requests are just as welcome.',
            'services_title' => 'What we do across the whole service area',
            'cta_eyebrow' => 'Get started',
            'cta_title' => 'Not sure whether your address falls inside the service area?',
            'cta_text' => 'Submit your request with the address included. If it sits just outside the usual zone we will say so straight away rather than leave you waiting.',
            'cta_button' => 'Start request',
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];

    $services = collect(config('services', []))
        ->filter(fn ($service) => $service['is_active'] ?? false)
        ->map(function ($service) use ($locale) {
            $trans = $service['translations'][$locale] ?? $service['translations']['nl'];

            return ['title' => $trans['title'], 'slug' => $trans['slug'], 'description' => $trans['description']];
        })
        ->values();

    $seoService->addNode([
        '@type' => 'ItemList',
        '@id' => $seo['canonical'] . '#areas',
        'name' => $translation->title,
        'itemListElement' => $areasWithPage->map(fn ($area, $index) => [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $area['name'],
            'url' => route('pages.show', ['locale' => $locale, 'slug' => $area['slug']]),
        ])->all(),
    ]);
@endphp

<section class="section section-white">
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
        <h2 class="services-hub-heading">{{ $text['covered_title'] }}</h2>

        <div class="area-grid">
            @foreach ($areasWithPage as $area)
                <article class="area-card reveal reveal-stagger">
                    <h3>
                        <a href="{{ route('pages.show', ['locale' => $locale, 'slug' => $area['slug']]) }}">
                            {{ $area['name'] }}
                        </a>
                        @if ($area['postal_code'])
                            <span class="area-postal">{{ $area['postal_code'] }}</span>
                        @endif
                    </h3>

                    @if ($area['intro'])
                        <p>{{ \Illuminate\Support\Str::limit($area['intro'], 190) }}</p>
                    @endif

                    @if (!empty($area['neighbourhoods']))
                        <p class="area-neighbourhoods">{{ implode(' · ', $area['neighbourhoods']) }}</p>
                    @endif

                    <a class="area-card-link"
                       href="{{ route('pages.show', ['locale' => $locale, 'slug' => $area['slug']]) }}">
                        {{ str_replace(':name', $area['name'], $text['read_more']) }}
                    </a>
                </article>
            @endforeach
        </div>
    </div>
</section>

@if ($areasWithoutPage->isNotEmpty())
    <section class="section section-white">
        <div class="container">
            <div class="section-header">
                <h2>{{ $text['other_title'] }}</h2>
                <p>{{ $text['other_intro'] }}</p>
            </div>

            <ul class="location-neighbourhood-list">
                @foreach ($areasWithoutPage as $area)
                    <li>{{ $area['name'] }}{{ !empty($area['postal_code']) ? ' (' . $area['postal_code'] . ')' : '' }}</li>
                @endforeach
            </ul>
        </div>
    </section>
@endif

<section class="section section-waarom">
    <div class="container">
        <div class="section-header">
            <h2>{{ $text['services_title'] }}</h2>
        </div>

        <div class="service-related-links">
            @foreach ($services as $service)
                <a class="service-related-link"
                   href="{{ route('pages.show', ['locale' => $locale, 'slug' => $service['slug']]) }}">
                    {{ $service['title'] }}
                </a>
            @endforeach
        </div>
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
