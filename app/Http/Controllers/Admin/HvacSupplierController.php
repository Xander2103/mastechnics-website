<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HvacImportCatalog;
use App\Models\HvacSupplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HvacSupplierController extends Controller
{
    public function index(): View
    {
        return view('admin.hvac.suppliers.index', [
            'suppliers' => HvacSupplier::query()
                ->withCount([
                    'products',
                    'products as active_products_count' => fn ($q) => $q->where('is_active', true),
                    'catalogs as active_catalogs_count' => fn ($q) => $q->where('status', '!=', HvacImportCatalog::STATUS_ARCHIVED),
                ])
                ->with(['catalogs' => fn ($q) => $q->orderByDesc('imported_at')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:150'],
            'code'  => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        HvacSupplier::create($data + ['is_active' => true]);

        return back()->with('success', 'hvac_supplier_saved');
    }

    public function toggle(HvacSupplier $supplier): RedirectResponse
    {
        $supplier->update(['is_active' => ! $supplier->is_active]);

        return back()->with('success', 'hvac_supplier_saved');
    }
}
