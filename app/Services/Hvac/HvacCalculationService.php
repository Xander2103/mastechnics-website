<?php

namespace App\Services\Hvac;

use App\Models\CustomerRequest;
use App\Models\HvacCalculation;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates one deterministic pre-calculation run:
 * normalize → cooling load per room → capacity class → split/diversity →
 * pipe + electrical estimates → persisted, immutable snapshot.
 *
 * Recalculating never mutates history: the previous calculation rows are
 * marked superseded and a new row is created.
 */
class HvacCalculationService
{
    public function __construct(
        private readonly HvacRuleSetResolver $ruleSetResolver,
        private readonly HvacInputNormalizer $normalizer,
        private readonly CoolingLoadCalculator $loadCalculator,
        private readonly CapacityClassSelector $classSelector,
        private readonly PipeEstimator $pipeEstimator,
        private readonly ElectricalEstimator $electricalEstimator,
    ) {
    }

    public function run(CustomerRequest $request, ?string $adminEmail = null): HvacCalculation
    {
        $ruleSet = $this->ruleSetResolver->active();
        $rules = $ruleSet->configuration;

        $validation = $this->normalizer->fromCustomerRequest($request, $rules);

        return DB::transaction(function () use ($request, $ruleSet, $rules, $validation, $adminEmail) {
            HvacCalculation::where('customer_request_id', $request->id)
                ->whereIn('status', ['calculated', 'blocked'])
                ->update(['status' => 'superseded']);

            if (! $validation->isCalculable()) {
                return HvacCalculation::create([
                    'customer_request_id' => $request->id,
                    'hvac_rule_set_id'    => $ruleSet->id,
                    'normalized_input'    => $validation->input?->toArray() ?? [],
                    'result'              => null,
                    'warnings'            => [
                        'warnings' => $validation->warnings,
                        'blockers' => $validation->blockers,
                    ],
                    'status'        => 'blocked',
                    'calculated_by' => $adminEmail,
                    'calculated_at' => now(),
                ]);
            }

            $input = $validation->input;
            $roomResults = [];
            $totalLoadKw = 0.0;
            $anyManualReview = false;
            $extraWarnings = [];
            $anyV2 = false;
            $anyV2WindowDerived = false;

            foreach ($input->rooms as $room) {
                $load = $this->loadCalculator->calculateRoom($room, $rules);

                if (($load['method'] ?? 'simple_v1') === 'engineering_v2') {
                    $anyV2 = true;
                    $anyV2WindowDerived = $anyV2WindowDerived || ($load['window_area_assumed'] ?? false);
                }
                $class = $this->classSelector->select($load['final_kw'], $rules);
                $pipe = $this->pipeEstimator->estimate($room, $rules);
                $electrical = $this->electricalEstimator->forClass($class['class_kw'], $rules);

                if ($class['manual_review']) {
                    $anyManualReview = true;
                    $extraWarnings[] = [
                        'code'    => 'load_above_class_range',
                        'message' => "{$room->name}: berekende koellast ({$load['final_kw']} kW) ligt boven het klassenbereik — handmatige beoordeling vereist.",
                    ];
                }

                if ($pipe['exceeds_generic_threshold']) {
                    $extraWarnings[] = [
                        'code'    => 'pipe_threshold_exceeded',
                        'message' => "{$room->name}: geschatte equivalente leidinglengte ({$pipe['equivalent_length_m']} m) overschrijdt de generieke drempel. Controleer fabrikantlimieten.",
                    ];
                }

                $roomResults[] = [
                    'load'                    => $load,
                    'capacity_class_kw'       => $class['class_kw'],
                    'capacity_manual_review'  => $class['manual_review'],
                    'pipe'                    => $pipe,
                    'electrical'              => $electrical,
                ];

                $totalLoadKw += $load['final_kw'];
            }

            if ($anyV2) {
                if ($anyV2WindowDerived) {
                    $extraWarnings[] = [
                        'code'    => 'v2_window_area_derived',
                        'message' => 'De raamoppervlakte is afgeleid uit het raamtype (percentage van de vloeroppervlakte) — geen echte meting. Controleer dit bij grote glaspartijen.',
                    ];
                }
                $extraWarnings[] = [
                    'code'    => 'v2_shading_assumed',
                    'message' => sprintf(
                        'Zonwering wordt niet bevraagd in het formulier; er is gerekend met de regelset-aanname "%s".',
                        (string) ($rules['assumed_shading'] ?? 'none')
                    ),
                ];
                $extraWarnings[] = [
                    'code'    => 'v2_ach_assumed',
                    'message' => sprintf(
                        'Het luchtverversingsvoud (ACH %s) is een regelset-standaard, geen meting.',
                        (string) ($rules['ventilation_ach_default'] ?? 0.5)
                    ),
                ];
            }

            $system = $this->systemResult($input->splitType, $roomResults, $rules, round($totalLoadKw, 2));
            $system['vat'] = $this->vatSuggestion($input->houseOlderThan10y, $input->customerType, $rules);

            $warnings = array_merge($validation->warnings, $extraWarnings);
            if ($system['vat']['suggested_rate'] !== null
                && $system['vat']['suggested_rate'] !== $system['vat']['default_rate']) {
                $warnings[] = [
                    'code'    => 'reduced_vat_possible',
                    'message' => 'Mogelijk 6% btw van toepassing (woning ouder dan 10 jaar, particulier). Dit wordt NIET automatisch toegepast — handmatig bevestigen.',
                ];
            }

            return HvacCalculation::create([
                'customer_request_id' => $request->id,
                'hvac_rule_set_id'    => $ruleSet->id,
                'normalized_input'    => $input->toArray(),
                'result'              => [
                    'rule_set' => [
                        'id'            => $ruleSet->id,
                        'name'          => $ruleSet->name,
                        'version'       => $ruleSet->version,
                        'configuration' => $rules,
                    ],
                    'rooms'  => $roomResults,
                    'system' => $system,
                ],
                'warnings' => [
                    'warnings' => $warnings,
                    'blockers' => [],
                ],
                'status'        => $anyManualReview ? 'calculated' : 'calculated',
                'calculated_by' => $adminEmail,
                'calculated_at' => now(),
            ]);
        });
    }

    private function systemResult(string $splitType, array $roomResults, array $rules, float $totalLoadKw): array
    {
        $classes = array_map(
            fn (array $r) => $r['capacity_class_kw'],
            array_filter($roomResults, fn (array $r) => $r['capacity_class_kw'] !== null)
        );
        $sumOfClasses = round(array_sum($classes), 2);

        if ($splitType !== 'multi_split') {
            return [
                'split_type'            => $splitType,
                'total_load_kw'         => $totalLoadKw,
                'sum_of_classes_kw'     => $sumOfClasses,
                'diversity_factor'      => null,
                'estimated_outdoor_kw'  => $sumOfClasses,
                'indoor_unit_count'     => count($roomResults),
            ];
        }

        $count = count($roomResults);
        $factors = $rules['diversity_factors'] ?? [];
        $factor = (float) ($factors[(string) $count] ?? $factors['5_plus'] ?? 0.82);

        return [
            'split_type'            => $splitType,
            'total_load_kw'         => $totalLoadKw,
            'sum_of_classes_kw'     => $sumOfClasses,
            'diversity_factor'      => $factor,
            'estimated_outdoor_kw'  => round($sumOfClasses * $factor, 2),
            'indoor_unit_count'     => $count,
        ];
    }

    private function vatSuggestion(?string $houseOlderThan10y, string $customerType, array $rules): array
    {
        $pricing = $rules['pricing'] ?? [];
        $defaultRate = (float) ($pricing['default_vat_rate'] ?? 21.0);
        $reducedRate = (float) ($pricing['reduced_vat_rate'] ?? 6.0);

        $suggestReduced = $houseOlderThan10y === 'yes' && $customerType === 'residential';

        return [
            'default_rate'          => $defaultRate,
            'suggested_rate'        => $suggestReduced ? $reducedRate : $defaultRate,
            'requires_confirmation' => true,
            'note'                  => $suggestReduced
                ? 'Woning >10 jaar en particulier: 6% btw mogelijk — voorwaarden handmatig controleren en bevestigen.'
                : 'Standaard btw-tarief. Controleer of een verlaagd tarief van toepassing kan zijn.',
        ];
    }
}
