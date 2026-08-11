<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Reusable mapping recipe for one supplier's file layout (guided import).
 * Contains only the mapping — the uploaded file is never stored here.
 */
class HvacMappingProfile extends Model
{
    protected $fillable = [
        'name',
        'supplier_name',
        'worksheet_name_pattern',
        'header_row',
        'column_map',
        'decimal_format',
        'currency_format',
        'delimiter',
        'category_filter',
        'price_semantics',
        'source_headers',
        'type_fallback',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'column_map'      => 'array',
            'category_filter' => 'array',
            'price_semantics' => 'array',
            'source_headers'  => 'array',
            'header_row'      => 'integer',
            'is_active'       => 'boolean',
        ];
    }
}
