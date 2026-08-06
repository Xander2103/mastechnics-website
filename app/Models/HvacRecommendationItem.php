<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HvacRecommendationItem extends Model
{
    protected $fillable = [
        'hvac_recommendation_id', 'hvac_product_id', 'item_type',
        'sku', 'description', 'quantity', 'unit',
        'purchase_unit_price', 'sale_unit_price', 'line_total', 'metadata',
    ];

    protected $casts = [
        'quantity'            => 'float',
        'purchase_unit_price' => 'float',
        'sale_unit_price'     => 'float',
        'line_total'          => 'float',
        'metadata'            => 'array',
    ];

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(HvacRecommendation::class, 'hvac_recommendation_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(HvacProduct::class, 'hvac_product_id');
    }
}
