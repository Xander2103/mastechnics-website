<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HvacProductCompatibility extends Model
{
    public const TYPES = [
        'indoor_outdoor',     // parent = outdoor unit, compatible = indoor unit (single split)
        'multi_split_indoor', // parent = multi-split outdoor, compatible = indoor unit
        'required_accessory', // parent = unit, compatible = accessory that must be added
    ];

    protected $fillable = [
        'parent_product_id', 'compatible_product_id', 'compatibility_type',
        'minimum_connected_capacity_kw', 'maximum_connected_capacity_kw',
        'maximum_units', 'notes', 'is_active',
    ];

    protected $casts = [
        'minimum_connected_capacity_kw' => 'float',
        'maximum_connected_capacity_kw' => 'float',
        'is_active'                     => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(HvacProduct::class, 'parent_product_id');
    }

    public function compatible(): BelongsTo
    {
        return $this->belongsTo(HvacProduct::class, 'compatible_product_id');
    }
}
