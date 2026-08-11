<?php

namespace App\Services\Hvac;

use App\Models\HvacCalculation;
use App\Models\HvacProduct;
use App\Models\HvacRecommendation;
use App\Models\HvacRecommendationItem;
use App\Services\Hvac\CapacityClassSelector;
use Illuminate\Support\Facades\DB;

/**
 * Manual overrides by the admin. Every override records the original value,
 * the new value, a mandatory reason, the admin and a timestamp — original
 * automatic values are never erased. Totals are recomputed deterministically.
 */
class HvacManualOverrideService
{
    public function overrideItem(
        HvacRecommendationItem $item,
        ?float $quantity,
        ?float $saleUnitPrice,
        string $reason,
        string $adminEmail
    ): HvacRecommendationItem {
        return DB::transaction(function () use ($item, $quantity, $saleUnitPrice, $reason, $adminEmail) {
            $recommendation = $item->recommendation;

            if ($quantity !== null && $quantity !== $item->quantity) {
                $this->log($recommendation, "item:{$item->id}:quantity", (string) $item->quantity, (string) $quantity, $reason, $adminEmail);
                $item->quantity = $quantity;
            }

            if ($saleUnitPrice !== null && $saleUnitPrice !== $item->sale_unit_price) {
                $this->log($recommendation, "item:{$item->id}:sale_unit_price", (string) $item->sale_unit_price, (string) $saleUnitPrice, $reason, $adminEmail);
                $item->sale_unit_price = $saleUnitPrice;

                // A manually set price resolves a missing catalog price — the
                // readiness gate must be able to clear (audited above).
                if (($item->metadata['price_source'] ?? '') === 'missing' && $saleUnitPrice > 0) {
                    $item->metadata = array_merge($item->metadata ?? [], ['price_source' => 'manual_override']);
                }
            }

            $item->line_total = round($item->quantity * $item->sale_unit_price, 2);
            $item->save();

            $this->recalculateTotals($recommendation);

            return $item->fresh();
        });
    }

    public function overrideVatRate(
        HvacRecommendation $recommendation,
        float $vatRate,
        string $reason,
        string $adminEmail
    ): HvacRecommendation {
        return DB::transaction(function () use ($recommendation, $vatRate, $reason, $adminEmail) {
            $this->log($recommendation, 'vat_rate', (string) $recommendation->vat_rate, (string) $vatRate, $reason, $adminEmail);

            $recommendation->vat_rate = $vatRate;
            $recommendation->save();
            $this->recalculateTotals($recommendation);

            return $recommendation->fresh();
        });
    }

    /**
     * Replace the product behind an equipment item with another ACTIVE
     * catalog product. Prices come from the catalog, never free input.
     */
    public function changeItemProduct(
        HvacRecommendationItem $item,
        HvacProduct $product,
        string $reason,
        string $adminEmail
    ): HvacRecommendationItem {
        if (! $product->is_active) {
            throw new \InvalidArgumentException('Alleen actieve catalogusproducten kunnen gekozen worden.');
        }
        if (! HvacProduct::selectable()->whereKey($product->id)->exists()) {
            throw new \InvalidArgumentException(
                'Dit product zit alleen nog in gearchiveerde productlijsten en kan niet gekozen worden voor nieuwe aanbevelingen.'
            );
        }

        return DB::transaction(function () use ($item, $product, $reason, $adminEmail) {
            $recommendation = $item->recommendation;

            $this->log(
                $recommendation,
                "item:{$item->id}:product",
                "{$item->sku} ({$item->description})",
                "{$product->sku} ({$product->name})",
                $reason,
                $adminEmail
            );

            $sale = $product->default_sale_price_excl_vat;
            if ($sale === null && $product->purchase_price_excl_vat !== null) {
                $rules = $recommendation->calculation->result['rule_set']['configuration'] ?? [];
                $fallback = (float) ($rules['pricing']['fallback_margin_pct_on_purchase'] ?? 35.0);
                $sale = round($product->purchase_price_excl_vat * (1 + $fallback / 100), 2);
            }

            $item->update([
                'hvac_product_id'     => $product->id,
                'sku'                 => $product->sku,
                'description'         => trim(($product->brand?->name ?? '') . ' ' . $product->model),
                'purchase_unit_price' => $product->purchase_price_excl_vat,
                'sale_unit_price'     => $sale ?? 0,
                'line_total'          => round($item->quantity * ($sale ?? 0), 2),
                'metadata'            => array_merge($item->metadata ?? [], [
                    'manually_selected' => true,
                    'price_source'      => $sale !== null ? 'catalog' : 'missing',
                ]),
            ]);

            $this->recalculateTotals($recommendation);

            return $item->fresh();
        });
    }

    /**
     * Add a commercial discount line (always negative, reason mandatory,
     * fully audited). Totals are recomputed server-side.
     */
    public function addDiscount(
        HvacRecommendation $recommendation,
        float $amount,
        ?string $description,
        string $reason,
        string $adminEmail
    ): HvacRecommendation {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Een korting moet een positief bedrag zijn.');
        }
        if ($amount > (float) $recommendation->subtotal_excl_vat) {
            throw new \InvalidArgumentException('De korting is groter dan het subtotaal.');
        }

        return DB::transaction(function () use ($recommendation, $amount, $description, $reason, $adminEmail) {
            $this->log($recommendation, 'discount', null, '-' . number_format($amount, 2, '.', ''), $reason, $adminEmail);

            $recommendation->items()->create([
                'item_type'       => 'discount',
                'description'     => $description !== null && trim($description) !== ''
                    ? trim($description)
                    : 'Commerciële korting',
                'quantity'        => 1,
                'unit'            => 'forfait',
                'sale_unit_price' => -$amount,
                'line_total'      => -$amount,
                'metadata'        => ['reason' => $reason, 'mandatory' => true, 'price_source' => 'manual_discount'],
            ]);

            $this->recalculateTotals($recommendation);

            return $recommendation->fresh();
        });
    }

    /**
     * Override the calculated cooling load of one room. The original
     * automatic values stay untouched in the result; override values are
     * stored alongside them (final_watts_override etc.), the target class is
     * re-derived from the SAME rule snapshot, and the system totals are
     * recomputed into result['system_with_overrides'] — the original
     * 'system' block is never modified.
     *
     * Recommendations must be rebuilt afterwards so product selection uses
     * the overridden class (the controller does this).
     */
    public function overrideRoomLoad(
        HvacCalculation $calculation,
        int $roomIndex,
        float $watts,
        string $reason,
        string $adminEmail
    ): HvacCalculation {
        if ($watts < 100 || $watts > 20000) {
            throw new \InvalidArgumentException('Het opgegeven vermogen is onwaarschijnlijk (100–20000 W).');
        }

        return DB::transaction(function () use ($calculation, $roomIndex, $watts, $reason, $adminEmail) {
            $result = $calculation->result;
            if (! isset($result['rooms'][$roomIndex])) {
                throw new \InvalidArgumentException('Onbekende kamer.');
            }

            $rules = $result['rule_set']['configuration'];
            $room = $result['rooms'][$roomIndex];
            $originalWatts = $room['load']['final_watts'];

            $this->logOnCalculation(
                $calculation,
                "room:{$roomIndex}:final_watts",
                (string) $originalWatts,
                (string) (int) round($watts),
                $reason,
                $adminEmail
            );

            $overrideKw = round($watts / 1000, 2);
            $class = (new CapacityClassSelector())->select($overrideKw, $rules);

            $result['rooms'][$roomIndex]['load']['final_watts_override'] = (int) round($watts);
            $result['rooms'][$roomIndex]['load']['final_kw_override'] = $overrideKw;
            $result['rooms'][$roomIndex]['capacity_class_kw_override'] = $class['class_kw'];
            $result['rooms'][$roomIndex]['capacity_manual_review_override'] = $class['manual_review'];

            // Recompute system totals with overrides applied, next to the
            // untouched original block.
            $totalLoadKw = 0.0;
            $classes = [];
            foreach ($result['rooms'] as $r) {
                $totalLoadKw += (float) ($r['load']['final_kw_override'] ?? $r['load']['final_kw']);
                $classKw = $r['capacity_class_kw_override'] ?? $r['capacity_class_kw'];
                if ($classKw !== null) {
                    $classes[] = (float) $classKw;
                }
            }
            $sumOfClasses = round(array_sum($classes), 2);
            $system = $result['system'];
            $factor = $system['diversity_factor'];

            $result['system_with_overrides'] = [
                'split_type'           => $system['split_type'],
                'total_load_kw'        => round($totalLoadKw, 2),
                'sum_of_classes_kw'    => $sumOfClasses,
                'diversity_factor'     => $factor,
                'estimated_outdoor_kw' => $factor !== null ? round($sumOfClasses * $factor, 2) : $sumOfClasses,
                'indoor_unit_count'    => $system['indoor_unit_count'],
                'vat'                  => $system['vat'],
            ];

            $calculation->update([
                'result'                 => $result,
                'manually_overridden_at' => now(),
                'manually_overridden_by' => $adminEmail,
            ]);

            return $calculation->fresh();
        });
    }

    private function logOnCalculation(
        HvacCalculation $calculation,
        string $field,
        ?string $original,
        ?string $overridden,
        string $reason,
        string $adminEmail
    ): void {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Een reden is verplicht bij elke handmatige aanpassing.');
        }

        $calculation->overrides()->create([
            'field'            => $field,
            'original_value'   => $original,
            'overridden_value' => $overridden,
            'reason'           => $reason,
            'overridden_by'    => $adminEmail,
            'created_at'       => now(),
        ]);
    }

    private function log(
        HvacRecommendation $recommendation,
        string $field,
        ?string $original,
        ?string $overridden,
        string $reason,
        string $adminEmail
    ): void {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Een reden is verplicht bij elke handmatige aanpassing.');
        }

        $calculation = $recommendation->calculation;

        $calculation->overrides()->create([
            'field'            => $field,
            'original_value'   => $original,
            'overridden_value' => $overridden,
            'reason'           => $reason,
            'overridden_by'    => $adminEmail,
            'created_at'       => now(),
        ]);

        $calculation->update([
            'manually_overridden_at' => now(),
            'manually_overridden_by' => $adminEmail,
        ]);
    }

    private function recalculateTotals(HvacRecommendation $recommendation): void
    {
        $items = $recommendation->items()->get();

        $sum = fn (string $type) => round((float) $items->where('item_type', $type)->sum('line_total'), 2);

        $equipment = $sum('equipment');
        $materials = $sum('material');
        $labor = $sum('labor');
        $travel = $sum('travel');
        $discount = $sum('discount'); // negative
        $subtotal = round($equipment + $materials + $labor + $travel + $discount, 2);
        $vat = round($subtotal * (float) $recommendation->vat_rate / 100, 2);

        // Unpriced optional flag lines don't count against margin completeness.
        $marginItems = $items->whereIn('item_type', ['equipment', 'material'])
            ->reject(fn ($i) => ($i->metadata['mandatory'] ?? true) === false
                && $i->purchase_unit_price === null
                && (float) $i->sale_unit_price === 0.0);
        $purchaseKnown = $marginItems->every(fn ($i) => $i->purchase_unit_price !== null);
        $margin = null;
        $marginPct = null;
        if ($purchaseKnown) {
            $saleTotal = (float) $marginItems->sum('line_total');
            $purchaseTotal = (float) $marginItems
                ->sum(fn ($i) => (float) $i->purchase_unit_price * (float) $i->quantity);
            $margin = round($saleTotal - $purchaseTotal, 2);
            $marginPct = $subtotal > 0 ? round($margin / $subtotal * 100, 1) : null;
        }

        $recommendation->update([
            'equipment_total_excl_vat' => $equipment,
            'materials_total_excl_vat' => $materials,
            'labor_total_excl_vat'     => $labor,
            'travel_total_excl_vat'    => $travel,
            'subtotal_excl_vat'        => $subtotal,
            'vat_amount'               => $vat,
            'total_incl_vat'           => round($subtotal + $vat, 2),
            'margin_amount'            => $margin,
            'margin_percentage'        => $marginPct,
        ]);
    }
}
