<?php

namespace App\Http\Controllers;

use App\Models\CustomerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Mail\NewCustomerRequestMail;
use Illuminate\Support\Facades\Mail;
use App\Mail\CustomerRequestConfirmationMail;

class CustomerRequestController extends Controller
{
    public function store(Request $request, string $locale): RedirectResponse
    {
        $serviceCategories = collect(config('request-flow.service_categories', []));
        $allowedCategoryValues = $serviceCategories->pluck('value')->toArray();

        $dynamicFields = $this->getDynamicFields();

        $rules = [
            'service_category' => [
                'required',
                'string',
                Rule::in($allowedCategoryValues),
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

        $validatedData = $request->validate($rules);

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

        $customerRequest = CustomerRequest::create([
            'locale'       => $locale,
            'service_slug' => $serviceSlug,
            'request_type' => $derivedRequestType,
            'source'       => 'website',

            // New workflow fields
            'service_category' => $submittedCategory,
            'urgency_level'    => $answers['urgency_level'] ?? null,
            'preferred_time'   => $answers['preferred_time'] ?? $answers['availability'] ?? null,
            'customer_message' => $answers['description'] ?? null,
            'ai_summary'                => null,
            'ai_detected_missing_fields' => null,

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

        $notificationEmails = config('admin.notification_emails', []);

        foreach ($notificationEmails as $email) {
            Mail::to($email)->send(new NewCustomerRequestMail($customerRequest));
        }

        Mail::to($customerRequest->customer_email)->send(
            new CustomerRequestConfirmationMail($customerRequest)
        );

        return back()->with('success', 'request_created');
    }

    private function getDynamicFields(): array
    {
        $steps = config('request-flow.steps', []);
        $fields = [];
        $selectedCategory = request()->input('service_category', '');

        foreach ($steps as $step) {
            if (($step['type'] ?? '') !== 'fields') {
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
