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
            'description_step' => '3. Probleem of project',
            'technical_step' => '4. Technische gegevens',
            'contact_step' => '5. Contactgegevens',
            'summary_step' => '6. Samenvatting',

            'request_types' => [
                ['value' => 'repair', 'label' => 'Herstelling'],
                ['value' => 'maintenance', 'label' => 'Onderhoud'],
                ['value' => 'installation', 'label' => 'Installatie'],
                ['value' => 'new_project', 'label' => 'Nieuw project'],
            ],

            'description' => 'Beschrijf kort je probleem of project',
            'photos' => 'Foto’s toevoegen',
            'photos_help' => 'Foto-upload komt later. Voor nu kan de klant het probleem beschrijven.',

            'brand' => 'Merk',
            'model' => 'Model',
            'serial' => 'Serienummer',
            'unknown' => 'Ik weet dit niet',

            'name' => 'Naam',
            'email' => 'E-mailadres',
            'phone' => 'Telefoonnummer',

            'estimate_title' => 'Richtprijs mogelijk na volledige info',
            'estimate_text' => 'Op basis van de gekozen dienst, technische gegevens en foto’s kan ' . $siteName . ' sneller inschatten wat nodig is en indien mogelijk een richtprijs of duidelijke vervolgstap voorstellen.',
            'summary_title' => 'Samenvatting',
            'summary_text' => 'De aanvraag wordt opgeslagen zodat ze later in een adminpaneel opgevolgd kan worden.',
            'button' => 'Aanvraag verzenden',
            'success' => 'Je aanvraag werd goed ontvangen. We nemen zo snel mogelijk contact op.',
            'errors_title' => 'Controleer de ingevulde gegevens.',
        ],

        'fr' => [
            'badge' => 'Demande technique intelligente',
            'service_step' => '1. Choisissez votre service',
            'type_step' => '2. Type de demande',
            'description_step' => '3. Problème ou projet',
            'technical_step' => '4. Informations techniques',
            'contact_step' => '5. Coordonnées',
            'summary_step' => '6. Résumé',

            'request_types' => [
                ['value' => 'repair', 'label' => 'Réparation'],
                ['value' => 'maintenance', 'label' => 'Entretien'],
                ['value' => 'installation', 'label' => 'Installation'],
                ['value' => 'new_project', 'label' => 'Nouveau projet'],
            ],

            'description' => 'Décrivez brièvement votre problème ou projet',
            'photos' => 'Ajouter des photos',
            'photos_help' => 'L’upload de photos sera ajouté plus tard. Pour l’instant, le client peut décrire le problème.',

            'brand' => 'Marque',
            'model' => 'Modèle',
            'serial' => 'Numéro de série',
            'unknown' => 'Je ne sais pas',

            'name' => 'Nom',
            'email' => 'Adresse e-mail',
            'phone' => 'Numéro de téléphone',

            'estimate_title' => 'Estimation possible après informations complètes',
            'estimate_text' => 'Grâce au service choisi, aux informations techniques et aux photos, ' . $siteName . ' peut estimer plus rapidement ce qui est nécessaire et proposer une estimation ou une prochaine étape claire.',
            'summary_title' => 'Résumé',
            'summary_text' => 'La demande sera enregistrée afin de pouvoir être suivie plus tard dans un espace admin.',
            'button' => 'Envoyer la demande',
            'success' => 'Votre demande a bien été reçue. Nous vous contacterons dès que possible.',
            'errors_title' => 'Veuillez vérifier les informations saisies.',
        ],

        'en' => [
            'badge' => 'Smart technical request',
            'service_step' => '1. Choose your service',
            'type_step' => '2. Request type',
            'description_step' => '3. Issue or project',
            'technical_step' => '4. Technical details',
            'contact_step' => '5. Contact details',
            'summary_step' => '6. Summary',

            'request_types' => [
                ['value' => 'repair', 'label' => 'Repair'],
                ['value' => 'maintenance', 'label' => 'Maintenance'],
                ['value' => 'installation', 'label' => 'Installation'],
                ['value' => 'new_project', 'label' => 'New project'],
            ],

            'description' => 'Briefly describe your issue or project',
            'photos' => 'Add photos',
            'photos_help' => 'Photo upload will be added later. For now, the customer can describe the issue.',

            'brand' => 'Brand',
            'model' => 'Model',
            'serial' => 'Serial number',
            'unknown' => 'I don’t know',

            'name' => 'Name',
            'email' => 'Email address',
            'phone' => 'Phone number',

            'estimate_title' => 'Estimate possible after complete information',
            'estimate_text' => 'Based on the selected service, technical details and photos, ' . $siteName . ' can estimate what is needed faster and provide an estimate or clear next step when possible.',
            'summary_title' => 'Summary',
            'summary_text' => 'The request will be stored so it can later be followed up in an admin panel.',
            'button' => 'Send request',
            'success' => 'Your request has been received. We will contact you as soon as possible.',
            'errors_title' => 'Please check the entered information.',
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
        @if (session('success') === 'request_created')
            <div class="form-success">
                {{ $text['success'] }}
            </div>
        @endif

        @if ($errors->any())
            <div class="form-error-list">
                <strong>{{ $text['errors_title'] }}</strong>

                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('customer-requests.store', ['locale' => $locale]) }}">
            @csrf

            <div class="request-layout">
                <aside class="request-steps">
                    <div class="request-step is-active">{{ $text['service_step'] }}</div>
                    <div class="request-step">{{ $text['type_step'] }}</div>
                    <div class="request-step">{{ $text['description_step'] }}</div>
                    <div class="request-step">{{ $text['technical_step'] }}</div>
                    <div class="request-step">{{ $text['contact_step'] }}</div>
                    <div class="request-step">{{ $text['summary_step'] }}</div>
                </aside>

                <div class="request-form-card">
                    <div class="form-section" data-step="0">
                        <h2>{{ $text['service_step'] }}</h2>

                        <div class="option-grid">
                            @foreach ($services as $index => $service)
                                <label class="option-card {{ old('service_slug', $services[0]['slug'] ?? '') === $service['slug'] ? 'is-selected' : '' }}">
                                    <input
                                        type="radio"
                                        name="service_slug"
                                        value="{{ $service['slug'] }}"
                                        {{ old('service_slug', $services[0]['slug'] ?? '') === $service['slug'] ? 'checked' : '' }}
                                    >
                                    <span>{{ $service['title'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="form-section" data-step="1">
                        <h2>{{ $text['type_step'] }}</h2>

                        <div class="option-grid option-grid-small">
                            @foreach ($text['request_types'] as $type)
                                <label class="option-card {{ old('request_type', $text['request_types'][0]['value']) === $type['value'] ? 'is-selected' : '' }}">
                                    <input
                                        type="radio"
                                        name="request_type"
                                        value="{{ $type['value'] }}"
                                        {{ old('request_type', $text['request_types'][0]['value']) === $type['value'] ? 'checked' : '' }}
                                    >
                                    <span>{{ $type['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="form-section" data-step="2">
                        <h2>{{ $text['description_step'] }}</h2>

                        <label>
                            <span>{{ $text['description'] }}</span>
                            <textarea name="description" rows="5" placeholder="...">{{ old('description') }}</textarea>
                        </label>

                        <div class="upload-box">
                            <strong>{{ $text['photos'] }}</strong>
                            <p>{{ $text['photos_help'] }}</p>
                        </div>
                    </div>

                    <div class="form-section" data-step="3">
                        <h2>{{ $text['technical_step'] }}</h2>

                        <div class="field-grid">
                            <label>
                                <span>{{ $text['brand'] }}</span>
                                <input type="text" name="brand" value="{{ old('brand') }}" placeholder="Vaillant, Daikin, Bosch...">
                            </label>

                            <label>
                                <span>{{ $text['model'] }}</span>
                                <input type="text" name="device_model" value="{{ old('device_model') }}" placeholder="ecoTEC plus, Altherma...">
                            </label>

                            <label>
                                <span>{{ $text['serial'] }}</span>
                                <input type="text" name="serial_number" value="{{ old('serial_number') }}" placeholder="SN / serial...">
                            </label>

                            <label class="checkbox-field">
                                <input type="checkbox" name="unknown_device_details" value="1" {{ old('unknown_device_details') ? 'checked' : '' }}>
                                <span>{{ $text['unknown'] }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-section" data-step="4">
                        <h2>{{ $text['contact_step'] }}</h2>

                        <div class="field-grid">
                            <label>
                                <span>{{ $text['name'] }}</span>
                                <input type="text" name="customer_name" value="{{ old('customer_name') }}">
                            </label>

                            <label>
                                <span>{{ $text['email'] }}</span>
                                <input type="email" name="customer_email" value="{{ old('customer_email') }}">
                            </label>

                            <label>
                                <span>{{ $text['phone'] }}</span>
                                <input type="tel" name="customer_phone" value="{{ old('customer_phone') }}">
                            </label>
                        </div>
                    </div>

                    <div class="estimate-box" data-step="5">
                        <h3>{{ $text['estimate_title'] }}</h3>
                        <p>{{ $text['estimate_text'] }}</p>
                    </div>

                    <div class="summary-box" data-step="5">
                        <h3>{{ $text['summary_title'] }}</h3>
                        <p>{{ $text['summary_text'] }}</p>
                    </div>

                    <div class="button-row">
                        <button class="button button-primary button-large" type="submit">
                            {{ $text['button'] }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>