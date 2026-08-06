<?php

namespace App\Services\Hvac;

use App\Models\HvacRuleSet;

/**
 * Resolves the active rule set. Seeds version 1 from config/hvac.php on
 * first use so every calculation always references a persisted, versioned
 * rule set — never live config values.
 */
class HvacRuleSetResolver
{
    public function active(): HvacRuleSet
    {
        $active = HvacRuleSet::where('status', 'active')
            ->orderByDesc('version')
            ->first();

        if ($active !== null) {
            return $active;
        }

        $default = config('hvac.default_rule_set');

        return HvacRuleSet::firstOrCreate(
            [
                'name'    => $default['name'],
                'version' => $default['version'],
            ],
            [
                'status'         => 'active',
                'effective_from' => now()->toDateString(),
                'configuration'  => $default['configuration'],
                'created_by'     => 'system (config/hvac.php v1)',
            ]
        );
    }
}
