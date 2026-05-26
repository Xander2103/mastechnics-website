<?php

namespace App\Http\Controllers;

use App\Models\CustomerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerRequestController extends Controller
{
    public function store(Request $request, string $locale): RedirectResponse
    {
        $services = collect(config('services'))
            ->filter(fn (array $service): bool => $service['is_active'] ?? false)
            ->map(function (array $service) use ($locale): array {
                return $service['translations'][$locale] ?? $service['translations']['nl'];
            })
            ->values();

        $serviceSlugs = $services
            ->pluck('slug')
            ->toArray();

        $requestTypes = collect(config('request-flow.request_types', []));
        $requestTypeValues = $requestTypes
            ->pluck('value')
            ->toArray();

        $dynamicFields = $this->getDynamicFields();

        $rules = [
            'service_slug' => [
                'required',
                'string',
                Rule::in($serviceSlugs),
            ],
            'request_type' => [
                'required',
                'string',
                Rule::in($requestTypeValues),
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

        $selectedService = $services->firstWhere('slug', $validatedData['service_slug']);
        $selectedRequestType = $requestTypes->firstWhere('value', $validatedData['request_type']);

        $answers = [
            'service_slug' => $validatedData['service_slug'],
            'service_title' => $selectedService['title'] ?? $validatedData['service_slug'],
            'request_type' => $validatedData['request_type'],
            'request_type_label' => $this->getTranslatedLabel($selectedRequestType ?? [], $locale),
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
            'locale' => $locale,
            'service_slug' => $validatedData['service_slug'],
            'request_type' => $validatedData['request_type'],

            'customer_name' => $answers['customer_name'] ?? '',
            'customer_email' => $answers['customer_email'] ?? '',
            'customer_phone' => $answers['customer_phone'] ?? null,

            'brand' => $answers['brand'] ?? null,
            'device_model' => $answers['device_model'] ?? null,
            'serial_number' => $answers['serial_number'] ?? null,
            'unknown_device_details' => $answers['unknown_device_details'] ?? false,

            'description' => $answers['description'] ?? '',
            'status' => 'new',

            'metadata' => [
                'source' => 'smart_request_form',
                'service' => [
                    'slug' => $validatedData['service_slug'],
                    'title' => $selectedService['title'] ?? $validatedData['service_slug'],
                ],
                'request_type' => [
                    'value' => $validatedData['request_type'],
                    'label' => $this->getTranslatedLabel($selectedRequestType ?? [], $locale),
                ],
                'answers' => $answers,
            ],
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $uploadedFile) {
                $path = $uploadedFile->store('customer-requests', 'public');

                $customerRequest->attachments()->create([
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $uploadedFile->getMimeType(),
                    'size' => $uploadedFile->getSize(),
                ]);
            }
        }

        return back()->with('success', 'request_created');
    }

    private function getDynamicFields(): array
    {
        $steps = config('request-flow.steps', []);
        $fields = [];

        foreach ($steps as $step) {
            if (($step['type'] ?? '') !== 'fields') {
                continue;
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

        $rules[] = 'string';
        $rules[] = 'max:255';

        return $rules;
    }

    private function getTranslatedLabel(array $item, string $locale): string
    {
        return $item['labels'][$locale]
            ?? $item['labels']['nl']
            ?? $item['value']
            ?? '';
    }
}