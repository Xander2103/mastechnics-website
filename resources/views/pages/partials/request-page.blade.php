@php
    $siteName = config('site.name');
    $configuredServices = config('services');

    $services = collect($configuredServices)
        ->filter(fn ($service) => $service['is_active'] ?? false)
        ->map(function ($service) use ($locale) {
            return $service['translations'][$locale] ?? $service['translations']['nl'];
        })
        ->values();

    $labels = [
        'nl' => [
            'badge' => 'Slimme technische aanvraag',
            'service_step' => '1. Kies je dienst',
            'type_step' => '2. Type aanvraag',
            'technical_step' => '3. Technische gegevens',
            'description_step' => '4. Probleem of project',
            'summary_step' => '5. Samenvatting',

            'request_types' => [
                'Herstelling',
                'Onderhoud',
                'Installatie',
                'Nieuw project',
            ],

            'brand' => 'Merk',
            'model' => 'Model',
            'serial' => 'Serienummer',
            'unknown' => 'Ik weet dit niet',
            'description' => 'Beschrijf kort je probleem of project',
            'photos' => 'Foto’s toevoegen',
            'photos_help' => 'Bijvoorbeeld een foto van het toestel, typeplaatje of probleemzone.',
            'estimate_title' => 'Richtprijs mogelijk na volledige info',
            'estimate_text' => 'Op basis van de gekozen dienst, technische gegevens en foto’s kan ' . $siteName . ' sneller inschatten wat nodig is en indien mogelijk een richtprijs of duidelijke vervolgstap voorstellen.',
            'summary_title' => 'Voorbeeld samenvatting',
            'summary_text' => 'Verwarming • Herstelling • Technische gegevens ingevuld • Foto’s toegevoegd',
            'button' => 'Aanvraag voorbereiden',
        ],

        'fr' => [
            'badge' => 'Demande technique intelligente',
            'service_step' => '1. Choisissez votre service',
            'type_step' => '2. Type de demande',
            'technical_step' => '3. Informations techniques',
            'description_step' => '4. Problème ou projet',
            'summary_step' => '5. Résumé',

            'request_types' => [
                'Réparation',
                'Entretien',
                'Installation',
                'Nouveau projet',
            ],

            'brand' => 'Marque',
            'model' => 'Modèle',
            'serial' => 'Numéro de série',
            'unknown' => 'Je ne sais pas',
            'description' => 'Décrivez brièvement votre problème ou projet',
            'photos' => 'Ajouter des photos',
            'photos_help' => 'Par exemple une photo de l’appareil, de la plaque signalétique ou de la zone du problème.',
            'estimate_title' => 'Estimation possible après informations complètes',
            'estimate_text' => 'Grâce au service choisi, aux informations techniques et aux photos, ' . $siteName . ' peut estimer plus rapidement ce qui est nécessaire et proposer une estimation ou une prochaine étape claire.',
            'summary_title' => 'Exemple de résumé',
            'summary_text' => 'Chauffage • Réparation • Informations techniques ajoutées • Photos ajoutées',
            'button' => 'Préparer la demande',
        ],

        'en' => [
            'badge' => 'Smart technical request',
            'service_step' => '1. Choose your service',
            'type_step' => '2. Request type',
            'technical_step' => '3. Technical details',
            'description_step' => '4. Issue or project',
            'summary_step' => '5. Summary',

            'request_types' => [
                'Repair',
                'Maintenance',
                'Installation',
                'New project',
            ],

            'brand' => 'Brand',
            'model' => 'Model',
            'serial' => 'Serial number',
            'unknown' => 'I don’t know',
            'description' => 'Briefly describe your issue or project',
            'photos' => 'Add photos',
            'photos_help' => 'For example a photo of the unit, nameplate or problem area.',
            'estimate_title' => 'Estimate possible after complete information',
            'estimate_text' => 'Based on the selected service, technical details and photos, ' . $siteName . ' can estimate what is needed faster and provide an estimate or clear next step when possible.',
            'summary_title' => 'Example summary',
            'summary_text' => 'Heating • Repair • Technical details added • Photos uploaded',
            'button' => 'Prepare request',
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];
@endphp

<section class="request-hero">
    <div class="container">
        <span class="eyebrow">{{ $text['badge'] }}</span>

        <h1>{{ $translation->title }}</h1>

        @if ($translation->intro)
            <p class="request-intro">{{ $translation->intro }}</p>
        @endif
    </div>
</section>

<section class="section section-white">
    <div class="container">
        <div class="request-layout">
            <aside class="request-steps">
                <div class="request-step is-active">{{ $text['service_step'] }}</div>
                <div class="request-step">{{ $text['type_step'] }}</div>
                <div class="request-step">{{ $text['technical_step'] }}</div>
                <div class="request-step">{{ $text['description_step'] }}</div>
                <div class="request-step">{{ $text['summary_step'] }}</div>
            </aside>

            <div class="request-form-card">
                <div class="form-section">
                    <h2>{{ $text['service_step'] }}</h2>

                    <div class="option-grid">
                        @foreach ($services as $index => $service)
                            <label class="option-card {{ $index === 0 ? 'is-selected' : '' }}">
                                <input
                                    type="radio"
                                    name="service"
                                    value="{{ $service['slug'] }}"
                                    {{ $index === 0 ? 'checked' : '' }}
                                >
                                <span>{{ $service['title'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="form-section">
                    <h2>{{ $text['type_step'] }}</h2>

                    <div class="option-grid option-grid-small">
                        @foreach ($text['request_types'] as $index => $type)
                            <label class="option-card {{ $index === 0 ? 'is-selected' : '' }}">
                                <input type="radio" name="request_type" {{ $index === 0 ? 'checked' : '' }}>
                                <span>{{ $type }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="form-section">
                    <h2>{{ $text['technical_step'] }}</h2>

                    <div class="field-grid">
                        <label>
                            <span>{{ $text['brand'] }}</span>
                            <input type="text" placeholder="Vaillant, Daikin, Bosch...">
                        </label>

                        <label>
                            <span>{{ $text['model'] }}</span>
                            <input type="text" placeholder="ecoTEC plus, Altherma...">
                        </label>

                        <label>
                            <span>{{ $text['serial'] }}</span>
                            <input type="text" placeholder="SN / serial...">
                        </label>

                        <label class="checkbox-field">
                            <input type="checkbox">
                            <span>{{ $text['unknown'] }}</span>
                        </label>
                    </div>
                </div>

                <div class="form-section">
                    <h2>{{ $text['description_step'] }}</h2>

                    <label>
                        <span>{{ $text['description'] }}</span>
                        <textarea rows="5" placeholder="..."></textarea>
                    </label>

                    <div class="upload-box">
                        <strong>{{ $text['photos'] }}</strong>
                        <p>{{ $text['photos_help'] }}</p>
                    </div>
                </div>

                <div class="estimate-box">
                    <h3>{{ $text['estimate_title'] }}</h3>
                    <p>{{ $text['estimate_text'] }}</p>
                </div>

                <div class="summary-box">
                    <h3>{{ $text['summary_title'] }}</h3>
                    <p>{{ $text['summary_text'] }}</p>
                </div>

                <div class="button-row">
                    <button class="button button-primary button-large" type="button">
                        {{ $text['button'] }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>