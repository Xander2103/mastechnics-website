<?php

namespace App\Services\Hvac;

use App\Models\HvacBrand;
use App\Models\HvacProduct;
use App\Models\HvacSupplier;

/**
 * Data-quality definitions of the product catalog: which active products
 * miss a price, stock, electrical data, pipe limits or compatibility, and
 * which are complete enough to be recommended. One definition, shared by the
 * product dashboard and the quote-settings overview.
 */
class HvacCatalogQuality
{
    public const UNIT_TYPES = ['indoor_unit', 'outdoor_unit', 'single_split_set', 'multi_split_outdoor'];

    /**
     * Reusable query constraints, keyed by quality filter.
     *
     * @return array<string, \Closure>
     */
    public function scopes(): array
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

    /**
     * Dashboard counts over the ACTIVE catalog.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $scopes = $this->scopes();

        $activeBase = fn () => HvacProduct::query()->where('is_active', true);
        $unitCount = $activeBase()->whereIn('product_type', self::UNIT_TYPES)->count();
        $readyCount = $activeBase()->tap($scopes['ready'])->count();

        return [
            'brands'             => HvacBrand::where('is_active', true)->count(),
            'suppliers'          => HvacSupplier::where('is_active', true)->count(),
            'active_products'    => $activeBase()->count(),
            'missing_price'      => $activeBase()->tap($scopes['missing_price'])->count(),
            'missing_stock'      => $activeBase()->tap($scopes['missing_stock'])->count(),
            'missing_electrical' => $activeBase()->tap($scopes['missing_electrical'])->count(),
            'missing_pipe'       => $activeBase()->tap($scopes['missing_pipe'])->count(),
            'missing_compat'     => $activeBase()->tap($scopes['missing_compat'])->count(),
            'ready'              => $readyCount,
            'blocked'            => max(0, $unitCount - $readyCount),
        ];
    }
}
