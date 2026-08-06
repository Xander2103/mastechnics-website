<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HvacProductCompatibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HvacCompatibilityController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'parent_product_id'             => ['required', 'integer', Rule::exists('hvac_products', 'id')],
            'compatible_product_id'         => ['required', 'integer', 'different:parent_product_id', Rule::exists('hvac_products', 'id')],
            'compatibility_type'            => ['required', Rule::in(HvacProductCompatibility::TYPES)],
            'minimum_connected_capacity_kw' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'maximum_connected_capacity_kw' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'maximum_units'                 => ['nullable', 'integer', 'min:1', 'max:20'],
            'notes'                         => ['nullable', 'string', 'max:1000'],
        ]);

        HvacProductCompatibility::updateOrCreate(
            [
                'parent_product_id'     => $data['parent_product_id'],
                'compatible_product_id' => $data['compatible_product_id'],
                'compatibility_type'    => $data['compatibility_type'],
            ],
            $data + ['is_active' => true]
        );

        return back()->with('success', 'hvac_compatibility_saved');
    }

    public function toggle(HvacProductCompatibility $compatibility): RedirectResponse
    {
        $compatibility->update(['is_active' => ! $compatibility->is_active]);

        return back()->with('success', 'hvac_compatibility_saved');
    }
}
