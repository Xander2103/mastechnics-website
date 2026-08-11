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
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'header_row' => 'integer',
            'is_active'  => 'boolean',
        ];
    }
}
