<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HvacRuleValidation extends Model
{
    protected $fillable = [
        'hvac_rule_set_id', 'rule_key', 'status', 'note', 'validated_by', 'validated_at',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
    ];

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(HvacRuleSet::class, 'hvac_rule_set_id');
    }
}
