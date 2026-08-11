<?php

namespace App\Console\Commands;

use App\Models\HvacRuleSet;
use Illuminate\Console\Command;

/**
 * Creates the "Belgische residentiële koellast" v2 rule set as a DRAFT.
 *
 * Deliberately never activates it: Martin validates the v2 values in the
 * admin rules screen and activates the set there with explicit confirmation.
 * Historical calculations and the v1 rule set are never touched.
 */
class SeedHvacV2RuleSet extends Command
{
    protected $signature = 'hvac:seed-v2-rule-set';

    protected $description = 'Maakt de conceptregelset "Belgische residentiële koellast" (v2) aan — nooit automatisch actief';

    public function handle(): int
    {
        $definition = config('hvac.cooling_load_v2_rule_set');

        $existing = HvacRuleSet::where('name', $definition['name'])
            ->where('version', $definition['version'])
            ->first();

        if ($existing !== null) {
            $this->info(sprintf(
                'Regelset "%s" v%d bestaat al (status: %s) — niets gewijzigd.',
                $existing->name,
                $existing->version,
                $existing->status
            ));

            return self::SUCCESS;
        }

        $ruleSet = HvacRuleSet::create([
            'name'          => $definition['name'],
            'version'       => $definition['version'],
            'status'        => 'draft',
            'configuration' => $definition['configuration'],
            'created_by'    => 'system (config/hvac.php v2)',
        ]);

        $this->info(sprintf(
            'Conceptregelset "%s" v%d aangemaakt. Activeren kan uitsluitend via Admin → HVAC → Berekeningsregels, na validatie van de waarden.',
            $ruleSet->name,
            $ruleSet->version
        ));

        return self::SUCCESS;
    }
}
