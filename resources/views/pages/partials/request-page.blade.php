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
            'hero_badge'     => 'Slimme aanvraag',
            'hero_title'     => 'Start je aanvraag',
            'hero_intro'     => 'Vul de belangrijkste informatie in. Zo kan je aanvraag sneller en duidelijker opgevolgd worden.',
            'verder'         => 'Verder',
            'terug'          => 'Terug',
            'submit'         => 'Verstuur mijn aanvraag',
            'submit_offerte' => 'Vraag mijn offerte aan',
            'step_indicator' => 'Stap {1} van {2}',
            'success_title'  => 'Je aanvraag werd verzonden.',
            'success_text'   => 'We hebben je aanvraag goed ontvangen en nemen zo snel mogelijk contact op.',
            'error_title'    => 'Controleer de ingevulde informatie.',
            'estimate_title' => 'Richtprijs mogelijk na volledige info',
            'estimate_text'  => 'Op basis van de gekozen dienst, technische gegevens en foto\'s kan ' . $siteName . ' sneller inschatten wat nodig is en indien mogelijk een richtprijs of duidelijke vervolgstap voorstellen.',
            'summary_title'  => 'Samenvatting',
            'summary_text'   => 'De aanvraag wordt opgeslagen zodat ze later opgevolgd kan worden in het admin panel.',
            'choose_option'  => 'Kies een optie',
            'privacy_notice' => 'Uw gegevens en eventuele foto\'s worden enkel gebruikt voor de opvolging van uw aanvraag en worden niet gedeeld met derden.',
            'choose_files'   => 'Bestanden kiezen',
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
            'success_title'  => 'Votre demande a été envoyée.',
            'success_text'   => 'Nous avons bien reçu votre demande et nous vous contacterons dès que possible.',
            'error_title'    => 'Veuillez vérifier les informations saisies.',
            'estimate_title' => 'Estimation possible après informations complètes',
            'estimate_text'  => 'Sur la base du service choisi, des informations techniques et des photos, ' . $siteName . ' peut évaluer plus rapidement la situation et proposer une estimation ou une prochaine étape claire si possible.',
            'summary_title'  => 'Résumé',
            'summary_text'   => 'La demande sera enregistrée afin de pouvoir être suivie plus tard dans le panneau d\'administration.',
            'choose_option'  => 'Choisissez une option',
            'privacy_notice' => 'Vos données et éventuelles photos sont utilisées uniquement pour le traitement de votre demande et ne sont pas partagées avec des tiers.',
            'choose_files'   => 'Choisir des fichiers',
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
            'success_title'  => 'Your request has been sent.',
            'success_text'   => 'We have received your request and will contact you as soon as possible.',
            'error_title'    => 'Please check the entered information.',
            'estimate_title' => 'Estimate possible after complete information',
            'estimate_text'  => 'Based on the selected service, technical details and photos, ' . $siteName . ' can estimate what is needed faster and provide an estimate or clear next step when possible.',
            'summary_title'  => 'Summary',
            'summary_text'   => 'The request will be stored so it can later be followed up in an admin panel.',
            'choose_option'  => 'Choose an option',
            'privacy_notice' => 'Your data and any photos are used solely to process your request and are not shared with third parties.',
            'choose_files'   => 'Choose files',
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
                    <aside class="request-steps" id="wizardSidebar">
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

                    <div class="request-form-area">
                        <div class="wizard-progress-wrap" id="wizardProgressWrap" aria-hidden="true">
                            <div class="wizard-progress-fill" id="wizardProgressFill"></div>
                        </div>

                        <div class="request-form-card" id="requestFormCard">
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
                        </div>

                        <div class="wizard-nav-bar">
                            <button type="button" id="wizardTerug" class="button button-ghost wizard-nav-back is-wizard-hidden">
                                &larr; {{ $text['terug'] }}
                            </button>

                            <span class="wizard-step-count" id="wizardStepCount" data-template="{{ $text['step_indicator'] }}"></span>

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
    var formCard       = document.getElementById('requestFormCard');
    var wizardTerug    = document.getElementById('wizardTerug');
    var wizardVerder   = document.getElementById('wizardVerder');
    var wizardSubmit   = document.getElementById('wizardSubmit');
    var wizardCount    = document.getElementById('wizardStepCount');
    var progressFill   = document.getElementById('wizardProgressFill');
    var sidebarItems   = document.querySelectorAll('.request-step[data-step-nav]');
    var allSections    = Array.from(document.querySelectorAll('.form-section[data-step]'));
    var categoryInputs = document.querySelectorAll('input[name="service_category"]');
    var attachInput    = document.getElementById('attachmentsInput');
    var attachList     = document.getElementById('selectedAttachments');

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
        });
    }

    function showStep(sections, index) {
        currentIndex = index;

        // Wizard step visibility
        allSections.forEach(function (s) { s.classList.remove('wizard-current'); });
        if (sections[index]) { sections[index].classList.add('wizard-current'); }

        // Sidebar active
        var activeDomStep = sections[index] ? parseInt(sections[index].dataset.step, 10) : -1;
        sidebarItems.forEach(function (item) {
            item.classList.toggle('is-active', parseInt(item.dataset.stepNav, 10) === activeDomStep);
        });

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

        // Navigation buttons
        var isFirst = index === 0;
        var isLast  = index === sections.length - 1;

        wizardTerug.classList.toggle('is-wizard-hidden', isFirst);
        wizardVerder.classList.toggle('is-wizard-hidden', isLast);
        wizardSubmit.classList.toggle('is-wizard-hidden', !isLast);

        // Disable/enable Verder (only locked on step 0 until category chosen)
        if (isFirst) {
            var hasCat = !!getSelectedCategory();
            wizardVerder.disabled = !hasCat;
            wizardVerder.setAttribute('aria-disabled', String(!hasCat));
        } else {
            wizardVerder.disabled = false;
            wizardVerder.removeAttribute('aria-disabled');
        }

        // Submit label (airco offerte → different CTA)
        var cat = getSelectedCategory();
        wizardSubmit.textContent = (cat === 'airco_offerte')
            ? (wizardSubmit.dataset.labelOfferte || 'Vraag mijn offerte aan')
            : (wizardSubmit.dataset.labelGeneral || 'Verstuur mijn aanvraag');

        // Scroll form into view when advancing (not on init)
        if (index > 0 && formCard) {
            formCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // ── Category change ───────────────────────────────────────────────────────
    function onCategoryChange() {
        var category    = getSelectedCategory();
        visibleSections = computeVisible(category);
        updateConditionalVisibility(category);
        if (currentIndex === 0) { showStep(visibleSections, 0); }
    }

    categoryInputs.forEach(function (input) {
        input.addEventListener('change', onCategoryChange);
    });

    // ── Nav buttons ───────────────────────────────────────────────────────────
    wizardVerder.addEventListener('click', function () {
        if (currentIndex < visibleSections.length - 1) {
            showStep(visibleSections, currentIndex + 1);
        }
    });

    wizardTerug.addEventListener('click', function () {
        if (currentIndex > 0) {
            showStep(visibleSections, currentIndex - 1);
        }
    });

    // ── File attachment preview ────────────────────────────────────────────────
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
                nameSpan.textContent = file.name; // textContent — safe, no HTML parsing

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.setAttribute('aria-label', attachList.dataset.removeLabel || 'Verwijder');
                removeBtn.textContent = '×'; // ×

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

    // ── Init ──────────────────────────────────────────────────────────────────
    function init() {
        formCard.classList.add('is-wizard-active');
        var initialCategory = getSelectedCategory();
        visibleSections     = computeVisible(initialCategory);
        updateConditionalVisibility(initialCategory);
        showStep(visibleSections, 0);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
