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

        // The config seed only fills an EMPTY table. When the default set
        // exists but was archived (or is still a draft), silently reviving
        // it would calculate with rules nobody chose to be active.
        $existing = HvacRuleSet::where('name', $default['name'])
            ->where('version', $default['version'])
            ->first();

        if ($existing !== null) {
            throw new \RuntimeException(
                'Geen actieve regelset: de standaardregelset "' . $default['name'] . '" v' . $default['version']
                . ' heeft status "' . $existing->status . '". Activeer een regelset via Berekeningsregels.'
            );
        }

        return HvacRuleSet::create([
            'name'           => $default['name'],
            'version'        => $default['version'],
            'status'         => 'active',
            'effective_from' => now()->toDateString(),
            'configuration'  => $default['configuration'],
            'created_by'     => 'system (config/hvac.php v1)',
        ]);
    }
}
