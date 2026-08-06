<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HvacRecommendation extends Model
{
    protected $fillable = [
        'hvac_calculation_id', 'option_type', 'status',
        'equipment_total_excl_vat', 'materials_total_excl_vat',
        'labor_total_excl_vat', 'travel_total_excl_vat',
        'subtotal_excl_vat', 'vat_rate', 'vat_amount', 'total_incl_vat',
        'margin_amount', 'margin_percentage',
        'explanation_nl', 'explanation_fr', 'explanation_en',
        'approved_by', 'approved_at', 'converted_quote_id',
    ];

    protected $casts = [
        'equipment_total_excl_vat' => 'float',
        'materials_total_excl_vat' => 'float',
        'labor_total_excl_vat'     => 'float',
        'travel_total_excl_vat'    => 'float',
        'subtotal_excl_vat'        => 'float',
        'vat_rate'                 => 'float',
        'vat_amount'               => 'float',
        'total_incl_vat'           => 'float',
        'margin_amount'            => 'float',
        'margin_percentage'        => 'float',
        'approved_at'              => 'datetime',
    ];

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(HvacCalculation::class, 'hvac_calculation_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(HvacRecommendationItem::class);
    }

    public function convertedQuote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'converted_quote_id');
    }
}
