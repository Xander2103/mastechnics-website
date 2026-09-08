<?php

namespace App\Http\Controllers;

use App\Mail\CustomerRequestConfirmationMail;
use App\Mail\NewCustomerRequestMail;
use App\Models\CustomerRequest;
use App\Models\CustomerRequestAttachment;
use App\Services\Spam\FormMailer;
use App\Services\Spam\PublicFormGuard;
use App\Services\Spam\Rejection;
use App\Services\Spam\SubmissionFacts;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerRequestController extends Controller
{
    /**
     * Name of the honeypot field rendered (visually hidden) in the wizard.
     * Humans never see or fill it; a submission that does carry a value is
     * accepted with the normal success screen but stored and mailed nowhere.
     */
    public const HONEYPOT_FIELD = PublicFormGuard::HONEYPOT_FIELD;

    public function __construct(
        private readonly PublicFormGuard $guard,
        private readonly FormMailer $mailer,
    ) {
    }

    /**
     * Anti-abuse order (see PublicFormGuard): kill switch → idempotency →
     * attempt limits → validation → captcha → honeypot → fill time → accepted
     * limits → fingerprint → store → mail. No mail transport is reached
     * before the row exists.
     */
    public function store(Request $request, string $locale): RedirectResponse
    {
        app()->setLocale($locale);
        $form = PublicFormGuard::FORM_REQUEST;

        if (! $this->guard->formEnabled($form)) {
            $this->guard->noteDisabled($form, $request);

            return $this->refuseWith($form, 'disabled', 'form_disabled', $locale);
        }

        // A fresh random token is rendered into the form on every GET. A
        // request replaying the same token (double-click, refresh, retry)
        // is not a new request: answer with the success screen and do not
        // touch the rate limiter, the database or the mailer again.
        $token = $this->submissionToken($request);

        if ($token !== null && CustomerRequest::where('submission_token', $token)->exists()) {
            return $this->successRedirect($locale);
        }

        // Per-client + site-wide attempt limiter: bounds validation and
        // captcha checks a flood can trigger. Aborts with 429.
        $this->guard->enforceAttemptLimits($form, $request);

        $serviceCategories = collect(config('request-flow.service_categories', []));
        $allowedCategoryValues = $serviceCategories->pluck('value')->toArray();

        $dynamicFields = $this->getDynamicFields();
        $roomStep = $this->roomStepForCategory((string) $request->input('service_category', ''));

        $rules = [
            'service_category' => [
                'required',
                'string',
                Rule::in($allowedCategoryValues),
            ],
            'privacy_consent' => [
                'bail',
                'required',
                'accepted',
            ],
            'attachments' => [
                'nullable',
                'array',
                'max:8',
            ],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:5120',
            ],
        ];

        foreach ($dynamicFields as $field) {
            $rules[$field['name']] = $this->buildRulesForField($field);
        }

        // Room validation is derived from the airco_rooms step in
        // config/request-flow.php (room_types + room_fields), exactly like
        // the UI, so a field marked required there is required here too.
        if ($roomStep !== null) {
            $rules = array_merge($rules, $this->buildRoomRules($roomStep));
        }

        $attributes = $this->buildValidationAttributes($dynamicFields, $roomStep, $locale);

        $validatedData = $request->validate($rules, [], $attributes);

        $facts = new SubmissionFacts(
            email: (string) ($validatedData['customer_email'] ?? ''),
            phone: $validatedData['customer_phone'] ?? null,
            message: $validatedData['description'] ?? null,
        );

        // Captcha, honeypot, fill time, IP/e-mail/form/global limits and the
        // fingerprint. Nothing is stored or mailed for a rejection.
        $rejection = $this->guard->screen($form, $request, $facts, 'customer_email');

        if ($rejection !== null) {
            return $this->refuse($rejection, $locale);
        }

        // Derive service_slug and request_type from the selected service_category
        $submittedCategory = $validatedData['service_category'];
        $categoryConfig = $serviceCategories->firstWhere('value', $submittedCategory);

        $serviceKey = $categoryConfig['service_key'] ?? 'heating';
        $derivedRequestType = $categoryConfig['request_type'] ?? 'repair';

        $allServices = config('services', []);
        $serviceConfig = $allServices[$serviceKey] ?? null;
        $serviceSlug = $serviceConfig['translations'][$locale]['slug']
            ?? $serviceConfig['translations']['nl']['slug']
            ?? $serviceKey;
        $serviceTitle = $serviceConfig['translations'][$locale]['title']
            ?? $serviceConfig['translations']['nl']['title']
            ?? $serviceKey;
        $categoryLabels = $categoryConfig['labels'] ?? [];
        $categoryLabel = $categoryLabels[$locale] ?? $categoryLabels['nl'] ?? $submittedCategory;

        $answers = [
            'service_category'       => $submittedCategory,
            'service_category_label' => $categoryLabel,
            'service_slug'           => $serviceSlug,
            'service_title'          => $serviceTitle,
            'request_type'           => $derivedRequestType,
        ];

        foreach ($dynamicFields as $field) {
            $fieldName = $field['name'];

            if (($field['type'] ?? '') === 'checkbox') {
                $answers[$fieldName] = $request->boolean($fieldName);
                continue;
            }

            $value = $validatedData[$fieldName] ?? null;

            // Single-line fields (name, street, brand, ...) must never carry
            // line breaks: they end up in mail subjects/headers and CSV
            // exports. Textareas keep their formatting.
            if (is_string($value) && ($field['type'] ?? 'text') !== 'textarea') {
                $value = $this->stripLineBreaks($value);
            }

            $answers[$fieldName] = $value;
        }

        // Store rooms for airco_offerte with server-side surface calculation.
        // "Other" free-text values are only kept when the matching select is
        // actually set to other, so stray hidden-input values never persist.
        if ($roomStep !== null && $request->has('rooms')) {
            $processedRooms = [];
            foreach ($request->input('rooms', []) as $room) {
                $w = round((float) ($room['width']  ?? 0), 2);
                $l = round((float) ($room['length'] ?? 0), 2);
                $h = round((float) ($room['height'] ?? 0), 2);
                $processedRooms[] = [
                    'type'              => $room['type'] ?? null,
                    'width'             => $w,
                    'length'            => $l,
                    'height'            => $h > 0 ? $h : null,
                    'surface'           => ($w > 0 && $l > 0) ? round($w * $l, 1) : null,
                    'roof_type'         => $room['roof_type'] ?? null,
                    'roof_type_other'   => ($room['roof_type'] ?? null) === 'other' ? $this->stripLineBreaks((string) ($room['roof_type_other'] ?? '')) ?: null : null,
                    'windows'           => $room['windows'] ?? null,
                    'windows_other'     => ($room['windows'] ?? null) === 'other' ? $this->stripLineBreaks((string) ($room['windows_other'] ?? '')) ?: null : null,
                    'orientation'       => $room['orientation'] ?? null,
                    'orientation_other' => ($room['orientation'] ?? null) === 'other' ? $this->stripLineBreaks((string) ($room['orientation_other'] ?? '')) ?: null : null,
                ];
            }
            $answers['rooms'] = $processedRooms;
        }

        $attributesToStore = [
            'locale'       => $locale,
            'service_slug' => $serviceSlug,
            'request_type' => $derivedRequestType,
            'source'       => 'website',
            'submission_token' => $token,

            // New workflow fields
            'service_category' => $submittedCategory,
            'urgency_level'    => $answers['urgency_level'] ?? null,
            'customer_message' => $answers['description'] ?? null,
            'ai_summary'                => null,
            'ai_detected_missing_fields' => null,

            // Preferred time: text from most flows, or a structured timing value
            // (airco installation / water softener quote flows)
            'preferred_time' => $answers['preferred_time']
                ?? $answers['airco_installation_timing']
                ?? $answers['installation_timeframe']
                ?? $answers['availability']
                ?? null,

            // Customer info
            'customer_name'  => $answers['customer_name'] ?? '',
            'customer_email' => $answers['customer_email'] ?? '',
            'customer_phone' => $answers['customer_phone'] ?? null,

            // Technical (from general technical_details step)
            'brand'                  => $answers['brand'] ?? null,
            'device_model'           => $answers['device_model'] ?? null,
            'serial_number'          => $answers['serial_number'] ?? null,
            'unknown_device_details' => $answers['unknown_device_details'] ?? false,

            'description' => $answers['description'] ?? '',
            'privacy_consent' => $request->boolean('privacy_consent'),
            'status'      => 'new',

            'metadata' => [
                'source'           => 'smart_request_form',
                'service_category' => $submittedCategory,
                'service'          => ['slug' => $serviceSlug, 'title' => $serviceTitle],
                'request_type'     => ['value' => $derivedRequestType],
                'answers'          => $answers,
            ],
        ];

        $uploadedFiles = $request->hasFile('attachments') ? $request->file('attachments') : [];

        try {
            // Request row and attachment rows are one unit of work: a failing
            // attachment insert must not leave a request without its files.
            $customerRequest = DB::transaction(function () use ($attributesToStore, $uploadedFiles): CustomerRequest {
                $customerRequest = CustomerRequest::create($attributesToStore);

                foreach ($uploadedFiles as $uploadedFile) {
                    $path = $uploadedFile->store('customer-requests', CustomerRequestAttachment::DISK);

                    if (! is_string($path) || $path === '') {
                        throw new \RuntimeException('Attachment could not be stored on disk.');
                    }

                    $customerRequest->attachments()->create([
                        'original_name' => Str::limit($uploadedFile->getClientOriginalName(), 250, ''),
                        'path'          => $path,
                        'mime_type'     => $uploadedFile->getMimeType(),
                        'size'          => $uploadedFile->getSize(),
                    ]);
                }

                return $customerRequest;
            });
        } catch (QueryException $e) {
            // Lost the race against a concurrent request with the same token
            // (unique index): the other request stores and mails. Anything
            // else is a real failure and must surface as such.
            if ($token !== null && CustomerRequest::where('submission_token', $token)->exists()) {
                return $this->successRedirect($locale);
            }

            throw $e;
        }

        // Stored. Only now do counters move and may mail go out.
        $this->guard->recordAccepted($form, $request, $facts);

        $customerRequest->load(['attachments', 'notes']);

        $notificationEmails = collect(config('admin.notification_emails', []))
            ->push(config('site.request_notification_email'))
            ->filter()
            ->unique()
            ->values();

        if ($notificationEmails->isEmpty()) {
            Log::error('No admin notification recipient configured for customer requests', [
                'customer_request_id' => $customerRequest->id,
            ]);
        }

        foreach ($notificationEmails as $email) {
            $this->mailer->sendAdmin($form, $email, new NewCustomerRequestMail($customerRequest), $customerRequest);
        }

        $this->mailer->sendCustomer(
            $form,
            $customerRequest->customer_email,
            new CustomerRequestConfirmationMail($customerRequest),
            $customerRequest
        );

        return $this->successRedirect($locale);
    }

    /**
     * The success screen lives on the request page itself. Redirecting there
     * explicitly (instead of back()) means the flash message is shown even
     * when the browser sent no Referer header.
     */
    private function successRedirect(string $locale): RedirectResponse
    {
        $slug = config("site.page_slugs.request.{$locale}");

        if (! is_string($slug) || $slug === '') {
            return back()->with('success', 'request_created');
        }

        return redirect()
            ->route('pages.show', ['locale' => $locale, 'slug' => $slug])
            ->with('success', 'request_created');
    }

    private function submissionToken(Request $request): ?string
    {
        $token = $request->input('submission_token');

        if (! is_string($token)) {
            return null;
        }

        $token = trim($token);

        // Tokens are UUIDs generated by the page; anything else is ignored
        // rather than rejected so a hand-crafted POST still degrades to the
        // non-idempotent behaviour of the past.
        return $token !== '' && strlen($token) <= 64 && preg_match('/^[A-Za-z0-9\-]+$/', $token) === 1
            ? $token
            : null;
    }

    private function stripLineBreaks(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }

    /**
     * Answer a rejected submission. Nothing has been stored or mailed when
     * this is called. A silent rejection (honeypot) shows the normal
     * success screen so a bot learns nothing.
     */
    private function refuse(Rejection $rejection, string $locale): RedirectResponse
    {
        if ($rejection->silentSuccess) {
            return $this->successRedirect($locale);
        }

        return $this->refuseWith(PublicFormGuard::FORM_REQUEST, $rejection->messageKind, $rejection->errorKey, $locale);
    }

    private function refuseWith(string $form, string $messageKind, string $errorKey, string $locale): RedirectResponse
    {
        return back()
            ->withErrors([$errorKey => $this->guard->message($form, $messageKind, $locale)])
            ->withInput();
    }

    private function buildValidationAttributes(array $dynamicFields, ?array $roomStep, string $locale): array
    {
        $staticAttributes = [
            'service_category' => ['nl' => 'dienst', 'fr' => 'service', 'en' => 'service'],
            'attachments'      => ['nl' => 'bijlagen', 'fr' => 'pièces jointes', 'en' => 'attachments'],
            'attachments.*'    => ['nl' => 'bijlage', 'fr' => 'pièce jointe', 'en' => 'attachment'],
            'rooms'            => ['nl' => 'kamers', 'fr' => 'pièces', 'en' => 'rooms'],
            'rooms.*.type'     => ['nl' => 'type kamer', 'fr' => 'type de pièce', 'en' => 'room type'],
        ];

        $attributes = [];
        foreach ($staticAttributes as $name => $labels) {
            $attributes[$name] = $labels[$locale] ?? $labels['nl'];
        }

        foreach ($dynamicFields as $field) {
            $label = $field['labels'][$locale] ?? $field['labels']['nl'] ?? $field['name'];
            $attributes[$field['name']] = Str::lower($label);
        }

        foreach (($roomStep['room_fields'] ?? []) as $field) {
            $label = $field['labels'][$locale] ?? $field['labels']['nl'] ?? $field['name'];
            $attributes['rooms.*.' . $field['name']] = Str::lower($label);
        }

        return $attributes;
    }

    /**
     * The airco_rooms step of the flow, when it applies to the submitted
     * service category.
     */
    private function roomStepForCategory(string $selectedCategory): ?array
    {
        foreach (config('request-flow.steps', []) as $step) {
            if (($step['type'] ?? '') !== 'airco_rooms') {
                continue;
            }

            $allowedCategories = $step['condition']['service_categories'] ?? [];

            if (! empty($allowedCategories) && ! in_array($selectedCategory, $allowedCategories, true)) {
                continue;
            }

            return $step;
        }

        return null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function buildRoomRules(array $roomStep): array
    {
        $rules = [
            'rooms' => ['required', 'array', 'min:1', 'max:10'],
        ];

        $roomTypes = collect($roomStep['room_types'] ?? [])->pluck('value')->filter()->values()->all();
        $rules['rooms.*.type'] = ['required', 'string', Rule::in($roomTypes)];

        foreach (($roomStep['room_fields'] ?? []) as $field) {
            $rules['rooms.*.' . $field['name']] = $this->buildRulesForField($field);
        }

        return $rules;
    }

    private function getDynamicFields(): array
    {
        $steps = config('request-flow.steps', []);
        $fields = [];
        $selectedCategory = request()->input('service_category', '');

        foreach ($steps as $step) {
            $stepType = $step['type'] ?? '';

            // airco_rooms: process its regular fields (outdoor unit, house age, timing)
            if ($stepType === 'airco_rooms') {
                $condition = $step['condition'] ?? null;
                if ($condition !== null) {
                    $allowedCategories = $condition['service_categories'] ?? [];
                    if (!empty($allowedCategories) && !in_array($selectedCategory, $allowedCategories, true)) {
                        continue;
                    }
                }
                foreach (($step['fields'] ?? []) as $field) {
                    $fields[] = $field;
                }
                continue;
            }

            if ($stepType !== 'fields') {
                continue;
            }

            $condition = $step['condition'] ?? null;
            if ($condition !== null) {
                $allowedCategories = $condition['service_categories'] ?? [];
                if (!empty($allowedCategories) && !in_array($selectedCategory, $allowedCategories, true)) {
                    continue; // Skip this conditional step — doesn't match submitted category
                }
            }

            foreach (($step['fields'] ?? []) as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    private function buildRulesForField(array $field): array
    {
        $rules = [];

        if ($field['required'] ?? false) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        $type = $field['type'] ?? 'text';

        if (in_array($field['name'], ['brand', 'device_model'], true)) {
            $rules = ['nullable', 'string', 'max:255'];

            if (!request()->boolean('unknown_device_details')) {
                $rules[0] = 'required';
            }

            return $rules;
        }

        if ($type === 'select') {
            $rules[] = 'string';

            $allowedValues = collect($field['options'] ?? [])
                ->pluck('value')
                ->toArray();

            if (!empty($allowedValues)) {
                $rules[] = Rule::in($allowedValues);
            }

            return $rules;
        }

        if ($type === 'email') {
            $rules[] = 'email:rfc';
            $rules[] = 'max:254';

            return $rules;
        }

        if ($type === 'tel') {
            $rules[] = 'string';
            $rules[] = 'max:50';
            $rules[] = 'regex:/^[0-9+\s().-]+$/';

            return $rules;
        }

        if ($type === 'checkbox') {
            $rules[] = 'boolean';

            return $rules;
        }

        if ($type === 'textarea') {
            $rules[] = 'string';
            $rules[] = 'max:5000';

            return $rules;
        }

        if ($type === 'number') {
            $rules[] = ($field['decimal'] ?? false) ? 'numeric' : 'integer';
            $rules[] = 'min:' . ($field['min'] ?? 0);

            if (isset($field['max'])) {
                $rules[] = 'max:' . $field['max'];
            }

            return $rules;
        }

        $rules[] = 'string';
        $rules[] = 'max:255';

        return $rules;
    }
}
