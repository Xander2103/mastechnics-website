<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HvacRuleValidation extends Model
{
    protected $fillable = [
        'hvac_rule_set_id', 'rule_key', 'status', 'note', 'validated_value', 'validated_by', 'validated_at',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
    ];

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(HvacRuleSet::class, 'hvac_rule_set_id');
    }

    /**
     * Canonical JSON encoding of a rule value, so a value read back from the
     * database compares equal to the one it was validated with (65 vs 65.0,
     * key order, …).
     */
    public static function encodeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode(self::normalize($value));
    }

    /**
     * True when this validation still applies to the given current value:
     * legacy rows without a stored value always apply.
     */
    public function appliesTo(mixed $currentValue): bool
    {
        if ($this->validated_value === null) {
            return true;
        }

        return $this->validated_value === self::encodeValue($currentValue);
    }

    private static function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $k => $v) {
                $normalized[$k] = self::normalize($v);
            }
            if (array_keys($normalized) !== range(0, count($normalized) - 1)) {
                ksort($normalized);
            }

            return $normalized;
        }

        if (is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return self::normalize($value + 0);
        }

        return $value;
    }
}
