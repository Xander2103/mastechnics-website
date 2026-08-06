<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HvacCalculation extends Model
{
    protected $fillable = [
        'customer_request_id', 'hvac_rule_set_id',
        'normalized_input', 'result', 'warnings', 'status',
        'calculated_by', 'calculated_at',
        'manually_overridden_at', 'manually_overridden_by',
    ];

    protected $casts = [
        'normalized_input'       => 'array',
        'result'                 => 'array',
        'warnings'               => 'array',
        'calculated_at'          => 'datetime',
        'manually_overridden_at' => 'datetime',
    ];

    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class);
    }

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(HvacRuleSet::class, 'hvac_rule_set_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(HvacRecommendation::class);
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(HvacManualOverride::class)->latest('created_at');
    }
}
