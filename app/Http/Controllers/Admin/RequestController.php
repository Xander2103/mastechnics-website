<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerRequest;
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
        ]);
    }
}