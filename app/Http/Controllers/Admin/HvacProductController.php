<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HvacBrand;
use App\Models\HvacProduct;
use App\Models\HvacSupplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HvacProductController extends Controller
{
    private const UNIT_TYPES = ['indoor_unit', 'outdoor_unit', 'single_split_set', 'multi_split_outdoor'];

    /**
     * Reusable quality constraints — also power the dashboard counts.
     */
    private function qualityScopes(): array
    {
        $noCompat = fn ($q) => $q->whereIn('product_type', ['indoor_unit', 'outdoor_unit', 'multi_split_outdoor'])
            ->whereDoesntHave('compatibilities')
            ->whereDoesntHave('reverseCompatibilities');

        return [
            'missing_price'      => fn ($q) => $q->whereNull('default_sale_price_excl_vat')->whereNull('purchase_price_excl_vat'),
            'missing_stock'      => fn ($q) => $q->whereNull('stock_quantity'),
            'missing_electrical' => fn ($q) => $q->whereIn('product_type', self::UNIT_TYPES)
                ->whereNull('supported_voltage')->whereNull('required_breaker_a'),
            'missing_pipe'       => fn ($q) => $q->whereIn('product_type', self::UNIT_TYPES)
                ->whereNull('maximum_pipe_length_m'),
            'missing_compat'     => $noCompat,
            'ready'              => fn ($q) => $q->whereIn('product_type', self::UNIT_TYPES)
                ->whereNotNull('cooling_capacity_kw')
                ->where(fn ($p) => $p->whereNotNull('default_sale_price_excl_vat')->orWhereNotNull('purchase_price_excl_vat'))
                ->whereNotNull('maximum_pipe_length_m')
                ->whereNotNull('maximum_height_difference_m')
                ->where(fn ($p) => $p
                    ->where('product_type', 'single_split_set')
                    ->orWhereHas('compatibilities')
                    ->orWhereHas('reverseCompatibilities')),
        ];
    }

    public function index(Request $request): View
    {
        $scopes = $this->qualityScopes();

        $activeBase = fn () => HvacProduct::query()->where('is_active', true);
        $unitCount = $activeBase()->whereIn('product_type', self::UNIT_TYPES)->count();
        $readyCount = $activeBase()->tap($scopes['ready'])->count();

        $quality = [
            'brands'             => \App\Models\HvacBrand::where('is_active', true)->count(),
            'suppliers'          => \App\Models\HvacSupplier::where('is_active', true)->count(),
            'active_products'    => $activeBase()->count(),
            'missing_price'      => $activeBase()->tap($scopes['missing_price'])->count(),
            'missing_stock'      => $activeBase()->tap($scopes['missing_stock'])->count(),
            'missing_electrical' => $activeBase()->tap($scopes['missing_electrical'])->count(),
            'missing_pipe'       => $activeBase()->tap($scopes['missing_pipe'])->count(),
            'missing_compat'     => $activeBase()->tap($scopes['missing_compat'])->count(),
            'ready'              => $readyCount,
            'blocked'            => max(0, $unitCount - $readyCount),
        ];

        $products = HvacProduct::query()
            ->with(['brand', 'supplier'])
            ->when(
                $request->filled('quality') && isset($scopes[$request->string('quality')->toString()]),
                fn ($q) => $q->where('is_active', true)->tap($scopes[$request->string('quality')->toString()])
            )
            ->when($request->filled('type'), fn ($q) => $q->where('product_type', $request->string('type')))
            ->when($request->filled('brand'), fn ($q) => $q->where('hvac_brand_id', $request->integer('brand')))
            ->when($request->string('active')->toString() === '1', fn ($q) => $q->where('is_active', true))
            ->when($request->string('active')->toString() === '0', fn ($q) => $q->where('is_active', false))
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

        return view('admin.hvac.products.index', [
            'products' => $products,
            'brands'   => HvacBrand::orderBy('name')->get(),
            'types'    => HvacProduct::PRODUCT_TYPES,
            'quality'  => $quality,
        ]);
    }

    public function create(): View
    {
        return view('admin.hvac.products.form', [
            'product'   => new HvacProduct(['is_active' => true]),
            'brands'    => HvacBrand::orderBy('name')->get(),
            'suppliers' => HvacSupplier::orderBy('name')->get(),
            'types'     => HvacProduct::PRODUCT_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $product = HvacProduct::create($this->validated($request));

        return redirect()
            ->route('admin.hvac.products.edit', $product)
            ->with('success', 'hvac_product_saved');
    }

    public function edit(HvacProduct $product): View
    {
        $product->load(['brand', 'supplier', 'compatibilities.compatible.brand', 'reverseCompatibilities.parent.brand']);

        return view('admin.hvac.products.form', [
            'product'   => $product,
            'brands'    => HvacBrand::orderBy('name')->get(),
            'suppliers' => HvacSupplier::orderBy('name')->get(),
            'types'     => HvacProduct::PRODUCT_TYPES,
            'compatCandidates' => HvacProduct::active()
                ->where('id', '!=', $product->id)
                ->orderBy('model')
                ->get(['id', 'model', 'sku', 'product_type']),
        ]);
    }

    public function update(Request $request, HvacProduct $product): RedirectResponse
    {
        $product->update($this->validated($request, $product));

        return redirect()
            ->route('admin.hvac.products.edit', $product)
            ->with('success', 'hvac_product_saved');
    }

    public function toggleActive(HvacProduct $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', $product->is_active ? 'hvac_product_activated' : 'hvac_product_deactivated');
    }

    public function duplicate(HvacProduct $product): RedirectResponse
    {
        $copy = $product->replicate();
        $copy->sku = $product->sku . '-KOPIE';
        $copy->is_active = false;

        // Keep the supplier+sku combination unique.
        $suffix = 1;
        while (HvacProduct::where('hvac_supplier_id', $copy->hvac_supplier_id)->where('sku', $copy->sku)->exists()) {
            $suffix++;
            $copy->sku = $product->sku . '-KOPIE' . $suffix;
        }

        $copy->save();

        return redirect()
            ->route('admin.hvac.products.edit', $copy)
            ->with('success', 'hvac_product_duplicated');
    }

    private function validated(Request $request, ?HvacProduct $product = null): array
    {
        return $request->validate([
            'hvac_brand_id'    => ['required', 'integer', Rule::exists('hvac_brands', 'id')],
            'hvac_supplier_id' => ['nullable', 'integer', Rule::exists('hvac_suppliers', 'id')],
            'sku'              => [
                'required', 'string', 'max:100',
                Rule::unique('hvac_products', 'sku')
                    ->where(fn ($q) => $q->where('hvac_supplier_id', $request->input('hvac_supplier_id')))
                    ->ignore($product?->id),
            ],
            'model'                          => ['required', 'string', 'max:255'],
            'name'                           => ['required', 'string', 'max:255'],
            'product_type'                   => ['required', Rule::in(HvacProduct::PRODUCT_TYPES)],
            'cooling_capacity_kw'            => ['nullable', 'numeric', 'min:0', 'max:100'],
            'heating_capacity_kw'            => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minimum_capacity_kw'            => ['nullable', 'numeric', 'min:0', 'max:100'],
            'maximum_capacity_kw'            => ['nullable', 'numeric', 'min:0', 'max:100'],
            'nominal_input_power_w'          => ['nullable', 'integer', 'min:0'],
            'supported_voltage'              => ['nullable', 'string', 'max:50'],
            'supported_phase'                => ['nullable', 'string', 'max:20'],
            'required_breaker_a'             => ['nullable', 'integer', 'min:0', 'max:200'],
            'required_cable'                 => ['nullable', 'string', 'max:50'],
            'liquid_pipe_diameter'           => ['nullable', 'string', 'max:20'],
            'gas_pipe_diameter'              => ['nullable', 'string', 'max:20'],
            'maximum_pipe_length_m'          => ['nullable', 'numeric', 'min:0', 'max:500'],
            'maximum_pipe_length_per_unit_m' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'maximum_height_difference_m'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'maximum_connected_indoor_units' => ['nullable', 'integer', 'min:1', 'max:20'],
            'purchase_price_excl_vat'        => ['nullable', 'numeric', 'min:0'],
            'default_sale_price_excl_vat'    => ['nullable', 'numeric', 'min:0'],
            'stock_quantity'                 => ['nullable', 'integer', 'min:0'],
            'lead_time_days'                 => ['nullable', 'integer', 'min:0', 'max:365'],
            'sound_level_db'                 => ['nullable', 'numeric', 'min:0', 'max:120'],
            'seer'                           => ['nullable', 'numeric', 'min:0', 'max:15'],
            'scop'                           => ['nullable', 'numeric', 'min:0', 'max:10'],
            'wifi_included'                  => ['nullable', 'boolean'],
            'is_active'                      => ['nullable', 'boolean'],
        ]);
    }
}
