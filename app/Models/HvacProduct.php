<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HvacProduct extends Model
{
    public const PRODUCT_TYPES = [
        'indoor_unit',
        'outdoor_unit',
        'single_split_set',
        'multi_split_outdoor',
        'wall_bracket',
        'floor_support',
        'roof_support',
        'pipe',
        'pipe_set',
        'trunking',
        'drain_hose',
        'condensate_pump',
        'electrical_accessory',
        'vibration_damper',
        'wifi_module',
        'refrigerant',
        'installation_accessory',
        'other_accessory',
    ];

    protected $fillable = [
        'hvac_supplier_id', 'hvac_brand_id', 'sku', 'model', 'name', 'product_type',
        'cooling_capacity_kw', 'heating_capacity_kw', 'minimum_capacity_kw', 'maximum_capacity_kw',
        'nominal_input_power_w', 'supported_voltage', 'supported_phase',
        'required_breaker_a', 'required_cable', 'liquid_pipe_diameter', 'gas_pipe_diameter',
        'maximum_pipe_length_m', 'maximum_pipe_length_per_unit_m', 'maximum_height_difference_m',
        'maximum_connected_indoor_units', 'purchase_price_excl_vat', 'default_sale_price_excl_vat',
        'stock_quantity', 'lead_time_days', 'sound_level_db', 'seer', 'scop', 'wifi_included',
        'is_active', 'metadata',
    ];

    protected $casts = [
        'cooling_capacity_kw'            => 'float',
        'heating_capacity_kw'            => 'float',
        'minimum_capacity_kw'            => 'float',
        'maximum_capacity_kw'            => 'float',
        'maximum_pipe_length_m'          => 'float',
        'maximum_pipe_length_per_unit_m' => 'float',
        'maximum_height_difference_m'    => 'float',
        'purchase_price_excl_vat'        => 'float',
        'default_sale_price_excl_vat'    => 'float',
        'sound_level_db'                 => 'float',
        'seer'                           => 'float',
        'scop'                           => 'float',
        'wifi_included'                  => 'boolean',
        'is_active'                      => 'boolean',
        'metadata'                       => 'array',
    ];

    protected static function booted(): void
    {
        // Historically referenced products must never be hard-deleted;
        // deactivate them instead. The DB foreign keys restrict as well —
        // this guard just gives a clear error before the DB does.
        static::deleting(function (HvacProduct $product): void {
            if ($product->isReferenced()) {
                throw new \RuntimeException(
                    'Dit product wordt gebruikt in historische aanbevelingen of compatibiliteiten en kan niet verwijderd worden. Deactiveer het in plaats daarvan.'
                );
            }
        });
    }

    public function isReferenced(): bool
    {
        return $this->recommendationItems()->exists()
            || $this->compatibilities()->exists()
            || $this->reverseCompatibilities()->exists();
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(HvacBrand::class, 'hvac_brand_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(HvacSupplier::class, 'hvac_supplier_id');
    }

    public function importCatalogs(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(HvacImportCatalog::class, 'hvac_import_catalog_product')
            ->withPivot(['source_row', 'imported_at', 'source_price', 'source_stock', 'source_lead_time'])
            ->withTimestamps();
    }

    /**
     * Products the engine may pick for NEW recommendations: active, and not
     * living exclusively in archived product lists. Manually created products
     * (no list at all) stay selectable; historical references are unaffected.
     */
    public function scopeSelectable($query)
    {
        return $query->where('is_active', true)->where(function ($q) {
            $q->whereDoesntHave('importCatalogs')
                ->orWhereHas('importCatalogs', fn ($c) => $c->where('status', '!=', HvacImportCatalog::STATUS_ARCHIVED));
        });
    }

    /** Import-time review flag (derived capacity, unknown price meaning, …). */
    public function needsImportReview(): bool
    {
        return (bool) ($this->metadata['import']['needs_review'] ?? false);
    }

    public function compatibilities(): HasMany
    {
        return $this->hasMany(HvacProductCompatibility::class, 'parent_product_id');
    }

    public function reverseCompatibilities(): HasMany
    {
        return $this->hasMany(HvacProductCompatibility::class, 'compatible_product_id');
    }

    public function recommendationItems(): HasMany
    {
        return $this->hasMany(HvacRecommendationItem::class, 'hvac_product_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
