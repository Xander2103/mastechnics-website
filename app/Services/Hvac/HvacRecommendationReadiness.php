<?php

namespace App\Services\Hvac;

use App\Models\HvacRecommendation;
use App\Models\HvacRuleSet;
use App\Models\HvacRuleValidation;

/**
 * Evaluates whether a recommendation is safe to approve for production:
 *
 * - technical: the selector proved compatibility and limits (candidate valid)
 * - price: every mandatory line carries a real price
 * - rules: every CRITICAL calculation rule of the used rule set is validated
 *
 * Demo/test recommendations (all equipment clearly TEST-marked) bypass only
 * the rule-validation gate so Martin can rehearse the flow with the test
 * catalog — the banner makes the test status unmistakable.
 */
class HvacRecommendationReadiness
{
    /**
     * @return array{
     *   demo: bool, technical_ok: bool, price_ok: bool, rules_ok: bool,
     *   ready: bool, state_label: string, blockers: string[]
     * }
     */
    public function evaluate(HvacRecommendation $recommendation): array
    {
        $blockers = [];

        $equipment = $recommendation->items->where('item_type', 'equipment');

        $demo = $equipment->isNotEmpty() && $equipment->every(
            fn ($item) => str_starts_with(strtolower((string) ($item->description ?? '')), 'test')
                || str_starts_with(strtolower((string) ($item->sku ?? '')), 'test')
        );

        $candidate = $recommendation->metadata['candidate'] ?? null;
        $technicalOk = (bool) ($candidate['valid'] ?? false);
        if (! $technicalOk) {
            $blockers[] = 'Technische validatie ontbreekt: compatibiliteit of limieten zijn niet aantoonbaar in orde (zie waarschuwingen).';
        }

        $priceOk = ! $recommendation->items->contains(
            fn ($item) => ($item->metadata['mandatory'] ?? true)
                && ($item->metadata['price_source'] ?? '') === 'missing'
        );
        if (! $priceOk) {
            $blockers[] = 'Minstens één verplichte regel heeft geen prijs uit de catalogus.';
        }

        $rulesOk = $this->criticalRulesValidated((int) $recommendation->calculation->hvac_rule_set_id);
        if (! $rulesOk && ! $demo) {
            $blockers[] = 'Niet alle kritieke berekeningsregels zijn gevalideerd (zie Berekeningsregels).';
        }

        $ready = $technicalOk && $priceOk && ($rulesOk || $demo);

        return [
            'demo'         => $demo,
            'technical_ok' => $technicalOk,
            'price_ok'     => $priceOk,
            'rules_ok'     => $rulesOk,
            'ready'        => $ready,
            'state_label'  => $this->stateLabel($recommendation->status, $technicalOk, $priceOk, $ready, $demo),
            'blockers'     => $ready ? [] : $blockers,
        ];
    }

    public function criticalRulesValidated(int $ruleSetId): bool
    {
        $validated = HvacRuleValidation::where('hvac_rule_set_id', $ruleSetId)
            ->where('status', 'validated')
            ->pluck('rule_key')
            ->all();

        // Only critical rules that actually exist in this rule set's
        // configuration apply: v1 sets must not be blocked by v2-only rules
        // and vice versa.
        $configuration = HvacRuleSet::find($ruleSetId)?->configuration ?? [];
        $applicable = array_filter(
            HvacRuleCatalog::criticalKeys(),
            fn (string $key) => HvacRuleCatalog::value($configuration, $key) !== null
        );

        return array_diff($applicable, $validated) === [];
    }

    private function stateLabel(string $status, bool $technicalOk, bool $priceOk, bool $ready, bool $demo): string
    {
        $suffix = $demo ? ' (testcatalogus)' : '';

        return match (true) {
            $status === 'approved'      => 'Goedgekeurd' . $suffix,
            $status === 'rejected'      => 'Afgewezen',
            $status === 'converted'     => 'Omgezet naar offerte' . $suffix,
            $status === 'manual_review' => 'Handmatige controle vereist',
            $ready                      => 'Klaar voor offerte' . $suffix,
            $technicalOk && $priceOk    => 'Prijs gevalideerd — regels nog niet gevalideerd',
            $technicalOk                => 'Technisch gevalideerd — prijs onvolledig',
            default                     => 'Concept — handmatige controle vereist',
        };
    }
}
