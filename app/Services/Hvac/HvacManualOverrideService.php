<?php

namespace App\Services\Hvac;

use App\Models\HvacCalculation;
use App\Models\HvacProduct;
use App\Models\HvacProductCompatibility;
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
    /** Warning codes owned by the post-change re-validation (replaced on every run). */
    private const REVALIDATION_CODES = [
        'pipe_limit_exceeded', 'height_limit_exceeded', 'limits_unknown',
        'compatibility_missing_after_change', 'connected_units_exceeded',
    ];

    public const NEGATIVE_MARGIN_WARNING = [
        'code'    => 'negative_margin',
        'message' => 'De marge is NEGATIEF: de verkoopprijs dekt de aankoopkosten niet. Controleer prijzen en kortingen vóór goedkeuring.',
    ];

    public function __construct(private readonly ProductSelector $selector)
    {
    }

    public function overrideItem(
        HvacRecommendationItem $item,
        ?float $quantity,
        ?float $saleUnitPrice,
        string $reason,
        string $adminEmail
    ): HvacRecommendationItem {
        if ($item->item_type === 'discount') {
            [$quantity, $saleUnitPrice] = $this->guardDiscountOverride($item, $quantity, $saleUnitPrice);
        }

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

    /**
     * A discount line is always "1 × −amount": the quantity can never be
     * changed and the amount can never exceed what the other lines add up
     * to, so a discount can never flip the subtotal negative.
     *
     * @return array{0: ?float, 1: ?float} [quantity, saleUnitPrice] to apply
     */
    private function guardDiscountOverride(HvacRecommendationItem $item, ?float $quantity, ?float $saleUnitPrice): array
    {
        if ($quantity !== null && (float) $quantity !== 1.0) {
            throw new \InvalidArgumentException(
                'De hoeveelheid van een kortingsregel is altijd 1. Pas het kortingsbedrag aan in plaats van de hoeveelheid.'
            );
        }

        if ($saleUnitPrice === null) {
            return [null, null];
        }

        $normalised = -abs($saleUnitPrice);
        $others = $item->recommendation->items()->whereKeyNot($item->id)->get();
        $coverable = round((float) $others->sum('line_total'), 2); // other discounts already negative

        if (abs($normalised) > $coverable) {
            throw new \InvalidArgumentException(
                'De korting (€ ' . number_format(abs($normalised), 2, ',', '.') . ') is groter dan het subtotaal van de overige regels (€ '
                . number_format(max($coverable, 0), 2, ',', '.') . ').'
            );
        }

        return [null, $normalised];
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
     * Replace the product behind an equipment item with another ACTIVE,
     * selectable catalog product of the SAME type. Prices come from the
     * catalog, never free input. Afterwards the technical validation
     * (limits + explicit compatibility) is re-run and written into the
     * candidate snapshot, so the readiness gate reflects the new product
     * instead of the one the selector originally proved.
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
        if ($item->item_type !== 'equipment') {
            throw new \InvalidArgumentException('Alleen toestelregels kunnen van product gewisseld worden.');
        }
        $currentType = $item->product?->product_type;
        if ($currentType !== null && $product->product_type !== $currentType) {
            throw new \InvalidArgumentException(
                'Het nieuwe product moet van hetzelfde type zijn als het huidige (' . $currentType . '); gekozen: ' . $product->product_type . '.'
            );
        }

        return DB::transaction(function () use ($item, $product, $reason, $adminEmail) {
            $recommendation = $item->recommendation;
            $previousProductId = $item->hvac_product_id;

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

            $this->revalidateCandidate($recommendation->fresh(), $previousProductId, $product);
            $this->recalculateTotals($recommendation);

            return $item->fresh();
        });
    }

    /**
     * Re-run the selector's technical checks for the equipment now on the
     * recommendation and store the outcome in metadata.candidate
     * (valid / checks / warnings) — the readiness gate reads exactly that.
     */
    private function revalidateCandidate(HvacRecommendation $recommendation, ?int $previousProductId, HvacProduct $newProduct): void
    {
        $metadata = $recommendation->metadata ?? [];
        $candidate = $metadata['candidate'] ?? [];
        $calculation = $recommendation->calculation;
        $result = $calculation->result ?? [];
        $rules = $result['rule_set']['configuration'] ?? [];

        $totalPipe = 0.0;
        $maxRoomPipe = 0.0;
        $maxRise = 0.0;
        foreach ($result['rooms'] ?? [] as $room) {
            $equivalent = (float) ($room['pipe']['equivalent_length_m'] ?? 0);
            $totalPipe += $equivalent;
            $maxRoomPipe = max($maxRoomPipe, $equivalent);
            $maxRise = max($maxRise, (float) ($room['pipe']['vertical_rise_m'] ?? 0));
        }

        $equipment = $recommendation->items()->where('item_type', 'equipment')->with('product.brand')->orderBy('id')->get();
        $products = $equipment->pluck('product')->filter()->values();

        $limitsProduct = $products->first(fn (HvacProduct $p) => in_array($p->product_type, ['single_split_set', 'outdoor_unit', 'multi_split_outdoor'], true));
        $indoors = $products->filter(fn (HvacProduct $p) => $p->product_type === 'indoor_unit')->values();

        $warnings = [];
        $valid = true;

        if ($limitsProduct === null) {
            $checks = ['pipe' => 'unknown', 'height' => 'unknown', 'electrical' => 'unknown_supply'];
            $valid = false;
            $warnings[] = [
                'code'    => 'limits_unknown',
                'message' => 'Geen buitenunit of set in deze optie — leiding- en hoogtelimieten kunnen niet gecontroleerd worden.',
            ];
        } else {
            $checks = $this->selector->limitChecks($limitsProduct, $totalPipe, $maxRoomPipe, $maxRise, $rules);

            if ($checks['pipe'] === 'exceeded') {
                $valid = false;
                $warnings[] = [
                    'code'    => 'pipe_limit_exceeded',
                    'message' => "{$limitsProduct->model}: geschatte leidinglengte overschrijdt de maximale leidinglengte van het product.",
                ];
            }
            if ($checks['height'] === 'exceeded') {
                $valid = false;
                $warnings[] = [
                    'code'    => 'height_limit_exceeded',
                    'message' => "{$limitsProduct->model}: geschat hoogteverschil overschrijdt het maximum van het product.",
                ];
            }
            if ($checks['pipe'] === 'unknown' || $checks['height'] === 'unknown') {
                $valid = false;
                $warnings[] = [
                    'code'    => 'limits_unknown',
                    'message' => "{$limitsProduct->model}: leiding- of hoogtelimieten onbekend in de catalogus — handmatige controle vereist.",
                ];
            }

            // Explicit compatibility rows between the outdoor and every
            // distinct indoor unit (a single-split set is compatible by
            // definition). Units are never paired on kW alone.
            if ($limitsProduct->product_type !== 'single_split_set') {
                $type = $limitsProduct->product_type === 'multi_split_outdoor' ? 'multi_split_indoor' : 'indoor_outdoor';
                $indoorIds = $indoors->pluck('id')->unique()->values();
                $linked = HvacProductCompatibility::where('parent_product_id', $limitsProduct->id)
                    ->where('compatibility_type', $type)
                    ->where('is_active', true)
                    ->whereIn('compatible_product_id', $indoorIds)
                    ->pluck('compatible_product_id')
                    ->unique();

                $missing = $indoors->filter(fn (HvacProduct $p) => ! $linked->contains($p->id));
                if ($indoorIds->isEmpty() || $missing->isNotEmpty()) {
                    $valid = false;
                    $models = $missing->pluck('model')->unique()->implode(', ');
                    $warnings[] = [
                        'code'    => 'compatibility_missing_after_change',
                        'message' => "Buitenunit {$limitsProduct->model}: geen actieve compatibiliteitsregel met "
                            . ($models !== '' ? "binnenunit(s) {$models}" : 'een binnenunit')
                            . ' — compatibiliteit niet aantoonbaar na de productwijziging.',
                    ];
                }

                if ($limitsProduct->maximum_connected_indoor_units !== null
                    && $indoors->count() > $limitsProduct->maximum_connected_indoor_units) {
                    $valid = false;
                    $warnings[] = [
                        'code'    => 'connected_units_exceeded',
                        'message' => "Buitenunit {$limitsProduct->model}: meer binnenunits ({$indoors->count()}) dan het maximum ({$limitsProduct->maximum_connected_indoor_units}).",
                    ];
                }
            }
        }

        // Price gaps keep invalidating the candidate, as in the selector.
        $priceMissing = $products->contains(
            fn (HvacProduct $p) => $p->default_sale_price_excl_vat === null && $p->purchase_price_excl_vat === null
        );
        if ($priceMissing) {
            $valid = false;
        }

        // Refresh the product snapshot of the swapped unit (room label kept).
        $candidateProducts = $candidate['products'] ?? [];
        $replaced = false;
        foreach ($candidateProducts as $i => $p) {
            if ($previousProductId !== null && ($p['id'] ?? null) === $previousProductId && ! $replaced) {
                $candidateProducts[$i] = $this->selector->productData($newProduct, $p['for_room'] ?? null);
                $replaced = true;
            }
        }
        if (! $replaced) {
            $candidateProducts[] = $this->selector->productData($newProduct);
        }

        $keep = fn (array $w) => ! in_array($w['code'] ?? '', self::REVALIDATION_CODES, true);
        $candidate['products'] = array_values($candidateProducts);
        $candidate['checks'] = $checks;
        $candidate['valid'] = $valid;
        $candidate['revalidated_after_product_change'] = true;
        $candidate['warnings'] = array_values(array_merge(
            array_filter($candidate['warnings'] ?? [], $keep),
            $warnings
        ));

        $metadata['candidate'] = $candidate;
        $metadata['warnings'] = array_values(array_merge(
            array_filter($metadata['warnings'] ?? [], $keep),
            $warnings
        ));

        $recommendation->update(['metadata' => $metadata]);
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
        // Metadata may have been rewritten in this transaction (candidate
        // re-validation) — never merge warnings from a stale copy.
        $recommendation->refresh();
        $items = $recommendation->items()->get();

        $sum = fn (string $type) => round((float) $items->where('item_type', $type)->sum('line_total'), 2);

        $equipment = $sum('equipment');
        $materials = $sum('material');
        $labor = $sum('labor');
        $travel = $sum('travel');
        $discount = $sum('discount'); // negative
        $subtotal = round($equipment + $materials + $labor + $travel + $discount, 2);

        if ($subtotal < 0) {
            throw new \InvalidArgumentException(
                'Deze aanpassing zou het subtotaal negatief maken (€ ' . number_format($subtotal, 2, ',', '.') . ') en is geweigerd.'
            );
        }

        // VAT per line (rounded per line, then summed): the same arithmetic
        // as QuoteItem::calculateLine, so approval and quote PDF agree.
        $vatRate = (float) $recommendation->vat_rate;
        $vat = round((float) $items->sum(fn ($i) => round((float) $i->line_total * $vatRate / 100, 2)), 2);

        // Unpriced optional flag lines don't count against margin completeness.
        $marginItems = $items->whereIn('item_type', ['equipment', 'material'])
            ->reject(fn ($i) => ($i->metadata['mandatory'] ?? true) === false
                && $i->purchase_unit_price === null
                && (float) $i->sale_unit_price === 0.0);
        $purchaseKnown = $marginItems->every(fn ($i) => $i->purchase_unit_price !== null);
        $margin = null;
        $marginPct = null;
        if ($purchaseKnown) {
            // Commercial discounts come straight out of the margin.
            $saleTotal = (float) $marginItems->sum('line_total') + $discount;
            $purchaseTotal = (float) $marginItems
                ->sum(fn ($i) => (float) $i->purchase_unit_price * (float) $i->quantity);
            $margin = round($saleTotal - $purchaseTotal, 2);
            $marginPct = $subtotal > 0 ? round($margin / $subtotal * 100, 1) : null;
        }

        $metadata = $recommendation->metadata ?? [];
        $warnings = array_values(array_filter(
            $metadata['warnings'] ?? [],
            fn ($w) => ($w['code'] ?? '') !== 'negative_margin'
        ));
        if ($margin !== null && $margin < 0) {
            $warnings[] = self::NEGATIVE_MARGIN_WARNING;
        }
        $metadata['warnings'] = $warnings;

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
            'metadata'                 => $metadata,
        ]);
    }
}
