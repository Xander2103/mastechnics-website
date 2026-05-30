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
            'estimate_text' => 'Op basis van de gekozen dienst, technische gegevens en foto's kan ' . $siteName . ' sneller inschatten wat nodig is en indien mogelijk een richtprijs of duidelijke vervolgstap voorstellen.',
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
            'summary_text' => 'La demande sera enregistrée afin de pouvoir être suivie plus tard dans le panneau d'administration.',
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
                                $conditionCategories = $getConditionCategories($step);
                                $isVisibleByDefault = $stepMatchesOldInput($step);
                            @endphp

                            <div
                                class="request-step {{ $index === 0 ? 'is-active' : '' }} {{ $isVisibleByDefault ? '' : 'is-condition-hidden' }}"
                                data-step-nav="{{ $index }}"
                                @if (!empty($conditionCategories))
                                    data-condition-service-categories="{{ implode(',', $conditionCategories) }}"
                                @endif
                            >
                                {{ $getLabel($step) }}
                            </div>
                        @endforeach
                    </aside>

                    <div class="request-form-card">
                        @foreach ($steps as $stepIndex => $step)
                            @php
                                $conditionCategories = $getConditionCategories($step);
                                $isVisibleByDefault = $stepMatchesOldInput($step);
                            @endphp

                            <section
                                class="form-section {{ $isVisibleByDefault ? '' : 'is-condition-hidden' }}"
                                data-step="{{ $stepIndex }}"
                                @if (!empty($conditionCategories))
                                    data-condition-service-categories="{{ implode(',', $conditionCategories) }}"
                                @endif
                            >
                                @if (($step['type'] ?? '') !== 'summary')
                                    <h2>{{ $getLabel($step) }}</h2>
                                @endif

                                @if (($step['type'] ?? '') === 'service_category_selection')
                                    <div class="option-grid">
                                        @foreach ($step['options'] ?? [] as $option)
                                            <label class="option-card {{ old('service_category', '') === $option['value'] ? 'is-selected' : '' }}">
                                                <input
                                                    type="radio"
                                                    name="service_category"
                                                    value="{{ $option['value'] }}"
                                                    {{ old('service_category', '') === $option['value'] ? 'checked' : '' }}
                                                >
                                                <span>{{ $getLabel($option) }}</span>
                                            </label>
                                        @endforeach
                                    </div>

                                    @error('service_category')
                                        <p class="field-error-text">{{ $message }}</p>
                                    @enderror
                                @elseif (($step['type'] ?? '') === 'fields')
                                    @if (isset($step['urgent_warning']))
                                        <div class="urgent-warning-box">
                                            {{ $step['urgent_warning'][$locale] ?? $step['urgent_warning']['nl'] }}
                                        </div>
                                    @endif

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

                                            @if ($step['helper_box']['render_upload'] ?? true)
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
                                            @endif
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
        const conditionalElements = document.querySelectorAll('[data-condition-service-categories]');
        const categoryInputs = document.querySelectorAll('input[name="service_category"]');

        function getCheckedValue(inputs) {
            const checked = Array.from(inputs).find(function (i) { return i.checked; });
            return checked ? checked.value : '';
        }

        function updateConditionalSteps() {
            const selectedCategory = getCheckedValue(categoryInputs);

            conditionalElements.forEach(function (element) {
                const allowed = (element.dataset.conditionServiceCategories || '')
                    .split(',')
                    .map(function (v) { return v.trim(); })
                    .filter(Boolean);

                const matches = allowed.length === 0 || allowed.indexOf(selectedCategory) !== -1;
                element.classList.toggle('is-condition-hidden', !matches);
            });
        }

        categoryInputs.forEach(function (input) {
            const card = input.closest('.option-card');
            if (card) {
                card.addEventListener('click', updateConditionalSteps);
            }
            input.addEventListener('change', updateConditionalSteps);
        });

        updateConditionalSteps();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initConditionalRequestSteps);
    } else {
        initConditionalRequestSteps();
    }
})();
</script>
