@php
    $siteName = config('site.name');

    $steps = config('request-flow.steps', []);

    $getLabel = function (array $item) use ($locale): string {
        return $item['labels'][$locale]
            ?? $item['labels']['nl']
            ?? $item['label'][$locale]
            ?? $item['label']['nl']
            ?? $item['title']
            ?? $item['value']
            ?? '';
    };

    $getPlaceholder = function (array $field) use ($locale): string {
        return $field['placeholder'][$locale]
            ?? $field['placeholder']['nl']
            ?? '';
    };

    $isRequiredField = function (array $field): bool {
        return $field['required'] ?? false;
    };

    $getConditionCategories = function (array $step): array {
        return $step['condition']['service_categories'] ?? [];
    };

    $stepMatchesOldInput = function (array $step) use ($getConditionCategories): bool {
        $allowedCategories = $getConditionCategories($step);
        if (empty($allowedCategories)) {
            return true; // no condition = always visible
        }
        $selectedCategory = old('service_category', '');
        return in_array($selectedCategory, $allowedCategories, true);
    };

    // Privacy policy link slug per locale (mirrors layouts/app.blade.php)
    $privacySlugs = [
        'nl' => 'privacybeleid',
        'fr' => 'politique-confidentialite',
        'en' => 'privacy-policy',
    ];
    $privacySlug = $privacySlugs[$locale] ?? $privacySlugs['nl'];

    // Serial number help text per locale
    $serialHelpText = [
        'nl' => 'U vindt het serienummer meestal op het typeplaatje van het toestel. Dit zit vaak aan de zijkant, onderkant, binnenkant van een klep of op een sticker.',
        'fr' => 'Le numéro de série se trouve généralement sur la plaque signalétique de l\'appareil, souvent sur le côté, en dessous, derrière un clapet ou sur une étiquette.',
        'en' => 'The serial number is usually on the appliance rating plate, often on the side, underneath, behind a flap or on a sticker.',
    ];

    // Early photo upload labels for technical_details step
    $techUploadLabel = [
        'nl' => 'Foto van toestel of typeplaatje toevoegen',
        'fr' => 'Ajouter une photo de l\'appareil ou de la plaque signalétique',
        'en' => 'Add a photo of the appliance or rating plate',
    ];
    $techUploadText = [
        'nl' => 'Weet u het merk of serienummer niet? Upload een foto van het typeplaatje — dat vinden wij zelf op.',
        'fr' => 'Vous ne connaissez pas la marque ou le numéro de série ? Téléchargez une photo de la plaque signalétique — nous le trouverons nous-mêmes.',
        'en' => 'Don\'t know the brand or serial number? Upload a photo of the rating plate — we\'ll look it up ourselves.',
    ];

    $labels = [
        'nl' => [
            'hero_badge'     => 'Slimme aanvraag',
            'hero_title'     => 'Start je aanvraag',
            'hero_intro'     => 'Vul de belangrijkste informatie in. Zo kan je aanvraag sneller en duidelijker opgevolgd worden.',
            'verder'         => 'Verder',
            'terug'          => 'Terug',
            'submit'         => 'Verstuur mijn aanvraag',
            'submit_offerte' => 'Vraag mijn offerte aan',
            'step_indicator' => 'Stap {1} van {2}',
            'success_title'       => 'Je aanvraag werd verzonden.',
            'success_text'        => 'We hebben je aanvraag goed ontvangen en nemen zo snel mogelijk contact op.',
            'success_steps_title' => 'Wat gebeurt er nu?',
            'success_step_1'      => 'Je aanvraag is veilig ontvangen en opgeslagen.',
            'success_step_2'      => 'Mastechnics neemt zo snel mogelijk contact op via telefoon of e-mail.',
            'success_step_3'      => 'Heb je een dringend probleem? Bel ons of stuur een WhatsApp-bericht.',
            'success_cta_home'    => 'Terug naar de startpagina',
            'success_cta_new'     => 'Nieuwe aanvraag indienen',
            'error_title'    => 'Controleer de ingevulde informatie.',
            'estimate_title' => 'Richtprijs mogelijk na volledige info',
            'estimate_text'  => 'Op basis van de gekozen dienst, technische gegevens en foto\'s kan ' . $siteName . ' sneller inschatten wat nodig is en indien mogelijk een richtprijs of duidelijke vervolgstap voorstellen.',
            'summary_title'  => 'Samenvatting',
            'summary_text'   => 'De aanvraag wordt opgeslagen zodat ze later opgevolgd kan worden in het admin panel.',
            'choose_option'  => 'Kies een optie',
            'privacy_notice' => 'Uw gegevens en eventuele foto\'s worden enkel gebruikt voor de opvolging van uw aanvraag en worden niet gedeeld met derden.',
            'choose_files'   => 'Bestanden kiezen',
            'yes'                   => 'Ja',
            'no'                    => 'Nee',
            'room_label'            => 'Kamer',
            'room_add'              => 'Kamer toevoegen',
            'room_remove'           => 'Verwijder kamer',
            'room_type_label'       => 'Type ruimte',
            'room_width_label'      => 'Breedte (m)',
            'room_length_label'     => 'Lengte (m)',
            'room_surface_label'    => 'Oppervlakte (m²)',
            'room_attic_label'      => 'Zolderkamer of onder plat dak?',
            'room_windows_label'    => 'Veel grote ramen?',
            'unknown_device_helper'   => 'Geen probleem, dan bekijken we dit op basis van uw beschrijving en foto\'s.',
            'brand_model_error'       => 'Vul merk en model in, of vink aan dat u dit niet weet.',
            'summary_category'        => 'Gekozen dienst',
            'summary_rooms'           => 'Kamers',
            'summary_room'            => 'Kamer',
            'summary_dimensions'      => 'Afmetingen',
            'summary_surface'         => 'Oppervlakte',
            'summary_attic'           => 'Zolder/plat dak',
            'summary_large_windows'   => 'Grote ramen',
            'summary_house_age'       => 'Woning ouder dan 10 jaar',
            'summary_outdoor_unit'    => 'Buitenunit aanwezig',
            'summary_timing'          => 'Gewenste termijn',
            'summary_timing_notes'    => 'Toelichting',
            'summary_customer'        => 'Klant en urgentie',
            'summary_problem'         => 'Situatie / probleem',
            'summary_technical'       => 'Technische gegevens',
            'summary_unknown_device'  => 'Merk/model/serienummer niet gekend',
            'summary_location'        => 'Locatie',
            'summary_contact'         => 'Contactgegevens',
            'summary_uploads'         => 'Bijlagen',
            'summary_empty'           => '(niet ingevuld)',
            'serial_help_aria'        => 'Waar vind ik het serienummer?',
            'consent_intro'           => 'Ik ga akkoord dat mijn gegevens en eventuele foto\'s gebruikt worden om mijn aanvraag te behandelen.',
            'consent_pre'             => 'Ik heb de ',
            'consent_link'            => 'privacyverklaring',
            'consent_post'            => ' gelezen.',
        ],
        'fr' => [
            'hero_badge'     => 'Demande intelligente',
            'hero_title'     => 'Démarrer votre demande',
            'hero_intro'     => 'Remplissez les informations les plus importantes afin que votre demande puisse être suivie plus rapidement et plus clairement.',
            'verder'         => 'Suivant',
            'terug'          => 'Retour',
            'submit'         => 'Envoyer ma demande',
            'submit_offerte' => 'Demander mon devis',
            'step_indicator' => 'Étape {1} sur {2}',
            'success_title'       => 'Votre demande a été envoyée.',
            'success_text'        => 'Nous avons bien reçu votre demande et nous vous contacterons dès que possible.',
            'success_steps_title' => 'Que se passe-t-il maintenant ?',
            'success_step_1'      => 'Votre demande a été reçue et enregistrée en toute sécurité.',
            'success_step_2'      => 'Mastechnics vous contactera dès que possible par téléphone ou e-mail.',
            'success_step_3'      => 'Vous avez un problème urgent ? Appelez-nous ou envoyez un message WhatsApp.',
            'success_cta_home'    => 'Retour à la page d\'accueil',
            'success_cta_new'     => 'Soumettre une nouvelle demande',
            'error_title'    => 'Veuillez vérifier les informations saisies.',
            'estimate_title' => 'Estimation possible après informations complètes',
            'estimate_text'  => 'Sur la base du service choisi, des informations techniques et des photos, ' . $siteName . ' peut évaluer plus rapidement la situation et proposer une estimation ou une prochaine étape claire si possible.',
            'summary_title'  => 'Résumé',
            'summary_text'   => 'La demande sera enregistrée afin de pouvoir être suivie plus tard dans le panneau d\'administration.',
            'choose_option'  => 'Choisissez une option',
            'privacy_notice' => 'Vos données et éventuelles photos sont utilisées uniquement pour le traitement de votre demande et ne sont pas partagées avec des tiers.',
            'choose_files'   => 'Choisir des fichiers',
            'yes'                   => 'Oui',
            'no'                    => 'Non',
            'room_label'            => 'Pièce',
            'room_add'              => 'Ajouter une pièce',
            'room_remove'           => 'Supprimer la pièce',
            'room_type_label'       => 'Type de pièce',
            'room_width_label'      => 'Largeur (m)',
            'room_length_label'     => 'Longueur (m)',
            'room_surface_label'    => 'Surface (m²)',
            'room_attic_label'      => 'Chambre mansardée ou sous toit plat ?',
            'room_windows_label'    => 'Beaucoup de grandes fenêtres ?',
            'unknown_device_helper'   => 'Pas de problème, nous l\'évaluerons sur base de votre description et de vos photos.',
            'brand_model_error'       => 'Indiquez la marque et le modèle, ou cochez que vous ne les connaissez pas.',
            'summary_category'        => 'Service choisi',
            'summary_rooms'           => 'Pièces',
            'summary_room'            => 'Pièce',
            'summary_dimensions'      => 'Dimensions',
            'summary_surface'         => 'Surface',
            'summary_attic'           => 'Mansardée/toit plat',
            'summary_large_windows'   => 'Grandes fenêtres',
            'summary_house_age'       => 'Logement de plus de 10 ans',
            'summary_outdoor_unit'    => 'Unité extérieure présente',
            'summary_timing'          => 'Délai souhaité',
            'summary_timing_notes'    => 'Précision',
            'summary_customer'        => 'Client et urgence',
            'summary_problem'         => 'Situation / problème',
            'summary_technical'       => 'Informations techniques',
            'summary_unknown_device'  => 'Marque/modèle/numéro de série inconnus',
            'summary_location'        => 'Lieu',
            'summary_contact'         => 'Coordonnées',
            'summary_uploads'         => 'Pièces jointes',
            'summary_empty'           => '(non renseigné)',
            'serial_help_aria'        => 'Où trouver le numéro de série ?',
            'consent_intro'           => 'J\'accepte que mes données et éventuelles photos soient utilisées pour traiter ma demande.',
            'consent_pre'             => 'J\'ai lu la ',
            'consent_link'            => 'déclaration de confidentialité',
            'consent_post'            => '.',
        ],
        'en' => [
            'hero_badge'     => 'Smart request',
            'hero_title'     => 'Start your request',
            'hero_intro'     => 'Fill in the most important information so your request can be followed up faster and more clearly.',
            'verder'         => 'Next',
            'terug'          => 'Back',
            'submit'         => 'Send my request',
            'submit_offerte' => 'Request my quote',
            'step_indicator' => 'Step {1} of {2}',
            'success_title'       => 'Your request has been sent.',
            'success_text'        => 'We have received your request and will contact you as soon as possible.',
            'success_steps_title' => 'What happens next?',
            'success_step_1'      => 'Your request has been safely received and saved.',
            'success_step_2'      => 'Mastechnics will contact you as soon as possible by phone or email.',
            'success_step_3'      => 'Do you have an urgent problem? Call us or send a WhatsApp message.',
            'success_cta_home'    => 'Back to the homepage',
            'success_cta_new'     => 'Submit a new request',
            'error_title'    => 'Please check the entered information.',
            'estimate_title' => 'Estimate possible after complete information',
            'estimate_text'  => 'Based on the selected service, technical details and photos, ' . $siteName . ' can estimate what is needed faster and provide an estimate or clear next step when possible.',
            'summary_title'  => 'Summary',
            'summary_text'   => 'The request will be stored so it can later be followed up in an admin panel.',
            'choose_option'  => 'Choose an option',
            'privacy_notice' => 'Your data and any photos are used solely to process your request and are not shared with third parties.',
            'choose_files'   => 'Choose files',
            'yes'                   => 'Yes',
            'no'                    => 'No',
            'room_label'            => 'Room',
            'room_add'              => 'Add room',
            'room_remove'           => 'Remove room',
            'room_type_label'       => 'Room type',
            'room_width_label'      => 'Width (m)',
            'room_length_label'     => 'Length (m)',
            'room_surface_label'    => 'Area (m²)',
            'room_attic_label'      => 'Attic or flat roof?',
            'room_windows_label'    => 'Many large windows?',
            'unknown_device_helper'   => 'No problem, we will review it based on your description and photos.',
            'brand_model_error'       => 'Enter the brand and model, or check that you don\'t know them.',
            'summary_category'        => 'Chosen service',
            'summary_rooms'           => 'Rooms',
            'summary_room'            => 'Room',
            'summary_dimensions'      => 'Dimensions',
            'summary_surface'         => 'Area',
            'summary_attic'           => 'Attic/flat roof',
            'summary_large_windows'   => 'Large windows',
            'summary_house_age'       => 'Property older than 10 years',
            'summary_outdoor_unit'    => 'Outdoor unit present',
            'summary_timing'          => 'Desired timeframe',
            'summary_timing_notes'    => 'Details',
            'summary_customer'        => 'Customer and urgency',
            'summary_problem'         => 'Situation / problem',
            'summary_technical'       => 'Technical details',
            'summary_unknown_device'  => 'Brand/model/serial number unknown',
            'summary_location'        => 'Location',
            'summary_contact'         => 'Contact details',
            'summary_uploads'         => 'Attachments',
            'summary_empty'           => '(not filled in)',
            'serial_help_aria'        => 'Where can I find the serial number?',
            'consent_intro'           => 'I agree that my details and any photos may be used to process my request.',
            'consent_pre'             => 'I have read the ',
            'consent_link'            => 'privacy policy',
            'consent_post'            => '.',
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];

    $hasErrors       = $errors->any();
    $errorFieldNames = $hasErrors ? $errors->keys() : [];
@endphp

<div class="request-page-wrapper">
    <section class="request-hero">
        <div class="container">
            <span class="eyebrow">{{ $text['hero_badge'] }}</span>

            <h1>{{ $translation->title ?: $text['hero_title'] }}</h1>

            @if ($translation->intro)
                <p class="service-intro">{{ $translation->intro }}</p>
            @else
                <p class="service-intro">{{ $text['hero_intro'] }}</p>
            @endif
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @if (session('success') === 'request_created')
            <style>
            .form-success-expanded { text-align: left; }
            .form-success-icon { font-size: 2rem; margin-bottom: 0.5rem; color: #059669; }
            .form-success-title { font-size: 1.25rem; display: block; margin-bottom: 0.5rem; }
            .form-success-steps { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #bbf7d0; }
            .form-success-steps strong { display: block; margin-bottom: 0.5rem; }
            .form-success-steps ol { margin: 0; padding-left: 1.25rem; }
            .form-success-steps li { margin-bottom: 0.35rem; }
            .form-success-ctas { margin-top: 1.25rem; display: flex; flex-wrap: wrap; gap: 0.75rem; }
            </style>
                <div class="form-success form-success-expanded">
                    <div class="form-success-icon">&#10003;</div>
                    <strong class="form-success-title">{{ $text['success_title'] }}</strong>
                    <p class="form-success-text">{{ $text['success_text'] }}</p>

                    <div class="form-success-steps">
                        <strong>{{ $text['success_steps_title'] }}</strong>
                        <ol>
                            <li>{{ $text['success_step_1'] }}</li>
                            <li>{{ $text['success_step_2'] }}</li>
                            <li>{{ $text['success_step_3'] }}</li>
                        </ol>
                    </div>

                    <div class="form-success-ctas">
                        <a class="button button-primary" href="{{ route('pages.home', ['locale' => $locale]) }}">
                            {{ $text['success_cta_home'] }}
                        </a>
                        <a class="button button-secondary" href="{{ route('pages.show', ['locale' => $locale, 'slug' => $translation->slug]) }}">
                            {{ $text['success_cta_new'] }}
                        </a>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="form-error-list">
                    <strong>{{ $text['error_title'] }}</strong>

                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('customer-requests.store', ['locale' => $locale]) }}"
                enctype="multipart/form-data"
                novalidate
            >
                @csrf

                <div class="request-layout">
                    <div class="request-form-area">
                        {{-- Top progress area --}}
                        <div class="wizard-progress-area" id="wizardProgressArea" aria-hidden="true">
                            <div class="wizard-progress-meta">
                                <span class="wizard-step-count" id="wizardStepCount" data-template="{{ $text['step_indicator'] }}"></span>
                            </div>
                            <div class="wizard-progress-wrap" id="wizardProgressWrap">
                                <div class="wizard-progress-fill" id="wizardProgressFill"></div>
                            </div>
                        </div>

                        <div class="wizard-nav-bar wizard-nav-bar--top" id="wizardNavBarTop">
                            <button type="button" id="wizardTerugTop" class="button button-ghost wizard-nav-back is-wizard-hidden">
                                &larr; {{ $text['terug'] }}
                            </button>

                            <div class="wizard-nav-forward">
                                <button type="button" id="wizardVerderTop" class="button button-primary">
                                    {{ $text['verder'] }} &rarr;
                                </button>
                            </div>
                        </div>

                        <div class="request-form-card" id="requestFormCard">
                            @foreach ($steps as $stepIndex => $step)
                                @php
                                    $conditionCategories = $getConditionCategories($step);
                                    $isVisibleByDefault  = $stepMatchesOldInput($step);
                                    $stepType = $step['type'] ?? '';
                                    if ($stepType === 'service_category_selection') {
                                        $sectionFields = 'service_category';
                                    } elseif ($stepType === 'summary') {
                                        $sectionFields = 'privacy_consent';
                                    } else {
                                        $sectionFields = collect($step['fields'] ?? [])
                                            ->pluck('name')
                                            ->filter()
                                            ->prepend($stepType === 'airco_rooms' ? 'rooms' : null)
                                            ->filter()
                                            ->unique()
                                            ->implode(',');
                                    }
                                @endphp

                                <section
                                    class="form-section {{ $isVisibleByDefault ? '' : 'is-condition-hidden' }}"
                                    data-step="{{ $stepIndex }}"
                                    data-step-code="{{ $step['code'] ?? '' }}"
                                    data-fields="{{ $sectionFields }}"
                                    @if (!empty($conditionCategories))
                                        data-condition-service-categories="{{ implode(',', $conditionCategories) }}"
                                    @endif
                                >
                                    @if (($step['type'] ?? '') !== 'summary')
                                        <h2>{{ $getLabel($step) }}</h2>
                                    @endif

                                    @if (($step['type'] ?? '') === 'service_category_selection')
                                        @if (isset($step['helper_text']))
                                            <p class="step-helper-text">{{ $step['helper_text'][$locale] ?? $step['helper_text']['nl'] }}</p>
                                        @endif

                                        <div class="option-grid">
                                            @foreach ($step['options'] ?? [] as $option)
                                                <label class="option-card {{ old('service_category', '') === $option['value'] ? 'is-selected' : '' }}">
                                                    <input
                                                        type="radio"
                                                        name="service_category"
                                                        value="{{ $option['value'] }}"
                                                        {{ old('service_category', '') === $option['value'] ? 'checked' : '' }}
                                                    >
                                                    <span class="option-card-label">{{ $getLabel($option) }}</span>
                                                    @if (isset($option['description']))
                                                        <span class="option-card-desc">{{ $option['description'][$locale] ?? $option['description']['nl'] }}</span>
                                                    @endif
                                                </label>
                                            @endforeach
                                        </div>

                                        @error('service_category')
                                            <p class="field-error-text">{{ $message }}</p>
                                        @enderror
                                    @elseif (($step['type'] ?? '') === 'airco_rooms')
                                        @php
                                            $roomTypes = $step['room_types'] ?? [];
                                            $oldRooms  = old('rooms', [
                                                ['type' => '', 'width' => '', 'length' => '', 'surface' => '', 'attic_or_flat_roof' => '', 'large_windows' => ''],
                                            ]);
                                            $yesNoOptions = [
                                                ['value' => 'yes', 'label' => $text['yes'] ?? 'Ja'],
                                                ['value' => 'no',  'label' => $text['no']  ?? 'Nee'],
                                            ];
                                        @endphp

                                        <div class="room-manager" id="roomManager"
                                             data-room-label="{{ $text['room_label'] }}"
                                             data-room-add-label="{{ $text['room_add'] }}"
                                             data-room-remove-label="{{ $text['room_remove'] }}">

                                            @foreach ($oldRooms as $ri => $room)
                                                <div class="room-entry" data-room-index="{{ $ri }}">
                                                    <div class="room-entry-header">
                                                        <h4 class="room-entry-title">{{ $text['room_label'] }} {{ $ri + 1 }}</h4>
                                                        <button type="button" class="room-remove-btn button button-ghost button-small"
                                                            style="{{ $ri === 0 ? 'display:none' : '' }}">{{ $text['room_remove'] }}</button>
                                                    </div>
                                                    <div class="form-grid room-fields">
                                                        <label>
                                                            <span>{{ $text['room_type_label'] }}</span>
                                                            <select name="rooms[{{ $ri }}][type]" data-required="true">
                                                                <option value="">{{ $text['choose_option'] }}</option>
                                                                @foreach ($roomTypes as $rt)
                                                                    <option value="{{ $rt['value'] }}"
                                                                        {{ ($room['type'] ?? '') === $rt['value'] ? 'selected' : '' }}>
                                                                        {{ $rt['labels'][$locale] ?? $rt['labels']['nl'] }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        <label>
                                                            <span>{{ $text['room_width_label'] }}</span>
                                                            <input type="number" name="rooms[{{ $ri }}][width]"
                                                                   value="{{ $room['width'] ?? '' }}"
                                                                   min="1" max="50" step="0.1"
                                                                   class="room-dim-input room-width"
                                                                   data-required="true">
                                                        </label>
                                                        <label>
                                                            <span>{{ $text['room_length_label'] }}</span>
                                                            <input type="number" name="rooms[{{ $ri }}][length]"
                                                                   value="{{ $room['length'] ?? '' }}"
                                                                   min="1" max="50" step="0.1"
                                                                   class="room-dim-input room-length"
                                                                   data-required="true">
                                                        </label>
                                                        <label>
                                                            <span>{{ $text['room_surface_label'] }}</span>
                                                            <input type="number" name="rooms[{{ $ri }}][surface]"
                                                                   value="{{ ($room['width'] ?? '') !== '' && ($room['length'] ?? '') !== '' ? round((float)$room['width'] * (float)$room['length'], 1) : '' }}"
                                                                   readonly class="room-surface">
                                                        </label>
                                                        <label>
                                                            <span>{{ $text['room_attic_label'] }}</span>
                                                            <select name="rooms[{{ $ri }}][attic_or_flat_roof]" data-required="true">
                                                                <option value="">{{ $text['choose_option'] }}</option>
                                                                @foreach ($yesNoOptions as $opt)
                                                                    <option value="{{ $opt['value'] }}"
                                                                        {{ ($room['attic_or_flat_roof'] ?? '') === $opt['value'] ? 'selected' : '' }}>
                                                                        {{ $opt['label'] }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        <label>
                                                            <span>{{ $text['room_windows_label'] }}</span>
                                                            <select name="rooms[{{ $ri }}][large_windows]" data-required="true">
                                                                <option value="">{{ $text['choose_option'] }}</option>
                                                                @foreach ($yesNoOptions as $opt)
                                                                    <option value="{{ $opt['value'] }}"
                                                                        {{ ($room['large_windows'] ?? '') === $opt['value'] ? 'selected' : '' }}>
                                                                        {{ $opt['label'] }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                    </div>
                                                </div>
                                            @endforeach

                                            <button type="button" id="addRoomBtn" class="button button-ghost room-add-btn">
                                                + {{ $text['room_add'] }}
                                            </button>
                                        </div>

                                        {{-- Regular fields for this step: outdoor unit, house age, timing --}}
                                        @if (!empty($step['fields']))
                                            <div class="form-grid" style="margin-top: 24px;">
                                                @foreach ($step['fields'] as $field)
                                                    <label class="{{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
                                                        <span>
                                                            {{ $getLabel($field) }}
                                                            @if ($isRequiredField($field))
                                                                <span class="required-star">*</span>
                                                            @endif
                                                        </span>
                                                        @if (($field['type'] ?? '') === 'select')
                                                            <select name="{{ $field['name'] }}" @if ($isRequiredField($field)) data-required="true" @endif>
                                                                <option value="">{{ $text['choose_option'] }}</option>
                                                                @foreach ($field['options'] ?? [] as $option)
                                                                    <option value="{{ $option['value'] }}"
                                                                        {{ old($field['name']) === $option['value'] ? 'selected' : '' }}>
                                                                        {{ $getLabel($option) }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        @elseif (($field['type'] ?? '') === 'textarea')
                                                            <textarea
                                                                name="{{ $field['name'] }}"
                                                                placeholder="{{ $getPlaceholder($field) }}"
                                                                @if ($isRequiredField($field)) data-required="true" @endif
                                                                style="grid-column: 1 / -1"
                                                            >{{ old($field['name']) }}</textarea>
                                                        @else
                                                            <input type="{{ $field['type'] ?? 'text' }}"
                                                                   name="{{ $field['name'] }}"
                                                                   value="{{ old($field['name']) }}"
                                                                   placeholder="{{ $getPlaceholder($field) }}"
                                                                   @if ($isRequiredField($field)) data-required="true" @endif>
                                                        @endif
                                                        @error($field['name'])
                                                            <p class="field-error-text">{{ $message }}</p>
                                                        @enderror
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endif

                                        @if (isset($step['helper_box']))
                                            <div class="upload-box upload-box--info-only" style="margin-top: 18px;">
                                                <strong>{{ $step['helper_box']['title'][$locale] ?? $step['helper_box']['title']['nl'] }}</strong>
                                                <p>{{ $step['helper_box']['text'][$locale] ?? $step['helper_box']['text']['nl'] }}</p>
                                            </div>
                                        @endif

                                    @elseif (($step['type'] ?? '') === 'fields')
                                        @if (isset($step['urgent_warning']))
                                            <div class="urgent-warning-box">
                                                {{ $step['urgent_warning'][$locale] ?? $step['urgent_warning']['nl'] }}
                                            </div>
                                        @endif

                                        <div class="form-grid">
                                            @foreach ($step['fields'] ?? [] as $field)
                                                @if (($field['type'] ?? '') === 'checkbox')
                                                    <label class="{{ $field['name'] === 'unknown_device_details' ? 'checkbox-helper-card' : 'checkbox-field' }} {{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
                                                        <input
                                                            type="checkbox"
                                                            name="{{ $field['name'] }}"
                                                            value="1"
                                                            {{ old($field['name']) ? 'checked' : '' }}
                                                        >

                                                        <span class="{{ $field['name'] === 'unknown_device_details' ? 'checkbox-helper-card-text' : '' }}">
                                                            {{ $getLabel($field) }}
                                                            @if ($field['name'] === 'unknown_device_details')
                                                                <small class="checkbox-helper-hint">{{ $text['unknown_device_helper'] }}</small>
                                                            @endif
                                                        </span>

                                                        @error($field['name'])
                                                            <p class="field-error-text">{{ $message }}</p>
                                                        @enderror
                                                    </label>
                                                @elseif (($field['type'] ?? '') === 'textarea')
                                                    <label class="field-full {{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
                                                        <span>
                                                            {{ $getLabel($field) }}

                                                            @if ($isRequiredField($field))
                                                                <span class="required-star">*</span>
                                                            @endif
                                                        </span>

                                                        <textarea
                                                            name="{{ $field['name'] }}"
                                                            placeholder="{{ $getPlaceholder($field) }}"
                                                            @if ($isRequiredField($field)) data-required="true" @endif
                                                        >{{ old($field['name']) }}</textarea>

                                                        @error($field['name'])
                                                            <p class="field-error-text">{{ $message }}</p>
                                                        @enderror
                                                    </label>
                                                @elseif (($field['type'] ?? '') === 'select')
                                                    <label class="{{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
                                                        <span>
                                                            {{ $getLabel($field) }}

                                                            @if ($isRequiredField($field))
                                                                <span class="required-star">*</span>
                                                            @endif
                                                        </span>

                                                        <select name="{{ $field['name'] }}" @if ($isRequiredField($field)) data-required="true" @endif>
                                                            <option value="">{{ $text['choose_option'] }}</option>

                                                            @foreach ($field['options'] ?? [] as $option)
                                                                <option value="{{ $option['value'] }}" {{ old($field['name']) === $option['value'] ? 'selected' : '' }}>
                                                                    {{ $getLabel($option) }}
                                                                </option>
                                                            @endforeach
                                                        </select>

                                                        @error($field['name'])
                                                            <p class="field-error-text">{{ $message }}</p>
                                                        @enderror
                                                    </label>
                                                @else
                                                    <label class="{{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
                                                        <span>
                                                            {{ $getLabel($field) }}
                                                            @if ($isRequiredField($field))
                                                                <span class="required-star">*</span>
                                                            @endif
                                                            @if ($field['name'] === 'serial_number')
                                                                <button
                                                                    type="button"
                                                                    class="serial-help-btn"
                                                                    aria-label="{{ $text['serial_help_aria'] }}"
                                                                    aria-expanded="false"
                                                                    aria-controls="serialHelpBox"
                                                                >?</button>
                                                            @endif
                                                        </span>

                                                        @if ($field['name'] === 'serial_number')
                                                            <div class="serial-help-box" id="serialHelpBox" hidden>
                                                                {{ $serialHelpText[$locale] ?? $serialHelpText['nl'] }}
                                                            </div>
                                                        @endif

                                                        <input
                                                            type="{{ $field['type'] ?? 'text' }}"
                                                            name="{{ $field['name'] }}"
                                                            value="{{ old($field['name']) }}"
                                                            placeholder="{{ $getPlaceholder($field) }}"
                                                            @if ($isRequiredField($field)) data-required="true" @endif
                                                        >

                                                        @error($field['name'])
                                                            <p class="field-error-text">{{ $message }}</p>
                                                        @enderror
                                                    </label>
                                                @endif
                                            @endforeach
                                        </div>

                                        {{-- Early photo upload in technical_details step --}}
                                        @if (($step['code'] ?? '') === 'technical_details')
                                            <div class="upload-box {{ $errors->has('attachments') || $errors->has('attachments.*') ? 'field-has-error' : '' }}" style="margin-top: 20px;">
                                                <strong>{{ $techUploadLabel[$locale] ?? $techUploadLabel['nl'] }}</strong>
                                                <p>{{ $techUploadText[$locale] ?? $techUploadText['nl'] }}</p>

                                                <label class="upload-file-control">
                                                    <span>{{ $text['choose_files'] }}</span>
                                                    <input
                                                        id="techAttachmentsInput"
                                                        type="file"
                                                        name="attachments[]"
                                                        multiple
                                                        accept=".jpg,.jpeg,.png,.webp,.pdf"
                                                    >
                                                </label>

                                                <div id="techSelectedAttachments" class="selected-attachments"
                                                     data-remove-label="{{ $locale === 'fr' ? 'Supprimer' : ($locale === 'en' ? 'Remove' : 'Verwijder') }}"></div>
                                            </div>
                                        @endif

                                        @if (isset($step['helper_box']))
                                            <div class="upload-box {{ $errors->has('attachments') || $errors->has('attachments.*') ? 'field-has-error' : '' }}">
                                                <strong>
                                                    {{ $step['helper_box']['title'][$locale] ?? $step['helper_box']['title']['nl'] }}
                                                </strong>

                                                <p>
                                                    {{ $step['helper_box']['text'][$locale] ?? $step['helper_box']['text']['nl'] }}
                                                </p>

                                                @if ($step['helper_box']['render_upload'] ?? true)
                                                    <label class="upload-file-control">
                                                        <span>
                                                            {{ $text['choose_files'] }}
                                                        </span>

                                                        <input
                                                            id="attachmentsInput"
                                                            type="file"
                                                            name="attachments[]"
                                                            multiple
                                                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                                                        >
                                                    </label>

                                                    <div id="selectedAttachments" class="selected-attachments"
                                                         data-remove-label="{{ $locale === 'fr' ? 'Supprimer' : ($locale === 'en' ? 'Remove' : 'Verwijder') }}"></div>

                                                    @error('attachments')
                                                        <p class="field-error-text">{{ $message }}</p>
                                                    @enderror

                                                    @error('attachments.*')
                                                        <p class="field-error-text">{{ $message }}</p>
                                                    @enderror
                                                @endif
                                            </div>
                                        @endif
                                    @elseif (($step['type'] ?? '') === 'summary')
                                        <h2>{{ $text['summary_title'] }}</h2>

                                        <div class="summary-submit-wrap">
                                            <button type="submit" id="wizardSubmitTop"
                                                    class="button button-primary button-large"
                                                    data-label-general="{{ $text['submit'] }}"
                                                    data-label-offerte="{{ $text['submit_offerte'] }}">
                                                {{ $text['submit'] }}
                                            </button>
                                        </div>

                                        <div id="wizardSummaryContent" class="wizard-summary-content"></div>

                                        <label class="checkbox-field {{ $errors->has('privacy_consent') ? 'field-has-error' : '' }}" style="margin-top: 20px;">
                                            <input
                                                type="checkbox"
                                                name="privacy_consent"
                                                value="1"
                                                data-required="true"
                                                {{ old('privacy_consent') ? 'checked' : '' }}
                                            >

                                            <span>
                                                {{ $text['consent_intro'] }}
                                                {{ $text['consent_pre'] }}<a href="{{ route('pages.show', ['locale' => $locale, 'slug' => $privacySlug]) }}" target="_blank" rel="noopener">{{ $text['consent_link'] }}</a>{{ $text['consent_post'] }}
                                            </span>

                                            @error('privacy_consent')
                                                <p class="field-error-text">{{ $message }}</p>
                                            @enderror
                                        </label>

                                        <div class="request-summary-box" style="margin-top: 20px;">
                                            <h3>{{ $text['estimate_title'] }}</h3>
                                            <p>{{ $text['estimate_text'] }}</p>
                                        </div>
                                    @endif
                                </section>
                            @endforeach
                        </div>

                        {{-- Wizard error state: JS reads this to jump to the first error step --}}
                        <div id="wizardErrorState"
                             data-has-errors="{{ $hasErrors ? 'true' : 'false' }}"
                             data-error-fields='@json($errorFieldNames)'
                             aria-hidden="true"
                             style="display:none"></div>

                        <div class="wizard-nav-bar">
                            <button type="button" id="wizardTerug" class="button button-ghost wizard-nav-back is-wizard-hidden">
                                &larr; {{ $text['terug'] }}
                            </button>

                            <div class="wizard-nav-forward">
                                <button type="button" id="wizardVerder" class="button button-primary">
                                    {{ $text['verder'] }} &rarr;
                                </button>
                                <button type="submit" id="wizardSubmit" class="button button-primary button-large is-wizard-hidden"
                                        data-label-general="{{ $text['submit'] }}"
                                        data-label-offerte="{{ $text['submit_offerte'] }}">
                                    {{ $text['submit'] }}
                                </button>
                            </div>
                        </div>

                        <p class="form-privacy-notice">{{ $text['privacy_notice'] }}</p>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>

<script>
(function () {
    'use strict';

    // ── DOM refs ─────────────────────────────────────────────────────────────
    var formCard        = document.getElementById('requestFormCard');
    var wizardTerug     = document.getElementById('wizardTerug');
    var wizardVerder    = document.getElementById('wizardVerder');
    var wizardTerugTop  = document.getElementById('wizardTerugTop');
    var wizardVerderTop = document.getElementById('wizardVerderTop');
    var wizardSubmit    = document.getElementById('wizardSubmit');
    var wizardSubmitTop = document.getElementById('wizardSubmitTop');
    var wizardCount     = document.getElementById('wizardStepCount');
    var progressFill    = document.getElementById('wizardProgressFill');
    var progressArea    = document.getElementById('wizardProgressArea');
    var allSections     = Array.from(document.querySelectorAll('.form-section[data-step]'));
    var categoryInputs  = document.querySelectorAll('input[name="service_category"]');
    var attachInput     = document.getElementById('attachmentsInput');
    var attachList      = document.getElementById('selectedAttachments');
    var techAttachInput = document.getElementById('techAttachmentsInput');
    var techAttachList  = document.getElementById('techSelectedAttachments');

    // ── State ─────────────────────────────────────────────────────────────────
    var currentIndex    = 0;
    var visibleSections = [];

    // ── Helpers ───────────────────────────────────────────────────────────────
    function getSelectedCategory() {
        var checked = Array.from(categoryInputs).find(function (i) { return i.checked; });
        return checked ? checked.value : '';
    }

    function computeVisible(category) {
        return allSections.filter(function (section) {
            var cond = section.dataset.conditionServiceCategories || '';
            if (!cond) return true;
            var allowed = cond.split(',').map(function (v) { return v.trim(); }).filter(Boolean);
            return allowed.indexOf(category) !== -1;
        });
    }

    function updateConditionalVisibility(category) {
        Array.from(document.querySelectorAll('[data-condition-service-categories]')).forEach(function (el) {
            var cond    = el.dataset.conditionServiceCategories || '';
            var allowed = cond.split(',').map(function (v) { return v.trim(); }).filter(Boolean);
            var matches = allowed.length === 0 || allowed.indexOf(category) !== -1;
            el.classList.toggle('is-condition-hidden', !matches);

            // Disable fields in hidden conditional sections so duplicate-named
            // fields (e.g. customer_type, preferred_time) from mutually exclusive
            // flows aren't submitted alongside the visible step's values.
            el.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = !matches;
            });
        });
    }

    function showStep(sections, index) {
        currentIndex = index;

        // Wizard step visibility
        allSections.forEach(function (s) { s.classList.remove('wizard-current'); });
        if (sections[index]) { sections[index].classList.add('wizard-current'); }

        // Step count text
        var total   = sections.length;
        var current = index + 1;
        var tmpl    = (wizardCount.dataset.template || 'Stap {1} van {2}')
            .replace('{1}', current)
            .replace('{2}', total);
        wizardCount.textContent = tmpl;

        // Progress bar
        if (progressFill) {
            progressFill.style.width = (total > 1 ? (index / (total - 1)) * 100 : 100) + '%';
        }

        // Navigation buttons (top and bottom behave identically)
        var isFirst = index === 0;
        var isLast  = index === sections.length - 1;

        wizardTerug.classList.toggle('is-wizard-hidden', isFirst);
        wizardVerder.classList.toggle('is-wizard-hidden', isLast);
        wizardSubmit.classList.toggle('is-wizard-hidden', !isLast);

        if (wizardTerugTop) wizardTerugTop.classList.toggle('is-wizard-hidden', isFirst);
        if (wizardVerderTop) wizardVerderTop.classList.toggle('is-wizard-hidden', isLast);

        // Disable/enable Verder (only locked on step 0 until category chosen)
        if (isFirst) {
            var hasCat = !!getSelectedCategory();
            wizardVerder.disabled = !hasCat;
            wizardVerder.setAttribute('aria-disabled', String(!hasCat));
            if (wizardVerderTop) {
                wizardVerderTop.disabled = !hasCat;
                wizardVerderTop.setAttribute('aria-disabled', String(!hasCat));
            }
        } else {
            wizardVerder.disabled = false;
            wizardVerder.removeAttribute('aria-disabled');
            if (wizardVerderTop) {
                wizardVerderTop.disabled = false;
                wizardVerderTop.removeAttribute('aria-disabled');
            }
        }

        // Submit label (airco offerte → different CTA)
        var cat = getSelectedCategory();
        var submitLabel = (cat === 'airco_offerte')
            ? (wizardSubmit.dataset.labelOfferte || 'Vraag mijn offerte aan')
            : (wizardSubmit.dataset.labelGeneral || 'Verstuur mijn aanvraag');
        wizardSubmit.textContent = submitLabel;
        if (wizardSubmitTop) {
            wizardSubmitTop.textContent = submitLabel;
        }

        // Populate live summary when landing on the final step
        if (isLast) { updateSummary(); }

        // Scroll form into view when advancing (not on init)
        if (index > 0) {
            var scrollTarget = progressArea || formCard;
            if (scrollTarget) {
                scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    }

    // ── Category change ───────────────────────────────────────────────────────
    function onCategoryChange() {
        var category = getSelectedCategory();

        // Sync .is-selected visual class on all option cards
        document.querySelectorAll('.option-card').forEach(function (card) {
            var inp = card.querySelector('input[type="radio"]');
            card.classList.toggle('is-selected', !!(inp && inp.checked));
        });

        visibleSections = computeVisible(category);
        updateConditionalVisibility(category);
        if (currentIndex === 0) {
            if (category && visibleSections.length > 1) {
                showStep(visibleSections, 1);
            } else {
                showStep(visibleSections, 0);
            }
        }
    }

    categoryInputs.forEach(function (input) {
        input.addEventListener('change', onCategoryChange);
    });

    document.querySelectorAll('.option-card').forEach(function (card) {
        card.addEventListener('click', function () {
            var inp = card.querySelector('input[type="radio"]');
            if (inp) { inp.checked = true; }
            onCategoryChange();

            // Auto-advance from service selection once a category is chosen.
            // Fires on click/tap/Space activation only (not on arrow-key
            // radio-group navigation), so keyboard users can still explore
            // all options before committing. Verder remains as a manual
            // fallback for anyone who needs it.
            if (currentIndex === 0 && visibleSections.length > 1) {
                showStep(visibleSections, 1);
            }
        });
    });

    // ── Nav buttons ─────────────────────────────────────────────────────────
    // Top and bottom buttons share the same handlers so they always behave
    // identically.
    function goToNextStep() {
        if (currentIndex === 0) {
            if (currentIndex < visibleSections.length - 1) {
                showStep(visibleSections, currentIndex + 1);
            }
            return;
        }
        if (!validateCurrentStep()) return;
        if (currentIndex < visibleSections.length - 1) {
            showStep(visibleSections, currentIndex + 1);
        }
    }

    function goToPreviousStep() {
        if (currentIndex > 0) {
            showStep(visibleSections, currentIndex - 1);
        }
    }

    wizardVerder.addEventListener('click', goToNextStep);
    wizardTerug.addEventListener('click', goToPreviousStep);

    if (wizardVerderTop) wizardVerderTop.addEventListener('click', goToNextStep);
    if (wizardTerugTop) wizardTerugTop.addEventListener('click', goToPreviousStep);

    // Auto-clear JS field error when user corrects the field
    if (formCard) {
        formCard.addEventListener('input', function (e) {
            var field = e.target;
            if (!field.dataset || field.dataset.required !== 'true') return;
            if (!isFieldFilled(field)) return;
            var label = field.closest('label');
            if (!label) return;
            var err = label.querySelector('.js-field-error');
            if (err) err.remove();
            label.classList.remove('js-has-error', 'field-has-error');
        });
        formCard.addEventListener('change', function (e) {
            var field = e.target;
            if (field.name === 'unknown_device_details') {
                if (field.checked) {
                    var sec = field.closest('.form-section');
                    if (sec) {
                        ['input[name="brand"]', 'input[name="device_model"]'].forEach(function (sel) {
                            var inp = sec.querySelector(sel);
                            if (!inp) return;
                            var lbl = inp.closest('label');
                            if (!lbl) return;
                            var err = lbl.querySelector('.js-field-error');
                            if (err) err.remove();
                            lbl.classList.remove('js-has-error', 'field-has-error');
                        });
                    }
                }
                return;
            }
            if (!field.dataset || field.dataset.required !== 'true') return;
            if (!isFieldFilled(field)) return;
            var label = field.closest('label');
            if (!label) return;
            var err = label.querySelector('.js-field-error');
            if (err) err.remove();
            label.classList.remove('js-has-error', 'field-has-error');
        });
    }

    // ── File attachment preview (description step upload) ─────────────────────
    // SECURITY: file.name is rendered via textContent only — never innerHTML
    if (attachInput && attachList) {
        var files = [];

        attachInput.addEventListener('change', function () {
            Array.from(attachInput.files).forEach(function (file) {
                if (!files.find(function (f) { return f.name === file.name && f.size === file.size; })) {
                    files.push(file);
                }
            });
            renderAttachments();
        });

        function renderAttachments() {
            attachList.innerHTML = '';
            files.forEach(function (file, i) {
                var item = document.createElement('div');
                item.className = 'selected-attachment-item';

                var nameSpan = document.createElement('span');
                nameSpan.textContent = file.name;

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.setAttribute('aria-label', attachList.dataset.removeLabel || 'Verwijder');
                removeBtn.textContent = '×';

                removeBtn.addEventListener('click', (function (index) {
                    return function () {
                        files.splice(index, 1);
                        renderAttachments();
                    };
                })(i));

                item.appendChild(nameSpan);
                item.appendChild(removeBtn);
                attachList.appendChild(item);
            });
        }
    }

    // ── Technical photo upload preview ─────────────────────────────────────────
    if (techAttachInput && techAttachList) {
        var techFiles = [];

        techAttachInput.addEventListener('change', function () {
            Array.from(techAttachInput.files).forEach(function (file) {
                if (!techFiles.find(function (f) { return f.name === file.name && f.size === file.size; })) {
                    techFiles.push(file);
                }
            });
            renderTechAttachments();
        });

        function renderTechAttachments() {
            techAttachList.innerHTML = '';
            techFiles.forEach(function (file, i) {
                var item = document.createElement('div');
                item.className = 'selected-attachment-item';

                var nameSpan = document.createElement('span');
                nameSpan.textContent = file.name;

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.setAttribute('aria-label', techAttachList.dataset.removeLabel || 'Verwijder');
                removeBtn.textContent = '×';

                removeBtn.addEventListener('click', (function (index) {
                    return function () {
                        techFiles.splice(index, 1);
                        renderTechAttachments();
                    };
                })(i));

                item.appendChild(nameSpan);
                item.appendChild(removeBtn);
                techAttachList.appendChild(item);
            });
        }
    }

    // ── Serial number help tooltip ─────────────────────────────────────────────
    document.querySelectorAll('.serial-help-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var helpBox = document.getElementById('serialHelpBox');
            if (!helpBox) return;
            var isHidden = helpBox.hidden;
            helpBox.hidden = !isHidden;
            btn.setAttribute('aria-expanded', String(isHidden));
        });
    });

    // ── Room manager ─────────────────────────────────────────────────────────
    function initRoomManager() {
        var manager = document.getElementById('roomManager');
        if (!manager) return;

        var addBtn = document.getElementById('addRoomBtn');

        function getEntries() {
            return Array.from(manager.querySelectorAll('.room-entry'));
        }

        function calcSurface(entry) {
            var wInput = entry.querySelector('.room-width');
            var lInput = entry.querySelector('.room-length');
            var sInput = entry.querySelector('.room-surface');
            if (!wInput || !lInput || !sInput) return;
            var w = parseFloat(wInput.value.replace(',', '.')) || 0;
            var l = parseFloat(lInput.value.replace(',', '.')) || 0;
            sInput.value = (w > 0 && l > 0) ? (Math.round(w * l * 10) / 10) : '';
        }

        function renumber() {
            var roomLabel = manager.dataset.roomLabel || 'Kamer';
            var removeLabel = manager.dataset.roomRemoveLabel || 'Verwijder kamer';
            getEntries().forEach(function (entry, idx) {
                entry.dataset.roomIndex = idx;

                entry.querySelectorAll('[name]').forEach(function (el) {
                    el.name = el.name.replace(/rooms\[\d+\]/, 'rooms[' + idx + ']');
                });

                var title = entry.querySelector('.room-entry-title');
                if (title) title.textContent = roomLabel + ' ' + (idx + 1);

                var removeBtn = entry.querySelector('.room-remove-btn');
                if (removeBtn) {
                    removeBtn.style.display = idx === 0 ? 'none' : '';
                    removeBtn.textContent = removeLabel;
                }
            });
        }

        manager.addEventListener('input', function (e) {
            if (e.target.classList.contains('room-dim-input')) {
                calcSurface(e.target.closest('.room-entry'));
            }
        });

        manager.addEventListener('click', function (e) {
            var btn = e.target.closest('.room-remove-btn');
            if (!btn) return;
            var entry = btn.closest('.room-entry');
            if (!entry || getEntries().length <= 1) return;
            entry.remove();
            renumber();
        });

        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var entries = getEntries();
                var newIdx  = entries.length;

                var clone = entries[entries.length - 1].cloneNode(true);
                clone.dataset.roomIndex = newIdx;

                clone.querySelectorAll('input, select').forEach(function (el) {
                    el.value = '';
                });

                clone.querySelectorAll('[name]').forEach(function (el) {
                    el.name = el.name.replace(/rooms\[\d+\]/, 'rooms[' + newIdx + ']');
                });

                manager.insertBefore(clone, addBtn);
                renumber();

                clone.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        }

        renumber();
        getEntries().forEach(calcSurface);
    }

    // ── Validation error recovery ─────────────────────────────────────────────
    function findErrorStep(sections, errorFields) {
        if (!errorFields || errorFields.length === 0) return 0;
        for (var i = 0; i < sections.length; i++) {
            var sectionFields = (sections[i].dataset.fields || '').split(',').filter(Boolean);
            for (var f = 0; f < sectionFields.length; f++) {
                var name = sectionFields[f];
                for (var e = 0; e < errorFields.length; e++) {
                    if (errorFields[e] === name || errorFields[e].indexOf(name + '.') === 0) {
                        return i;
                    }
                }
            }
        }
        return 0;
    }

    // ── Client-side step validation ───────────────────────────────────────────
    var stepFieldErrorMsg = @json(
        $locale === 'fr' ? 'Veuillez remplir ce champ pour continuer.' :
        ($locale === 'en' ? 'Please fill in this field to continue.' :
        'Vul dit veld in om verder te gaan.')
    );

    var brandModelErrorMsg = @json($text['brand_model_error']);

    function isFieldFilled(field) {
        var tag = field.tagName.toLowerCase();
        if (tag === 'select') return field.value !== '';
        if (field.type === 'checkbox') return field.checked;
        if (field.type === 'number') {
            var n = Number(field.value);
            var min = field.min !== '' ? Number(field.min) : null;
            return field.value.trim() !== '' && (min === null || n >= min);
        }
        return field.value.trim() !== '';
    }

    function addFieldError(field, message) {
        var label = field.closest('label');
        if (!label) return;
        label.classList.add('field-has-error', 'js-has-error');
        if (!label.querySelector('.js-field-error')) {
            var msg = document.createElement('p');
            msg.className = 'field-error-text js-field-error';
            msg.textContent = message || stepFieldErrorMsg;
            label.appendChild(msg);
        }
    }

    function clearStepErrors(section) {
        section.querySelectorAll('.js-field-error').forEach(function (el) { el.remove(); });
        section.querySelectorAll('.js-has-error').forEach(function (el) {
            el.classList.remove('js-has-error', 'field-has-error');
        });
    }

    function validateCurrentStep() {
        var section = visibleSections[currentIndex];
        if (!section) return true;

        clearStepErrors(section);

        var requiredFields = Array.from(section.querySelectorAll('[data-required="true"]'));
        var firstInvalid = null;
        var valid = true;

        requiredFields.forEach(function (field) {
            if (!isFieldFilled(field)) {
                addFieldError(field);
                if (!firstInvalid) firstInvalid = field;
                valid = false;
            }
        });

        // Brand/model OR unknown check (technical_details step)
        var unknownCb = section.querySelector('input[name="unknown_device_details"]');
        if (unknownCb && !unknownCb.checked) {
            var brandInput = section.querySelector('input[name="brand"]');
            var modelInput = section.querySelector('input[name="device_model"]');
            if (brandInput && !isFieldFilled(brandInput)) {
                addFieldError(brandInput, brandModelErrorMsg);
                if (!firstInvalid) firstInvalid = brandInput;
                valid = false;
            }
            if (modelInput && !isFieldFilled(modelInput)) {
                addFieldError(modelInput, brandModelErrorMsg);
                if (!firstInvalid) firstInvalid = modelInput;
                valid = false;
            }
        }

        if (firstInvalid) {
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            try { firstInvalid.focus(); } catch (e) {}
        }

        return valid;
    }

    // ── Summary labels (PHP-rendered) ─────────────────────────────────────────
    @php
        $_sl = [
            'category'      => $text['summary_category'],
            'rooms'         => $text['summary_rooms'],
            'room'          => $text['summary_room'],
            'attic'         => $text['summary_attic'],
            'largeWindows'  => $text['summary_large_windows'],
            'houseAge'      => $text['summary_house_age'],
            'outdoorUnit'   => $text['summary_outdoor_unit'],
            'timing'        => $text['summary_timing'],
            'timingNotes'   => $text['summary_timing_notes'],
            'customer'      => $text['summary_customer'],
            'problem'       => $text['summary_problem'],
            'technical'     => $text['summary_technical'],
            'unknownDevice' => $text['summary_unknown_device'],
            'location'      => $text['summary_location'],
            'contact'       => $text['summary_contact'],
            'uploads'       => $text['summary_uploads'],
            'empty'         => $text['summary_empty'],
        ];
    @endphp
    var summaryLabels = @json($_sl);

    // ── Summary renderer ──────────────────────────────────────────────────────
    function updateSummary() {
        var container = document.getElementById('wizardSummaryContent');
        if (!container) return;

        while (container.firstChild) { container.removeChild(container.firstChild); }

        var form = container.closest('form');
        var category = getSelectedCategory();

        function qVal(name) {
            var el = form ? form.querySelector('[name="' + name + '"]') : null;
            return el ? el.value.trim() : '';
        }

        function qSelectText(name) {
            var el = form ? form.querySelector('select[name="' + name + '"]') : null;
            if (!el || !el.value) return '';
            var opt = Array.from(el.options).find(function (o) { return o.value === el.value; });
            return opt ? opt.text : '';
        }

        function qAnyVal(name) {
            var all = form ? Array.from(form.querySelectorAll('[name="' + name + '"]')) : [];
            for (var i = 0; i < all.length; i++) {
                if (all[i].value && all[i].value.trim()) return all[i].value.trim();
            }
            return '';
        }

        function addSection(titleText, items) {
            var filled = items.filter(function (it) { return it.value && it.value.trim(); });
            if (!filled.length) return;

            var sec = document.createElement('div');
            sec.className = 'summary-section';

            var h = document.createElement('h4');
            h.textContent = titleText;
            sec.appendChild(h);

            filled.forEach(function (it) {
                var row = document.createElement('p');
                row.className = 'summary-row';
                if (it.label) {
                    var lbl = document.createElement('span');
                    lbl.className = 'summary-row-label';
                    lbl.textContent = it.label + ': ';
                    row.appendChild(lbl);
                }
                var val = document.createElement('span');
                val.className = 'summary-row-value';
                val.textContent = it.value;
                row.appendChild(val);
                sec.appendChild(row);
            });

            container.appendChild(sec);
        }

        // 1. Service category
        var catRadio = form ? form.querySelector('input[name="service_category"]:checked') : null;
        if (catRadio) {
            var catCardLabel = catRadio.closest('label');
            var catSpan = catCardLabel ? catCardLabel.querySelector('.option-card-label') : null;
            var catText = catSpan ? catSpan.textContent.trim() : catRadio.value;
            addSection(summaryLabels.category, [{ value: catText }]);
        }

        // 2. Airco rooms (only for airco_offerte)
        if (category === 'airco_offerte') {
            var roomEntries = document.querySelectorAll('.room-entry');
            var roomItems = [];

            roomEntries.forEach(function (entry, i) {
                var typeEl    = entry.querySelector('select[name*="[type]"]');
                var surfaceEl = entry.querySelector('.room-surface');
                var atticEl   = entry.querySelector('select[name*="[attic_or_flat_roof]"]');
                var windowsEl = entry.querySelector('select[name*="[large_windows]"]');

                var parts = [];
                if (typeEl && typeEl.value) {
                    var tOpt = Array.from(typeEl.options).find(function (o) { return o.value === typeEl.value; });
                    if (tOpt) parts.push(tOpt.text);
                }
                if (surfaceEl && surfaceEl.value) {
                    parts.push(surfaceEl.value + ' m²');
                }
                if (atticEl && atticEl.value) {
                    var aOpt = Array.from(atticEl.options).find(function (o) { return o.value === atticEl.value; });
                    if (aOpt) parts.push(summaryLabels.attic + ': ' + aOpt.text);
                }
                if (windowsEl && windowsEl.value) {
                    var wOpt = Array.from(windowsEl.options).find(function (o) { return o.value === windowsEl.value; });
                    if (wOpt) parts.push(summaryLabels.largeWindows + ': ' + wOpt.text);
                }

                if (parts.length) {
                    roomItems.push({ label: summaryLabels.room + ' ' + (i + 1), value: parts.join(' · ') });
                }
            });

            var houseAgeText = qSelectText('airco_house_age');
            var outdoorText  = qSelectText('airco_has_outdoor_unit');
            var aircoTimingText  = qSelectText('airco_installation_timing');
            var aircoTimingNotes = qVal('airco_installation_timing_notes');

            if (houseAgeText)    roomItems.push({ label: summaryLabels.houseAge, value: houseAgeText });
            if (outdoorText)     roomItems.push({ label: summaryLabels.outdoorUnit, value: outdoorText });
            if (aircoTimingText) roomItems.push({ label: summaryLabels.timing, value: aircoTimingText });
            if (aircoTimingNotes) roomItems.push({ label: summaryLabels.timingNotes, value: aircoTimingNotes });

            addSection(summaryLabels.rooms, roomItems);
        }

        // 3. Customer / urgency
        addSection(summaryLabels.customer, [
            { label: 'Type', value: qSelectText('customer_type') },
            { label: 'Urgentie', value: qSelectText('urgency') },
        ]);

        // 4. Problem description (truncate at 300 chars)
        var descVal = qVal('description');
        if (descVal) {
            var displayDesc = descVal.length > 300 ? descVal.slice(0, 297) + '…' : descVal;
            addSection(summaryLabels.problem, [{ value: displayDesc }]);
        }

        // 5. Technical details
        var unknownCb = form ? form.querySelector('input[name="unknown_device_details"]') : null;
        if (unknownCb && unknownCb.checked) {
            addSection(summaryLabels.technical, [{ value: summaryLabels.unknownDevice }]);
        } else {
            addSection(summaryLabels.technical, [
                { label: 'Merk',        value: qVal('brand') },
                { label: 'Model',       value: qVal('device_model') },
                { label: 'Serienummer', value: qVal('serial_number') },
            ]);
        }

        // 6. Location and availability
        var address = [qVal('street'), qVal('postal_code'), qVal('city')].filter(Boolean).join(', ');
        addSection(summaryLabels.location, [
            { value: address },
            { label: 'Beschikbaarheid', value: qVal('availability') },
        ]);

        // 7. Contact details
        addSection(summaryLabels.contact, [
            { value: qVal('customer_name') },
            { value: qVal('customer_email') },
            { value: qVal('customer_phone') },
        ]);

        // 8. Uploaded files — count both upload inputs
        var fileCount = document.querySelectorAll('.selected-attachment-item').length;
        if (fileCount > 0) {
            addSection(summaryLabels.uploads, [{ value: String(fileCount) }]);
        }
    }

    // ── Init ──────────────────────────────────────────────────────────────────
    function init() {
        formCard.classList.add('is-wizard-active');
        initRoomManager();
        var initialCategory = getSelectedCategory();
        visibleSections     = computeVisible(initialCategory);
        updateConditionalVisibility(initialCategory);

        var errorEl    = document.getElementById('wizardErrorState');
        var hasErrors  = errorEl && errorEl.dataset.hasErrors === 'true';
        var errorFields = [];
        if (hasErrors && errorEl.dataset.errorFields) {
            try { errorFields = JSON.parse(errorEl.dataset.errorFields); } catch (e) {}
        }

        var startIndex = hasErrors ? findErrorStep(visibleSections, errorFields) : 0;
        showStep(visibleSections, startIndex);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
