<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    protected $fillable = [
        'quote_id',
        'position',
        'description',
        'quantity',
        'unit_price_excl_vat',
        'vat_rate',
        'line_total_excl_vat',
        'line_vat_amount',
        'line_total_incl_vat',
    ];

    protected $casts = [
        'quantity'             => 'decimal:2',
        'unit_price_excl_vat'  => 'decimal:2',
        'vat_rate'             => 'decimal:2',
        'line_total_excl_vat'  => 'decimal:2',
        'line_vat_amount'      => 'decimal:2',
        'line_total_incl_vat'  => 'decimal:2',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public static function calculateLine(float $quantity, float $unitPrice, float $vatRate): array
    {
        $excl = round($quantity * $unitPrice, 2);
        $vat  = round($excl * $vatRate / 100, 2);
        $incl = round($excl + $vat, 2);

        return [
            'line_total_excl_vat' => $excl,
            'line_vat_amount'     => $vat,
            'line_total_incl_vat' => $incl,
        ];
    }
}
