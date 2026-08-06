<?php

namespace App\Services\Hvac;

/**
 * Margin = (equipment + material sale totals) − (their purchase totals).
 * Labor and travel are treated as sold at internal cost in the MVP, so
 * product margin is the tracked margin. If any purchase price is missing,
 * the margin is reported as unknown — never guessed.
 */
class MarginCalculator
{
    /**
     * @param array $items pricing items with line_total / purchase totals
     * @return array{margin_amount: float|null, margin_percentage: float|null, complete: bool}
     */
    public function calculate(array $items, float $subtotalExclVat): array
    {
        $saleTotal = 0.0;
        $purchaseTotal = 0.0;
        $complete = true;

        foreach ($items as $item) {
            if (! in_array($item['item_type'], ['equipment', 'material'], true)) {
                continue;
            }
            $saleTotal += (float) $item['line_total'];
            if ($item['purchase_unit_price'] === null) {
                $complete = false;
                continue;
            }
            $purchaseTotal += (float) $item['purchase_unit_price'] * (float) $item['quantity'];
        }

        if (! $complete) {
            return ['margin_amount' => null, 'margin_percentage' => null, 'complete' => false];
        }

        $margin = round($saleTotal - $purchaseTotal, 2);

        return [
            'margin_amount'     => $margin,
            'margin_percentage' => $subtotalExclVat > 0 ? round($margin / $subtotalExclVat * 100, 1) : null,
            'complete'          => true,
        ];
    }
}
