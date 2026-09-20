<?php

namespace App\Services\Hvac;

/**
 * "Bekijk voorbeeld": a demonstration of what the current settings do, from
 * quick estimate to quote price.
 *
 * Everything runs in memory with the REAL services and the given rule
 * configuration, but with explicitly fictitious products and purchase prices.
 * Nothing is read from or written to requests, calculations, quotes or the
 * catalog — the example can never alter real work, and its products never
 * become catalog products.
 */
class HvacSettingsExample
{
    public const ROOM = ['length_m' => 5.0, 'width_m' => 4.0, 'height_m' => 2.5, 'situation' => 'well_insulated'];

    /** Fictitious purchase prices — demonstration only. */
    private const DEMO_UNIT_PURCHASE = 600.00;
    private const DEMO_MATERIALS = [
        ['description' => 'Koelleiding (fictief voorbeeld)', 'unit' => 'm', 'purchase' => 8.00, 'quantity_from' => 'pipe'],
        ['description' => 'Muurbeugel buitenunit (fictief voorbeeld)', 'unit' => 'stuk', 'purchase' => 25.00, 'quantity_from' => 'bracket'],
    ];

    public function __construct(
        private readonly QuickCoolingEstimator $quickEstimator,
        private readonly LaborEstimator $laborEstimator,
        private readonly HvacPricingService $pricingService,
    ) {
    }

    /**
     * @return array{quick: array, labor: array, pricing: array, markup_pct: float, margin_on_sale_pct: float, steps: string[]}
     */
    public function build(array $rules): array
    {
        $quick = $this->quickEstimator->estimate(self::ROOM, $rules);

        $pipeLength = (float) ($rules['pipe']['assumed_length_m'] ?? 5.0);
        $rooms = [[
            'pipe' => ['actual_length_m' => $pipeLength],
            'load' => ['roof_type' => 'none'],
        ]];
        $labor = $this->laborEstimator->estimate($rooms, false, $rules);

        $candidate = ['products' => [[
            'id'             => null,
            'sku'            => 'VOORBEELD',
            'brand'          => 'Fictief',
            'model'          => 'voorbeeldtoestel 2,5 kW',
            'for_room'       => null,
            'sale_price'     => null, // no sale price → the configured opslag is demonstrated
            'purchase_price' => self::DEMO_UNIT_PURCHASE,
        ]]];

        $brackets = (int) ($rules['accessories']['wall_bracket_per_outdoor_unit'] ?? 1);
        $materials = [];
        foreach (self::DEMO_MATERIALS as $demo) {
            $materials[] = [
                'product_id'          => null,
                'sku'                 => 'VOORBEELD',
                'description'         => $demo['description'],
                'quantity'            => $demo['quantity_from'] === 'pipe' ? $pipeLength : $brackets,
                'unit'                => $demo['unit'],
                'purchase_unit_price' => $demo['purchase'],
                'sale_unit_price'     => null,
                'price_source'        => 'purchase_price_needs_margin',
                'reason'              => 'Fictief voorbeeld.',
                'mandatory'           => true,
            ];
        }

        $pricing = $this->pricingService->price($candidate, $materials, $labor, $rules);
        $markup = (float) ($rules['pricing']['fallback_margin_pct_on_purchase'] ?? 35.0);

        return [
            'quick'              => $quick,
            'labor'              => $labor,
            'pricing'            => $pricing,
            'markup_pct'         => $markup,
            'margin_on_sale_pct' => round($markup / (100 + $markup) * 100, 1),
            'steps'              => [
                'Technische controle — klopt de inschatting met de ruimte (glas, zon, toestellen, ventilatie)? Gebruik bij twijfel de gedetailleerde berekening.',
                'Toestel kiezen — uit uw eigen catalogus, met bevestigde compatibiliteit tussen binnen- en buitenunit.',
                'Installatiegegevens controleren — leidingtraject, plaats van de buitenunit, condensafvoer, elektrische aansluiting.',
                'Materiaal en werkuren bepalen — de geraamde hoeveelheden en uren vergelijken met de werf.',
                'Offerteprijs berekenen — aankoop- en verkoopprijzen, opslag, marge en btw nakijken.',
                'Offerte goedkeuren — pas daarna omzetten naar een conceptofferte, de PDF lezen en zelf versturen.',
            ],
        ];
    }
}
