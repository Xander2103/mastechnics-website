<?php

namespace App\Services\Hvac;

/**
 * Deterministic pricing. Sale prices come from the catalog; a product with
 * only a purchase price gets the configured fallback margin (clearly
 * labeled). Items without any price stay unpriced and produce warnings —
 * prices are never invented. VAT uses the default rate; a reduced rate is
 * only a suggestion that Martin must confirm manually.
 */
class HvacPricingService
{
    public function __construct(private readonly MarginCalculator $marginCalculator)
    {
    }

    /**
     * @param array $candidate      ProductSelector candidate (equipment)
     * @param array $materialItems  AccessorySelector items
     * @param array $labor          LaborEstimator result
     * @return array{items: array, totals: array, warnings: array}
     */
    public function price(array $candidate, array $materialItems, array $labor, array $rules): array
    {
        $pricing = $rules['pricing'] ?? [];
        $fallbackMarginPct = (float) ($pricing['fallback_margin_pct_on_purchase'] ?? 35.0);
        $vatRate = (float) ($pricing['default_vat_rate'] ?? 21.0);

        $items = [];
        $warnings = [];

        // ── Equipment ────────────────────────────────────────────────────────
        foreach ($candidate['products'] as $product) {
            $sale = $product['sale_price'];
            $priceSource = 'catalog';

            if ($sale === null && $product['purchase_price'] !== null) {
                $sale = round($product['purchase_price'] * (1 + $fallbackMarginPct / 100), 2);
                $priceSource = "fallback_margin_{$fallbackMarginPct}pct";
                $warnings[] = [
                    'code'    => 'fallback_margin_applied',
                    'message' => "{$product['model']}: geen verkoopprijs in de catalogus — {$fallbackMarginPct}% marge op aankoopprijs toegepast. Controleer de prijs.",
                ];
            } elseif ($sale === null) {
                $priceSource = 'missing';
                $warnings[] = [
                    'code'    => 'equipment_price_missing',
                    'message' => "{$product['model']}: geen prijs beschikbaar — offerteprijs kan niet berekend worden.",
                ];
            }

            $items[] = [
                'item_type'           => 'equipment',
                'product_id'          => $product['id'],
                'sku'                 => $product['sku'],
                'description'         => trim(($product['brand'] ?? '') . ' ' . $product['model'])
                    . ($product['for_room'] !== null ? " — {$product['for_room']}" : ''),
                'quantity'            => 1.0,
                'unit'                => 'stuk',
                'purchase_unit_price' => $product['purchase_price'],
                'sale_unit_price'     => $sale,
                'line_total'          => $sale !== null ? round($sale, 2) : 0.0,
                'price_source'        => $priceSource,
                'reason'              => $product['for_room'] !== null
                    ? "Binnenunit voor {$product['for_room']}."
                    : 'Geselecteerd systeemonderdeel.',
                'mandatory'           => true,
            ];
        }

        // ── Materials ────────────────────────────────────────────────────────
        foreach ($materialItems as $material) {
            $sale = $material['sale_unit_price'];
            $priceSource = $material['price_source'];

            if ($sale === null && $material['purchase_unit_price'] !== null) {
                $markup = (float) ($pricing['material_markup_pct'] ?? 25.0);
                $sale = round($material['purchase_unit_price'] * (1 + $markup / 100), 2);
                $priceSource = "material_markup_{$markup}pct";
            } elseif ($sale === null) {
                $warnings[] = [
                    'code'    => 'material_price_missing',
                    'message' => "{$material['description']}: geen catalogusprijs — regel blijft zonder prijs.",
                ];
            }

            $items[] = [
                'item_type'           => 'material',
                'product_id'          => $material['product_id'],
                'sku'                 => $material['sku'],
                'description'         => $material['description'],
                'quantity'            => (float) $material['quantity'],
                'unit'                => $material['unit'],
                'purchase_unit_price' => $material['purchase_unit_price'],
                'sale_unit_price'     => $sale,
                'line_total'          => $sale !== null ? round($sale * (float) $material['quantity'], 2) : 0.0,
                'price_source'        => $priceSource,
                'reason'              => $material['reason'],
                'mandatory'           => $material['mandatory'],
            ];
        }

        // ── Labor & travel ───────────────────────────────────────────────────
        $items[] = [
            'item_type'           => 'labor',
            'product_id'          => null,
            'sku'                 => null,
            'description'         => "Installatie en indienststelling ({$labor['total_hours']} u × € " . number_format($labor['hourly_rate'], 2, ',', '.') . '/u)',
            'quantity'            => $labor['total_hours'],
            'unit'                => 'uur',
            'purchase_unit_price' => null,
            'sale_unit_price'     => $labor['hourly_rate'],
            'line_total'          => $labor['total_cost'],
            'price_source'        => 'labor_rule',
            'reason'              => 'Gecalculeerde arbeidsuren (zie detail).',
            'mandatory'           => true,
        ];

        $items[] = [
            'item_type'           => 'travel',
            'product_id'          => null,
            'sku'                 => null,
            'description'         => 'Verplaatsing',
            'quantity'            => 1.0,
            'unit'                => 'forfait',
            'purchase_unit_price' => null,
            'sale_unit_price'     => $labor['travel_cost'],
            'line_total'          => $labor['travel_cost'],
            'price_source'        => 'labor_rule',
            'reason'              => 'Forfaitaire verplaatsingskost.',
            'mandatory'           => true,
        ];

        // ── Totals ───────────────────────────────────────────────────────────
        $sumType = fn (string $type) => round(array_sum(array_map(
            fn ($i) => $i['line_total'],
            array_filter($items, fn ($i) => $i['item_type'] === $type)
        )), 2);

        $equipmentTotal = $sumType('equipment');
        $materialsTotal = $sumType('material');
        $laborTotal = $sumType('labor');
        $travelTotal = $sumType('travel');
        $subtotal = round($equipmentTotal + $materialsTotal + $laborTotal + $travelTotal, 2);
        $vatAmount = round($subtotal * $vatRate / 100, 2);

        $margin = $this->marginCalculator->calculate($items, $subtotal);
        if (! $margin['complete']) {
            $warnings[] = [
                'code'    => 'margin_incomplete',
                'message' => 'Niet alle aankoopprijzen zijn gekend — de marge kan niet volledig berekend worden.',
            ];
        }

        return [
            'items'  => $items,
            'totals' => [
                'equipment_total_excl_vat' => $equipmentTotal,
                'materials_total_excl_vat' => $materialsTotal,
                'labor_total_excl_vat'     => $laborTotal,
                'travel_total_excl_vat'    => $travelTotal,
                'subtotal_excl_vat'        => $subtotal,
                'vat_rate'                 => $vatRate,
                'vat_amount'               => $vatAmount,
                'total_incl_vat'           => round($subtotal + $vatAmount, 2),
                'margin_amount'            => $margin['margin_amount'],
                'margin_percentage'        => $margin['margin_percentage'],
            ],
            'warnings' => $warnings,
        ];
    }
}
