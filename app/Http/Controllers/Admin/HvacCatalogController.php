<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HvacBrand;
use App\Models\HvacImportCatalog;
use App\Models\HvacProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Productlijsten": browsing the catalog per imported supplier list instead of
 * one flat product table. Read/rename/archive only — importing happens in the
 * guided wizard, products are managed on their own pages.
 */
class HvacCatalogController extends Controller
{
    public function show(Request $request, HvacImportCatalog $catalog): View
    {
        $products = $catalog->products()
            ->with(['brand', 'supplier'])
            ->when($request->filled('type'), fn ($q) => $q->where('product_type', $request->string('type')))
            ->when($request->filled('brand'), fn ($q) => $q->where('hvac_brand_id', $request->integer('brand')))
            ->when($request->string('active')->toString() === '1', fn ($q) => $q->where('is_active', true))
            ->when($request->string('active')->toString() === '0', fn ($q) => $q->where('is_active', false))
            ->when($request->boolean('missing_price'), fn ($q) => $q
                ->whereNull('default_sale_price_excl_vat')->whereNull('purchase_price_excl_vat'))
            ->when($request->boolean('needs_review'), fn ($q) => $q->where('metadata->import->needs_review', true))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->string('q') . '%';
                $q->where(fn ($sub) => $sub
                    ->where('sku', 'like', $term)
                    ->orWhere('model', 'like', $term)
                    ->orWhere('name', 'like', $term));
            })
            ->orderBy('product_type')
            ->orderBy('cooling_capacity_kw')
            ->paginate(25)
            ->withQueryString();

        return view('admin.hvac.catalogs.show', [
            'catalog'     => $catalog->load(['supplier', 'runs']),
            'products'    => $products,
            'brands'      => HvacBrand::orderBy('name')->get(),
            'types'       => HvacProduct::PRODUCT_TYPES,
            'activeCount' => $catalog->products()->where('is_active', true)->count(),
            'reviewCount' => $catalog->products()->where('metadata->import->needs_review', true)->count(),
        ]);
    }

    public function rename(Request $request, HvacImportCatalog $catalog): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150']], [
            'name.required' => 'Geef de productlijst een naam.',
        ]);

        $catalog->update(['name' => trim($data['name'])]);

        return back()->with('success', 'hvac_catalog_saved');
    }

    public function archive(HvacImportCatalog $catalog): RedirectResponse
    {
        $catalog->update([
            'status' => $catalog->isArchived()
                ? HvacImportCatalog::STATUS_ACTIVE
                : HvacImportCatalog::STATUS_ARCHIVED,
        ]);

        return back()->with('success', 'hvac_catalog_saved');
    }
}
