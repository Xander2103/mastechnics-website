<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 23 — "Snelle inschatting". Existing installs already carry persisted
 * rule sets that were seeded before the `quick_estimate` key existed.
 *
 * The key is ADDED to active and draft rule sets that do not have it yet.
 * Nothing that exists is changed: no detailed-calculation rule reads this
 * key, validations are per rule key, and historical calculations embed their
 * own copy of the configuration — so no past result or snapshot moves.
 * Archived sets are left exactly as they were.
 */
return new class extends Migration
{
    public function up(): void
    {
        $quickEstimate = config('hvac.default_rule_set.configuration.quick_estimate');
        if (! is_array($quickEstimate)) {
            return;
        }

        $ruleSets = DB::table('hvac_rule_sets')
            ->whereIn('status', ['active', 'draft'])
            ->get(['id', 'configuration']);

        foreach ($ruleSets as $ruleSet) {
            $configuration = json_decode((string) $ruleSet->configuration, true);
            if (! is_array($configuration) || array_key_exists('quick_estimate', $configuration)) {
                continue;
            }

            $configuration['quick_estimate'] = $quickEstimate;

            DB::table('hvac_rule_sets')
                ->where('id', $ruleSet->id)
                ->update(['configuration' => json_encode($configuration)]);
        }
    }

    public function down(): void
    {
        // Deliberately empty: removing the key could strip values Martin has
        // confirmed or adjusted since. The key is inert for every other rule.
    }
};
