<?php

namespace App\Services\Hvac;

use App\Models\HvacCalculation;
use App\Models\HvacRecommendation;
use Illuminate\Support\Facades\DB;

/**
 * Builds persisted recommendation options (Budget / Aanbevolen / Premium)
 * from VALID selector candidates only. Invalid candidates never become
 * approvable recommendations; if only one valid system exists there is one
 * option and no artificial differentiation.
 */
class HvacRecommendationBuilder
{
    public function __construct(
        private readonly ProductSelector $selector,
        private readonly AccessorySelector $accessorySelector,
        private readonly LaborEstimator $laborEstimator,
        private readonly HvacPricingService $pricingService,
    ) {
    }

    /**
     * @return array{recommendations: array, selection_warnings: array, invalid_candidates: array}
     */
    public function buildForCalculation(HvacCalculation $calculation): array
    {
        $selection = $this->selector->selectSystems($calculation);
        $rules = $calculation->result['rule_set']['configuration'] ?? [];
        $rooms = $calculation->result['rooms'] ?? [];

        $validCandidates = array_values(array_filter($selection['candidates'], fn ($c) => $c['valid']));
        $invalidCandidates = array_values(array_filter($selection['candidates'], fn ($c) => ! $c['valid']));

        // Distinct systems only (same product set = same system).
        $seen = [];
        $validCandidates = array_values(array_filter($validCandidates, function ($c) use (&$seen) {
            $signature = implode('-', array_column($c['products'], 'id'));
            if (isset($seen[$signature])) {
                return false;
            }
            $seen[$signature] = true;

            return true;
        }));

        $options = $this->assignOptionTypes($validCandidates);

        $recommendations = DB::transaction(function () use ($calculation, $options, $rooms, $rules) {
            // Supersede all previous open recommendations for this request.
            HvacRecommendation::whereIn(
                'hvac_calculation_id',
                HvacCalculation::where('customer_request_id', $calculation->customer_request_id)->pluck('id')
            )->whereIn('status', ['draft', 'manual_review'])->update(['status' => 'superseded']);

            $created = [];
            foreach ($options as $optionType => $candidate) {
                $created[] = $this->persistRecommendation($calculation, $optionType, $candidate, $rooms, $rules);
            }

            return $created;
        });

        return [
            'recommendations'    => $recommendations,
            'selection_warnings' => $selection['warnings'],
            'invalid_candidates' => $invalidCandidates,
        ];
    }

    private function assignOptionTypes(array $validCandidates): array
    {
        if ($validCandidates === []) {
            return [];
        }

        if (count($validCandidates) === 1) {
            return ['recommended' => $validCandidates[0]];
        }

        // Ranked order (stock/lead-time/price) → first is "recommended".
        $byPrice = $validCandidates;
        usort($byPrice, fn ($a, $b) => ($a['total_sale_price'] ?? PHP_FLOAT_MAX) <=> ($b['total_sale_price'] ?? PHP_FLOAT_MAX));

        $options = ['recommended' => $validCandidates[0]];

        $cheapest = $byPrice[0];
        if ($this->signature($cheapest) !== $this->signature($validCandidates[0])) {
            $options['budget'] = $cheapest;
        }

        $premium = $byPrice[count($byPrice) - 1];
        if (! in_array($this->signature($premium), array_map(fn ($c) => $this->signature($c), $options), true)) {
            $options['premium'] = $premium;
        }

        return $options;
    }

    private function signature(array $candidate): string
    {
        return implode('-', array_column($candidate['products'], 'id'));
    }

    private function persistRecommendation(
        HvacCalculation $calculation,
        string $optionType,
        array $candidate,
        array $rooms,
        array $rules
    ): HvacRecommendation {
        $accessories = $this->accessorySelector->select($candidate, $rooms, $rules);
        $includePump = collect($accessories['items'])->contains(fn ($i) => $i['key'] === 'condensate_pump');
        $labor = $this->laborEstimator->estimate($rooms, $includePump, $rules);
        $priced = $this->pricingService->price($candidate, $accessories['items'], $labor, $rules);

        $allWarnings = array_merge(
            $candidate['warnings'],
            $accessories['warnings'],
            $labor['warnings'],
            $priced['warnings']
        );

        $hasPriceGap = collect($priced['items'])
            ->contains(fn ($i) => $i['sale_unit_price'] === null && $i['mandatory']);

        $recommendation = HvacRecommendation::create([
            'hvac_calculation_id' => $calculation->id,
            'option_type'         => $optionType,
            'status'              => $hasPriceGap ? 'manual_review' : 'draft',
            'metadata'            => [
                'candidate' => $candidate,
                'labor'     => $labor,
                'warnings'  => $allWarnings,
            ],
            ...$priced['totals'],
        ]);

        foreach ($priced['items'] as $item) {
            $recommendation->items()->create([
                'hvac_product_id'     => $item['product_id'],
                'item_type'           => $item['item_type'],
                'sku'                 => $item['sku'],
                'description'         => $item['description'],
                'quantity'            => $item['quantity'],
                'unit'                => $item['unit'],
                'purchase_unit_price' => $item['purchase_unit_price'],
                'sale_unit_price'     => $item['sale_unit_price'] ?? 0,
                'line_total'          => $item['line_total'],
                'metadata'            => [
                    'reason'       => $item['reason'],
                    'mandatory'    => $item['mandatory'],
                    'price_source' => $item['price_source'],
                ],
            ]);
        }

        $recommendation->setRelation('items', $recommendation->items()->get());

        return $recommendation;
    }
}
