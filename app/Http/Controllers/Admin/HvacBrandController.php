<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HvacBrand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HvacBrandController extends Controller
{
    public function index(): View
    {
        return view('admin.hvac.brands.index', [
            'brands' => HvacBrand::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        HvacBrand::firstOrCreate(
            ['slug' => Str::slug($data['name'])],
            ['name' => $data['name'], 'is_active' => true]
        );

        return back()->with('success', 'hvac_brand_saved');
    }

    public function toggle(HvacBrand $brand): RedirectResponse
    {
        $brand->update(['is_active' => ! $brand->is_active]);

        return back()->with('success', 'hvac_brand_saved');
    }
}
