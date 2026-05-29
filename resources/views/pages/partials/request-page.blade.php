@php
    $siteName = config('site.name');

    $configuredServices = config('services');
    $steps = config('request-flow.steps', []);
    $requestTypes = config('request-flow.request_types', []);

    $services = collect($configuredServices)
        ->filter(fn ($service) => $service['is_active'] ?? false)
        ->map(function ($service) use ($locale) {
            return $service['translations'][$locale] ?? $service['translations']['nl'];
        })
        ->values();

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

    $getConditionServices = function (array $condition): array {
        if (isset($condition['service_slugs']) && is_array($condition['service_slugs'])) {
            return $condition['service_slugs'];
        }

        if (isset($condition['service_slug']) && is_string($condition['service_slug'])) {
            return [$condition['service_slug']];
        }

        return [];
    };

    $stepMatchesOldInput = function (array $step) use ($services, $requestTypes, $getConditionServices): bool {
        $condition = $step['condition'] ?? null;

        if (! $condition) {
            return true;
        }

        $defaultService = $services->first()['slug'] ?? '';
        $defaultRequestType = $requestTypes[0]['value'] ?? '';

        $selectedService = old('service_slug', $defaultService);
        $selectedRequestType = old('request_type', $defaultRequestType);

        $conditionServices = $getConditionServices($condition);
        $conditionRequestTypes = $condition['request_types'] ?? [];

        if (! empty($conditionServices) && ! in_array($selectedService, $conditionServices, true)) {
            return false;
        }

        if (! empty($conditionRequestTypes) && ! in_array($selectedRequestType, $conditionRequestTypes, true)) {
            return false;
        }

        return true;
    };

    $labels = [
        'nl' => [
            'hero_badge' => 'Slimme aanvraag',
            'hero_title' => 'Start je aanvraag',
            'hero_intro' => 'Vul de belangrijkste informatie in. Zo kan je aanvraag sneller en duidelijker opgevolgd worden.',
            'submit' => 'Aanvraag verzenden',
            'success_title' => 'Je aanvraag werd verzonden.',
            'success_text' => 'We hebben je aanvraag goed ontvangen en nemen zo snel mogelijk contact op.',
            'error_title' => 'Controleer de ingevulde informatie.',
            'estimate_title' => 'Richtprijs mogelijk na volledige info',
            'estimate_text' => 'Op basis van de gekozen dienst, technische gegevens en foto’s kan ' . $siteName . ' sneller inschatten wat nodig is en indien mogelijk een richtprijs of duidelijke vervolgstap voorstellen.',
            'summary_title' => 'Samenvatting',
            'summary_text' => 'De aanvraag wordt opgeslagen zodat ze later opgevolgd kan worden in het admin panel.',
            'choose_option' => 'Kies een optie',
        ],
        'fr' => [
            'hero_badge' => 'Demande intelligente',
            'hero_title' => 'Démarrer votre demande',
            'hero_intro' => 'Remplissez les informations les plus importantes afin que votre demande puisse être suivie plus rapidement et plus clairement.',
            'submit' => 'Envoyer la demande',
            'success_title' => 'Votre demande a été envoyée.',
            'success_text' => 'Nous avons bien reçu votre demande et nous vous contacterons dès que possible.',
            'error_title' => 'Veuillez vérifier les informations saisies.',
            'estimate_title' => 'Estimation possible après informations complètes',
            'estimate_text' => 'Sur la base du service choisi, des informations techniques et des photos, ' . $siteName . ' peut évaluer plus rapidement la situation et proposer une estimation ou une prochaine étape claire si possible.',
            'summary_title' => 'Résumé',
            'summary_text' => 'La demande sera enregistrée afin de pouvoir être suivie plus tard dans le panneau d’administration.',
            'choose_option' => 'Choisissez une option',
        ],
        'en' => [
            'hero_badge' => 'Smart request',
            'hero_title' => 'Start your request',
            'hero_intro' => 'Fill in the most important information so your request can be followed up faster and more clearly.',
            'submit' => 'Send request',
            'success_title' => 'Your request has been sent.',
            'success_text' => 'We have received your request and will contact you as soon as possible.',
            'error_title' => 'Please check the entered information.',
            'estimate_title' => 'Estimate possible after complete information',
            'estimate_text' => 'Based on the selected service, technical details and photos, ' . $siteName . ' can estimate what is needed faster and provide an estimate or clear next step when possible.',
            'summary_title' => 'Summary',
            'summary_text' => 'The request will be stored so it can later be followed up in an admin panel.',
            'choose_option' => 'Choose an option',
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];
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
                <div class="form-success">
                    <strong>{{ $text['success_title'] }}</strong>
                    <p>{{ $text['success_text'] }}</p>
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
            >
                @csrf

                <div class="request-layout">
                    <aside class="request-steps">
                        @foreach ($steps as $index => $step)
                            @php
                                $condition = $step['condition'] ?? null;
                                $isVisibleByDefault = $stepMatchesOldInput($step);
                            @endphp

                            <div
                                class="request-step {{ $index === 0 ? 'is-active' : '' }} {{ $isVisibleByDefault ? '' : 'is-condition-hidden' }}"
                                data-step-nav="{{ $index }}"
                                @if ($condition)
                                    data-condition-services="{{ implode(',', $getConditionServices($condition)) }}"
                                    data-condition-request-types="{{ implode(',', $condition['request_types'] ?? []) }}"
                                @endif
                            >
                                {{ $getLabel($step) }}
                            </div>
                        @endforeach
                    </aside>

                    <div class="request-form-card">
                        @foreach ($steps as $stepIndex => $step)
                            @php
                                $condition = $step['condition'] ?? null;
                                $isVisibleByDefault = $stepMatchesOldInput($step);
                            @endphp

                            <section
                                class="form-section {{ $isVisibleByDefault ? '' : 'is-condition-hidden' }}"
                                data-step="{{ $stepIndex }}"
                                @if ($condition)
                                    data-condition-services="{{ implode(',', $getConditionServices($condition)) }}"
                                    data-condition-request-types="{{ implode(',', $condition['request_types'] ?? []) }}"
                                @endif
                            >
                                @if (($step['type'] ?? '') !== 'summary')
                                    <h2>{{ $getLabel($step) }}</h2>
                                @endif

                                @if (($step['type'] ?? '') === 'service_selection')
                                    <div class="option-grid">
                                        @foreach ($services as $service)
                                            <label class="option-card {{ old('service_slug', $services->first()['slug'] ?? '') === $service['slug'] ? 'is-selected' : '' }}">
                                                <input
                                                    type="radio"
                                                    name="service_slug"
                                                    value="{{ $service['slug'] }}"
                                                    {{ old('service_slug', $services->first()['slug'] ?? '') === $service['slug'] ? 'checked' : '' }}
                                                >

                                                <span>{{ $service['title'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>

                                    @error('service_slug')
                                        <p class="field-error-text">{{ $message }}</p>
                                    @enderror
                                @elseif (($step['type'] ?? '') === 'request_type_selection')
                                    <div class="option-grid option-grid-four">
                                        @foreach ($requestTypes as $requestType)
                                            <label class="option-card {{ old('request_type', $requestTypes[0]['value'] ?? '') === $requestType['value'] ? 'is-selected' : '' }}">
                                                <input
                                                    type="radio"
                                                    name="request_type"
                                                    value="{{ $requestType['value'] }}"
                                                    {{ old('request_type', $requestTypes[0]['value'] ?? '') === $requestType['value'] ? 'checked' : '' }}
                                                >

                                                <span>{{ $getLabel($requestType) }}</span>
                                            </label>
                                        @endforeach
                                    </div>

                                    @error('request_type')
                                        <p class="field-error-text">{{ $message }}</p>
                                    @enderror
                                @elseif (($step['type'] ?? '') === 'fields')
                                    <div class="form-grid">
                                        @foreach ($step['fields'] ?? [] as $field)
                                            @if (($field['type'] ?? '') === 'checkbox')
                                                <label class="checkbox-field {{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
                                                    <input
                                                        type="checkbox"
                                                        name="{{ $field['name'] }}"
                                                        value="1"
                                                        {{ old($field['name']) ? 'checked' : '' }}
                                                    >

                                                    <span>{{ $getLabel($field) }}</span>

                                                    @error($field['name'])
                                                        <p class="field-error-text">{{ $message }}</p>
                                                    @enderror
                                                </label>
                                            @elseif (($field['type'] ?? '') === 'textarea')
                                                <label class="{{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
                                                    <span>
                                                        {{ $getLabel($field) }}

                                                        @if ($isRequiredField($field))
                                                            <span class="required-star">*</span>
                                                        @endif
                                                    </span>

                                                    <textarea
                                                        name="{{ $field['name'] }}"
                                                        placeholder="{{ $getPlaceholder($field) }}"
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

                                                    <select name="{{ $field['name'] }}">
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
                                                    </span>

                                                    <input
                                                        type="{{ $field['type'] ?? 'text' }}"
                                                        name="{{ $field['name'] }}"
                                                        value="{{ old($field['name']) }}"
                                                        placeholder="{{ $getPlaceholder($field) }}"
                                                    >

                                                    @error($field['name'])
                                                        <p class="field-error-text">{{ $message }}</p>
                                                    @enderror
                                                </label>
                                            @endif
                                        @endforeach
                                    </div>

                                    @if (isset($step['helper_box']))
                                        <div class="upload-box {{ $errors->has('attachments') || $errors->has('attachments.*') ? 'field-has-error' : '' }}">
                                            <strong>
                                                {{ $step['helper_box']['title'][$locale] ?? $step['helper_box']['title']['nl'] }}
                                            </strong>

                                            <p>
                                                {{ $step['helper_box']['text'][$locale] ?? $step['helper_box']['text']['nl'] }}
                                            </p>

                                            <label class="upload-file-control">
                                                <span>
                                                    {{ $locale === 'fr' ? 'Choisir des fichiers' : ($locale === 'en' ? 'Choose files' : 'Bestanden kiezen') }}
                                                </span>

                                                <input
                                                    id="attachmentsInput"
                                                    type="file"
                                                    name="attachments[]"
                                                    multiple
                                                    accept=".jpg,.jpeg,.png,.webp,.pdf"
                                                >
                                            </label>

                                            <div id="selectedAttachments" class="selected-attachments"></div>

                                            @error('attachments')
                                                <p class="field-error-text">{{ $message }}</p>
                                            @enderror

                                            @error('attachments.*')
                                                <p class="field-error-text">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @endif
                                @elseif (($step['type'] ?? '') === 'summary')
                                    <div class="request-summary-box">
                                        <h2>{{ $text['estimate_title'] }}</h2>
                                        <p>{{ $text['estimate_text'] }}</p>
                                    </div>

                                    <div class="summary-card">
                                        <h3>{{ $text['summary_title'] }}</h3>
                                        <p>{{ $text['summary_text'] }}</p>
                                    </div>
                                @endif
                            </section>
                        @endforeach

                        <div class="button-row">
                            <button class="button button-primary button-large" type="submit">
                                {{ $text['submit'] }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>

<script>
    (function () {
        function initConditionalRequestSteps() {
            const conditionalElements = document.querySelectorAll('[data-condition-services]');
            const serviceInputs = document.querySelectorAll('input[name="service_slug"]');
            const requestTypeInputs = document.querySelectorAll('input[name="request_type"]');

            function getCheckedValue(inputs) {
                const checkedInput = Array.from(inputs).find((input) => input.checked);

                return checkedInput ? checkedInput.value : '';
            }

            function forceInputSelection(input) {
                input.checked = true;
            }

            function updateOptionCardStates(inputs) {
                inputs.forEach((input) => {
                    const card = input.closest('.option-card');

                    if (!card) {
                        return;
                    }

                    card.classList.toggle('is-selected', input.checked);
                });
            }

            function updateConditionalSteps() {
                const selectedService = getCheckedValue(serviceInputs);
                const selectedRequestType = getCheckedValue(requestTypeInputs);

                console.log('Selected service:', selectedService);
                console.log('Selected request type:', selectedRequestType);

                conditionalElements.forEach((element) => {
                    const allowedServices = (element.dataset.conditionServices || '')
                        .split(',')
                        .map((value) => value.trim())
                        .filter(Boolean);

                    const allowedRequestTypes = (element.dataset.conditionRequestTypes || '')
                        .split(',')
                        .map((value) => value.trim())
                        .filter(Boolean);

                    console.log('Allowed services:', allowedServices);
                    console.log('Allowed request types:', allowedRequestTypes);

                    const matchesService = allowedServices.length === 0 || allowedServices.includes(selectedService);
                    const matchesRequestType = allowedRequestTypes.length === 0 || allowedRequestTypes.includes(selectedRequestType);

                    console.log('Matches service:', matchesService);
                    console.log('Matches request type:', matchesRequestType);

                    element.classList.toggle('is-condition-hidden', !(matchesService && matchesRequestType));
                });
            }

            serviceInputs.forEach((input) => {
                const card = input.closest('.option-card');

                if (card) {
                    card.addEventListener('click', () => {
                        forceInputSelection(input);
                        updateOptionCardStates(serviceInputs);
                        updateConditionalSteps();
                    });
                }

                input.addEventListener('change', () => {
                    updateOptionCardStates(serviceInputs);
                    updateConditionalSteps();
                });
            });

            requestTypeInputs.forEach((input) => {
                const card = input.closest('.option-card');

                if (card) {
                    card.addEventListener('click', () => {
                        forceInputSelection(input);
                        updateOptionCardStates(requestTypeInputs);
                        updateConditionalSteps();
                    });
                }

                input.addEventListener('change', () => {
                    updateOptionCardStates(requestTypeInputs);
                    updateConditionalSteps();
                });
            });

            updateOptionCardStates(serviceInputs);
            updateOptionCardStates(requestTypeInputs);
            updateConditionalSteps();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initConditionalRequestSteps);
        } else {
            initConditionalRequestSteps();
        }
    })();
</script>