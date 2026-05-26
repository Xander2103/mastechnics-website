<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RequestController extends Controller
{
    public function index(Request $request): View
    {
        $statuses = $this->getStatuses();

        $services = collect(config('services'))
            ->filter(fn(array $service): bool => $service['is_active'] ?? false)
            ->map(function (array $service): array {
                return $service['translations']['nl'] ?? reset($service['translations']);
            })
            ->values();

        $requestTypes = collect(config('request-flow.request_types', []))
            ->mapWithKeys(function (array $requestType): array {
                return [
                    $requestType['value'] => $requestType['labels']['nl'] ?? $requestType['value'],
                ];
            })
            ->toArray();

        $customerRequests = CustomerRequest::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('customer_name', 'LIKE', "%{$search}%")
                        ->orWhere('customer_email', 'LIKE', "%{$search}%")
                        ->orWhere('customer_phone', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('service_slug'), function ($query) use ($request): void {
                $query->where('service_slug', $request->string('service_slug')->toString());
            })
            ->when($request->filled('request_type'), function ($query) use ($request): void {
                $query->where('request_type', $request->string('request_type')->toString());
            })
            ->when($request->filled('date_from'), function ($query) use ($request): void {
                $query->whereDate('created_at', '>=', $request->string('date_from')->toString());
            })
            ->when($request->filled('date_to'), function ($query) use ($request): void {
                $query->whereDate('created_at', '<=', $request->string('date_to')->toString());
            })
            ->latest()
            ->get();

        return view('admin.requests.index', [
            'customerRequests' => $customerRequests,
            'statuses' => $statuses,
            'services' => $services,
            'requestTypes' => $requestTypes,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString(),
                'service_slug' => $request->string('service_slug')->toString(),
                'request_type' => $request->string('request_type')->toString(),
                'date_from' => $request->string('date_from')->toString(),
                'date_to' => $request->string('date_to')->toString(),
            ],
        ]);
    }

    public function show(CustomerRequest $customerRequest): View
    {
        return view('admin.requests.show', [
            'customerRequest' => $customerRequest,
            'statuses' => $this->getStatuses(),
        ]);
    }

    public function updateStatus(Request $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $validatedData = $request->validate([
            'status' => ['required', 'string', 'in:new,contacted,planned,done,cancelled'],
        ]);

        $customerRequest->update([
            'status' => $validatedData['status'],
        ]);

        return back()->with('success', 'status_updated');
    }

    private function getStatuses(): array
    {
        return [
            'new' => 'Nieuw',
            'contacted' => 'Gecontacteerd',
            'planned' => 'Ingepland',
            'done' => 'Afgewerkt',
            'cancelled' => 'Geannuleerd',
        ];
    }
}
