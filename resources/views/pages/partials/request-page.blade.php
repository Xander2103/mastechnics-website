@php
    $siteName = config('site.name');

    $configuredServices = config('services');
    $requestSteps = config('request-flow.steps', []);
    $requestTypes = config('request-flow.request_types', []);

    $services = collect($configuredServices)
        ->filter(fn($service) => $service['is_active'] ?? false)
        ->map(function ($service) use ($locale) {
            return $service['translations'][$locale] ?? $service['translations']['nl'];
        })
        ->values();

    $labels = [
        'nl' => [
            'badge' => 'Slimme technische aanvraag',
            'estimate_title' => 'Richtprijs mogelijk na volledige info',
            'estimate_text' =>
                'Op basis van de gekozen dienst, technische gegevens en foto’s kan ' .
                $siteName .
                ' sneller inschatten wat nodig is en indien mogelijk een richtprijs of duidelijke vervolgstap voorstellen.',
            'summary_title' => 'Samenvatting',
            'summary_text' => 'De aanvraag wordt opgeslagen zodat ze later in een adminpaneel opgevolgd kan worden.',
            'button' => 'Aanvraag verzenden',
            'success' => 'Je aanvraag werd goed ontvangen. We nemen zo snel mogelijk contact op.',
            'errors_title' => 'Controleer de ingevulde gegevens.',
        ],
        'fr' => [
            'badge' => 'Demande technique intelligente',
            'estimate_title' => 'Estimation possible après informations complètes',
            'estimate_text' =>
                'Grâce au service choisi, aux informations techniques et aux photos, ' .
                $siteName .
                ' peut estimer plus rapidement ce qui est nécessaire et proposer une estimation ou une prochaine étape claire.',
            'summary_title' => 'Résumé',
            'summary_text' => 'La demande sera enregistrée afin de pouvoir être suivie plus tard dans un espace admin.',
            'button' => 'Envoyer la demande',
            'success' => 'Votre demande a bien été reçue. Nous vous contacterons dès que possible.',
            'errors_title' => 'Veuillez vérifier les informations saisies.',
        ],
        'en' => [
            'badge' => 'Smart technical request',
            'estimate_title' => 'Estimate possible after complete information',
            'estimate_text' =>
                'Based on the selected service, technical details and photos, ' .
                $siteName .
                ' can estimate what is needed faster and provide an estimate or clear next step when possible.',
            'summary_title' => 'Summary',
            'summary_text' => 'The request will be stored so it can later be followed up in an admin panel.',
            'button' => 'Send request',
            'success' => 'Your request has been received. We will contact you as soon as possible.',
            'errors_title' => 'Please check the entered information.',
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];

    $getLabel = function (array $item) use ($locale): string {
        return $item['labels'][$locale] ?? ($item['labels']['nl'] ?? '');
    };

    $getPlaceholder = function (array $item) use ($locale): string {
        return $item['placeholder'][$locale] ?? ($item['placeholder']['nl'] ?? '');
    };

    $isRequiredField = function (array $field): bool {
        return ($field['required'] ?? false) || in_array($field['name'], ['brand', 'device_model'], true);
    };
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

        <form method="POST" action="{{ route('customer-requests.store', ['locale' => $locale]) }}"
            enctype="multipart/form-data">
            @csrf

            <div class="request-layout">
                <aside class="request-steps">
                    @foreach ($requestSteps as $index => $step)
                        <div class="request-step {{ $index === 0 ? 'is-active' : '' }}">
                            {{ $getLabel($step) }}
                        </div>
                    @endforeach
                </aside>

                <div class="request-form-card">
                    @foreach ($requestSteps as $stepIndex => $step)
                        @if ($step['type'] === 'service_selection')
                            <div class="form-section" data-step="{{ $stepIndex }}">
                                <h2>
                                    {{ $getLabel($step) }}
                                    <span class="required-star">*</span>
                                </h2>

                                <div
                                    class="option-grid {{ $errors->has('service_slug') ? 'option-group-has-error' : '' }}">
                                    @foreach ($services as $index => $service)
                                        <label
                                            class="option-card {{ old('service_slug', $services[0]['slug'] ?? '') === $service['slug'] ? 'is-selected' : '' }}">
                                            <input type="radio" name="service_slug" value="{{ $service['slug'] }}"
                                                {{ old('service_slug', $services[0]['slug'] ?? '') === $service['slug'] ? 'checked' : '' }}>
                                            <span>{{ $service['title'] }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                @error('service_slug')
                                    <p class="option-group-error">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        @if ($step['type'] === 'request_type_selection')
                            <div class="form-section" data-step="{{ $stepIndex }}">
                                <h2>
                                    {{ $getLabel($step) }}
                                    <span class="required-star">*</span>
                                </h2>

                                <div
                                    class="option-grid option-grid-small {{ $errors->has('request_type') ? 'option-group-has-error' : '' }}">
                                    @foreach ($requestTypes as $index => $requestType)
                                        <label
                                            class="option-card {{ old('request_type', $requestTypes[0]['value'] ?? '') === $requestType['value'] ? 'is-selected' : '' }}">
                                            <input type="radio" name="request_type"
                                                value="{{ $requestType['value'] }}"
                                                {{ old('request_type', $requestTypes[0]['value'] ?? '') === $requestType['value'] ? 'checked' : '' }}>
                                            <span>{{ $getLabel($requestType) }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                @error('request_type')
                                    <p class="option-group-error">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        @if ($step['type'] === 'fields')
                            <div class="form-section" data-step="{{ $stepIndex }}">
                                <h2>{{ $getLabel($step) }}</h2>

                                <div class="field-grid">
                                    @foreach ($step['fields'] as $field)
                                        @if ($field['type'] === 'checkbox')
                                            <label
                                                class="checkbox-field {{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
                                                <input type="checkbox" name="{{ $field['name'] }}" value="1"
                                                    {{ old($field['name']) ? 'checked' : '' }}>
                                                <span>{{ $getLabel($field) }}</span>

                                                @error($field['name'])
                                                    <p class="field-error-text">{{ $message }}</p>
                                                @enderror
                                            </label>
                                        @elseif ($field['type'] === 'textarea')
                                            <label
                                                class="field-full {{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
                                                <span>
                                                    {{ $getLabel($field) }}

                                                    @if ($isRequiredField($field))
                                                        <span class="required-star">*</span>
                                                    @endif
                                                </span>

                                                <textarea name="{{ $field['name'] }}" rows="5" placeholder="{{ $getPlaceholder($field) }}">{{ old($field['name']) }}</textarea>

                                                @error($field['name'])
                                                    <p class="field-error-text">{{ $message }}</p>
                                                @enderror
                                            </label>
                                        @elseif ($field['type'] === 'select')
                                            <label class="{{ $errors->has($field['name']) ? 'field-has-error' : '' }}">
                                                <span>
                                                    {{ $getLabel($field) }}

                                                    @if ($isRequiredField($field))
                                                        <span class="required-star">*</span>
                                                    @endif
                                                </span>

                                                <select name="{{ $field['name'] }}">
                                                    <option value="">
                                                        {{ $locale === 'fr' ? 'Choisissez une option' : ($locale === 'en' ? 'Choose an option' : 'Kies een optie') }}
                                                    </option>

                                                    @foreach ($field['options'] ?? [] as $option)
                                                        <option value="{{ $option['value'] }}"
                                                            {{ old($field['name']) === $option['value'] ? 'selected' : '' }}>
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

                                                <input type="{{ $field['type'] }}" name="{{ $field['name'] }}"
                                                    value="{{ old($field['name']) }}"
                                                    placeholder="{{ $getPlaceholder($field) }}"
                                                    @if ($field['type'] === 'tel') pattern="[0-9+\s().-]+" @endif>

                                                @error($field['name'])
                                                    <p class="field-error-text">{{ $message }}</p>
                                                @enderror
                                            </label>
                                        @endif
                                    @endforeach
                                </div>

                                @if (isset($step['helper_box']))
                                    <div
                                        class="upload-box {{ $errors->has('attachments') || $errors->has('attachments.*') ? 'field-has-error' : '' }}">
                                        <strong>
                                            {{ $step['helper_box']['title'][$locale] ?? $step['helper_box']['title']['nl'] }}
                                        </strong>

                                        <p>
                                            {{ $step['helper_box']['text'][$locale] ?? $step['helper_box']['text']['nl'] }}
                                        </p>

                                        <label class="upload-file-control">
                                            <span>Bestanden kiezen</span>

                                            <input id="attachmentsInput" type="file" name="attachments[]" multiple
                                                accept=".jpg,.jpeg,.png,.webp,.pdf">
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
                            </div>
                        @endif

                        @if ($step['type'] === 'summary')
                            <div class="estimate-box" data-step="{{ $stepIndex }}">
                                <h3>{{ $text['estimate_title'] }}</h3>
                                <p>{{ $text['estimate_text'] }}</p>
                            </div>

                            <div class="summary-box" data-step="{{ $stepIndex }}">
                                <h3>{{ $text['summary_title'] }}</h3>
                                <p>{{ $text['summary_text'] }}</p>
                            </div>
                        @endif
                    @endforeach

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
