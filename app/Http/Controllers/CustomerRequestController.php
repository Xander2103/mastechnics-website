<?php

namespace App\Http\Controllers;

use App\Models\CustomerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerRequestController extends Controller
{
    public function store(Request $request, string $locale): RedirectResponse
    {
        $validatedData = $request->validate([
            'service_slug' => ['required', 'string', 'max:255'],
            'request_type' => ['required', 'string', 'max:255'],

            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],

            'brand' => ['nullable', 'string', 'max:255'],
            'device_model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],

            'description' => ['required', 'string', 'max:5000'],
        ]);

        CustomerRequest::create([
            'locale' => $locale,
            'service_slug' => $validatedData['service_slug'],
            'request_type' => $validatedData['request_type'],

            'customer_name' => $validatedData['customer_name'],
            'customer_email' => $validatedData['customer_email'],
            'customer_phone' => $validatedData['customer_phone'] ?? null,

            'brand' => $validatedData['brand'] ?? null,
            'device_model' => $validatedData['device_model'] ?? null,
            'serial_number' => $validatedData['serial_number'] ?? null,
            'unknown_device_details' => $request->boolean('unknown_device_details'),

            'description' => $validatedData['description'],
            'status' => 'new',

            'metadata' => [
                'source' => 'smart_request_form',
            ],
        ]);

        return back()->with('success', 'request_created');
    }
}