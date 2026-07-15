<?php

namespace App\Http\Controllers;

use App\Models\CustomerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Mail\NewCustomerRequestMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\CustomerRequestConfirmationMail;

class CustomerRequestController extends Controller
{
    public function store(Request $request, string $locale): RedirectResponse
    {
        app()->setLocale($locale);

        $serviceCategories = collect(config('request-flow.service_categories', []));
        $allowedCategoryValues = $serviceCategories->pluck('value')->toArray();

        $dynamicFields = $this->getDynamicFields();

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

        // Rooms validation (only for airco_offerte)
        $categoryForRooms = $request->input('service_category', '');
        if ($categoryForRooms === 'airco_offerte') {
            $rules['rooms']                          = ['required', 'array', 'min:1', 'max:10'];
            $rules['rooms.*.type']                   = ['required', 'string', 'in:slaapkamer,woonkamer,bureau,keuken,zolderkamer,andere'];
            $rules['rooms.*.width']                  = ['required', 'numeric', 'min:1', 'max:50'];
            $rules['rooms.*.length']                 = ['required', 'numeric', 'min:1', 'max:50'];
            $rules['rooms.*.attic_or_flat_roof']     = ['nullable', 'string', 'in:yes,no'];
            $rules['rooms.*.large_windows']          = ['nullable', 'string', 'in:yes,no'];
        }

        $attributes = $this->buildValidationAttributes($dynamicFields, $locale);

        $validatedData = $request->validate($rules, [], $attributes);

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

            $answers[$fieldName] = $validatedData[$fieldName] ?? null;
        }

        // Store rooms for airco_offerte with server-side surface calculation
        if ($submittedCategory === 'airco_offerte' && $request->has('rooms')) {
            $processedRooms = [];
            foreach ($request->input('rooms', []) as $room) {
                $w = round((float) ($room['width']  ?? 0), 2);
                $l = round((float) ($room['length'] ?? 0), 2);
                $processedRooms[] = [
                    'type'               => $room['type']               ?? null,
                    'width'              => $w,
                    'length'             => $l,
                    'surface'            => ($w > 0 && $l > 0) ? round($w * $l, 1) : null,
                    'attic_or_flat_roof' => $room['attic_or_flat_roof'] ?? null,
                    'large_windows'      => $room['large_windows']      ?? null,
                ];
            }
            $answers['rooms'] = $processedRooms;
        }

        $customerRequest = CustomerRequest::create([
            'locale'       => $locale,
            'service_slug' => $serviceSlug,
            'request_type' => $derivedRequestType,
            'source'       => 'website',

            // New workflow fields
            'service_category' => $submittedCategory,
            'urgency_level'    => $answers['urgency_level'] ?? null,
            'customer_message' => $answers['description'] ?? null,
            'ai_summary'                => null,
            'ai_detected_missing_fields' => null,

            // Preferred time: text from most flows, or structured timing value for airco installation
            'preferred_time' => $answers['preferred_time']
                ?? $answers['airco_installation_timing']
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
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $uploadedFile) {
                $path = $uploadedFile->store('customer-requests', 'public');

                $customerRequest->attachments()->create([
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'path'          => $path,
                    'mime_type'     => $uploadedFile->getMimeType(),
                    'size'          => $uploadedFile->getSize(),
                ]);
            }
        }

        $customerRequest->load(['attachments', 'notes']);

        $notificationEmails = collect(config('admin.notification_emails', []))
            ->push(config('site.request_notification_email'))
            ->filter()
            ->unique()
            ->values();

        foreach ($notificationEmails as $email) {
            try {
                Mail::to($email)->send(new NewCustomerRequestMail($customerRequest));
            } catch (\Throwable $e) {
                Log::error('Failed to send new-customer-request notification email', [
                    'email' => $email,
                    'customer_request_id' => $customerRequest->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            Mail::to($customerRequest->customer_email)->send(
                new CustomerRequestConfirmationMail($customerRequest)
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send customer confirmation email', [
                'customer_request_id' => $customerRequest->id,
                'error' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'request_created');
    }

    private function buildValidationAttributes(array $dynamicFields, string $locale): array
    {
        $staticAttributes = [
            'service_category' => ['nl' => 'dienst', 'fr' => 'service', 'en' => 'service'],
            'attachments'       => ['nl' => 'bijlagen', 'fr' => 'pièces jointes', 'en' => 'attachments'],
        ];

        $attributes = [];
        foreach ($staticAttributes as $name => $labels) {
            $attributes[$name] = $labels[$locale] ?? $labels['nl'];
        }

        foreach ($dynamicFields as $field) {
            $label = $field['labels'][$locale] ?? $field['labels']['nl'] ?? $field['name'];
            $attributes[$field['name']] = Str::lower($label);
        }

        return $attributes;
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
            $rules[] = 'email';
            $rules[] = 'max:255';

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
            $rules[] = 'integer';
            $rules[] = 'min:0';

            return $rules;
        }

        $rules[] = 'string';
        $rules[] = 'max:255';

        return $rules;
    }

}
