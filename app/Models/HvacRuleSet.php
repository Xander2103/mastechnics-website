<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HvacRuleSet extends Model
{
    protected $fillable = [
        'name', 'version', 'status', 'effective_from',
        'configuration', 'created_by', 'approved_by',
    ];

    protected $casts = [
        'configuration'  => 'array',
        'effective_from' => 'date',
    ];

    public function calculations(): HasMany
    {
        return $this->hasMany(HvacCalculation::class);
    }

    public function validations(): HasMany
    {
        return $this->hasMany(HvacRuleValidation::class);
    }
}
