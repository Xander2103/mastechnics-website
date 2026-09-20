<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit row for one value changed in a DRAFT rule set from the admin screen:
 * who changed which setting, from what to what, and when. Append-only.
 */
class HvacRuleChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'hvac_rule_set_id', 'rule_key', 'old_value', 'new_value', 'changed_by', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(HvacRuleSet::class, 'hvac_rule_set_id');
    }
}
