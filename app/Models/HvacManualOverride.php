<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HvacManualOverride extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'hvac_calculation_id', 'field',
        'original_value', 'overridden_value',
        'reason', 'overridden_by', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(HvacCalculation::class, 'hvac_calculation_id');
    }
}
