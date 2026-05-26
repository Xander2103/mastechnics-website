<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RequestController extends Controller
{
    public function index(): View
    {
        $customerRequests = CustomerRequest::query()
            ->latest()
            ->get();

        return view('admin.requests.index', [
            'customerRequests' => $customerRequests,
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