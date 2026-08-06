<?php

namespace App\Services\Hvac;

use App\Models\HvacCalculation;
use App\Models\HvacProduct;
use App\Models\HvacRecommendation;
use App\Models\HvacRecommendationItem;
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
        $subtotal = round($equipment + $materials + $labor + $travel, 2);
        $vat = round($subtotal * (float) $recommendation->vat_rate / 100, 2);

        $purchaseKnown = $items->whereIn('item_type', ['equipment', 'material'])
            ->every(fn ($i) => $i->purchase_unit_price !== null);
        $margin = null;
        $marginPct = null;
        if ($purchaseKnown) {
            $saleTotal = $equipment + $materials;
            $purchaseTotal = (float) $items->whereIn('item_type', ['equipment', 'material'])
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
