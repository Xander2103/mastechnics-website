<?php

namespace App\Services\Hvac;

use App\Models\HvacCalculation;
use App\Models\HvacProduct;
use App\Models\HvacProductCompatibility;
use Illuminate\Support\Collection;

/**
 * Selects real, technically compatible systems from the catalog for a
 * completed calculation.
 *
 * Hard principles:
 * - Compatibility comes from catalog data (single_split_set products or
 *   hvac_product_compatibilities rows) — units are NEVER paired because
 *   kW numbers merely look similar.
 * - Candidates with missing compatibility or unknown limits are returned as
 *   invalid/manual-review, never silently valid.
 * - Empty catalog → empty candidates + explicit warning; nothing invented.
 */
class ProductSelector
{
    /**
     * @return array{candidates: array, warnings: array}
     */
    public function selectSystems(HvacCalculation $calculation): array
    {
        $result = $calculation->result;
        if ($result === null) {
            return ['candidates' => [], 'warnings' => [[
                'code'    => 'calculation_blocked',
                'message' => 'Geen productselectie mogelijk: de berekening is geblokkeerd.',
            ]]];
        }

        $rules = $result['rule_set']['configuration'];

        return $result['system']['split_type'] === 'multi_split'
            ? $this->selectMultiSplit($result, $rules)
            : $this->selectSingleSplit($result, $rules);
    }

    // ── Single split ──────────────────────────────────────────────────────────

    private function selectSingleSplit(array $result, array $rules): array
    {
        $room = $result['rooms'][0];
        $loadKw = (float) $room['load']['final_kw'];
        $classKw = $room['capacity_class_kw'];
        $pipe = $room['pipe'];
        $warnings = [];

        if ($classKw === null) {
            return ['candidates' => [], 'warnings' => [[
                'code'    => 'manual_review_required',
                'message' => 'De koellast valt buiten de klassen — handmatige productkeuze vereist.',
            ]]];
        }

        $maxKw = (float) $classKw * (float) ($rules['capacity_match']['max_oversize_factor'] ?? 1.30);

        $candidates = [];

        // 1. Real single-split sets (compatibility guaranteed by the product).
        $sets = HvacProduct::active()
            ->where('product_type', 'single_split_set')
            ->whereNotNull('cooling_capacity_kw')
            ->where('cooling_capacity_kw', '>=', $loadKw)
            ->where('cooling_capacity_kw', '<=', $maxKw)
            ->with('brand')
            ->get();

        foreach ($sets as $set) {
            $candidates[] = $this->buildCandidate('set', [$set], $set, $pipe, $rules, 'set');
        }

        // 2. Indoor + outdoor pairs joined via explicit compatibility rows.
        $indoors = HvacProduct::active()
            ->where('product_type', 'indoor_unit')
            ->whereNotNull('cooling_capacity_kw')
            ->where('cooling_capacity_kw', '>=', $loadKw)
            ->where('cooling_capacity_kw', '<=', $maxKw)
            ->with('brand')
            ->get();

        foreach ($indoors as $indoor) {
            $links = HvacProductCompatibility::where('compatible_product_id', $indoor->id)
                ->where('compatibility_type', 'indoor_outdoor')
                ->where('is_active', true)
                ->with('parent.brand')
                ->get();

            if ($links->isEmpty()) {
                $candidates[] = [
                    'kind'             => 'pair',
                    'products'         => [$this->productData($indoor)],
                    'total_sale_price' => null,
                    'compatibility'    => 'missing',
                    'valid'            => false,
                    'checks'           => [],
                    'warnings'         => [[
                        'code'    => 'no_compatibility_data',
                        'message' => "Binnenunit {$indoor->model}: geen gekoppelde buitenunit in de catalogus. Compatibiliteitsdata ontbreekt — handmatige controle vereist.",
                    ]],
                ];
                continue;
            }

            foreach ($links as $link) {
                $outdoor = $link->parent;
                if ($outdoor === null || ! $outdoor->is_active || $outdoor->product_type !== 'outdoor_unit') {
                    continue;
                }
                $candidates[] = $this->buildCandidate('pair', [$indoor, $outdoor], $outdoor, $pipe, $rules, 'catalog');
            }
        }

        if ($candidates === []) {
            $warnings[] = [
                'code'    => 'no_valid_products',
                'message' => 'Geen geschikte producten gevonden in de catalogus voor deze capaciteitsklasse. Vul de catalogus aan of kies handmatig.',
            ];
        }

        return ['candidates' => $this->rank($candidates), 'warnings' => $warnings];
    }

    // ── Multi split ───────────────────────────────────────────────────────────

    private function selectMultiSplit(array $result, array $rules): array
    {
        $rooms = $result['rooms'];
        $system = $result['system'];
        $warnings = [];

        // One indoor unit per room; the best candidate per room by
        // stock/lead-time/price. All rooms must be servable.
        $indoorPerRoom = [];
        foreach ($rooms as $room) {
            $classKw = $room['capacity_class_kw'];
            if ($classKw === null) {
                return ['candidates' => [], 'warnings' => [[
                    'code'    => 'manual_review_required',
                    'message' => "{$room['load']['room_name']}: koellast buiten de klassen — handmatige keuze vereist.",
                ]]];
            }
            $loadKw = (float) $room['load']['final_kw'];
            $maxKw = (float) $classKw * (float) ($rules['capacity_match']['max_oversize_factor'] ?? 1.30);

            $units = HvacProduct::active()
                ->where('product_type', 'indoor_unit')
                ->whereNotNull('cooling_capacity_kw')
                ->where('cooling_capacity_kw', '>=', $loadKw)
                ->where('cooling_capacity_kw', '<=', $maxKw)
                ->with('brand')
                ->get();

            if ($units->isEmpty()) {
                return ['candidates' => [], 'warnings' => [[
                    'code'    => 'no_valid_products',
                    'message' => "{$room['load']['room_name']}: geen geschikte binnenunit in de catalogus (klasse {$classKw} kW).",
                ]]];
            }

            $indoorPerRoom[] = [
                'room'  => $room,
                'units' => $this->sortByPreference($units),
            ];
        }

        // Prefer keeping everything within one brand per system candidate.
        $brandIds = collect($indoorPerRoom)
            ->flatMap(fn ($r) => $r['units']->pluck('hvac_brand_id'))
            ->unique();

        $candidates = [];

        foreach ($brandIds as $brandId) {
            $chosenIndoors = [];
            foreach ($indoorPerRoom as $roomUnits) {
                $unit = $roomUnits['units']->firstWhere('hvac_brand_id', $brandId);
                if ($unit === null) {
                    continue 2; // brand can't serve every room
                }
                $chosenIndoors[] = ['room' => $roomUnits['room'], 'unit' => $unit];
            }

            $candidate = $this->buildMultiCandidate($chosenIndoors, $system, $rules);
            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        if ($candidates === []) {
            $warnings[] = [
                'code'    => 'no_valid_multi_split',
                'message' => 'Geen multi-split buitenunit gevonden die alle gekozen binnenunits aantoonbaar ondersteunt. Compatibiliteitsdata aanvullen of handmatig kiezen.',
            ];
        }

        return ['candidates' => $this->rank($candidates), 'warnings' => $warnings];
    }

    private function buildMultiCandidate(array $chosenIndoors, array $system, array $rules): ?array
    {
        $indoorIds = array_map(fn ($c) => $c['unit']->id, $chosenIndoors);
        $indoorCount = count($indoorIds);
        $sumNominalKw = round(array_sum(array_map(fn ($c) => (float) $c['unit']->cooling_capacity_kw, $chosenIndoors)), 2);
        $requiredKw = (float) $system['estimated_outdoor_kw'];
        $totalPipe = 0.0;
        $maxRoomPipe = 0.0;
        $maxRise = 0.0;
        foreach ($chosenIndoors as $c) {
            $pipe = $c['room']['pipe'];
            $totalPipe += (float) $pipe['equivalent_length_m'];
            $maxRoomPipe = max($maxRoomPipe, (float) $pipe['equivalent_length_m']);
            $maxRise = max($maxRise, (float) $pipe['vertical_rise_m']);
        }

        $outdoors = HvacProduct::active()
            ->where('product_type', 'multi_split_outdoor')
            ->where(function ($q) use ($indoorCount) {
                $q->whereNull('maximum_connected_indoor_units')
                    ->orWhere('maximum_connected_indoor_units', '>=', $indoorCount);
            })
            ->with('brand')
            ->get();

        foreach ($this->sortByPreference($outdoors) as $outdoor) {
            $candidateWarnings = [];
            $valid = true;

            // Every DISTINCT indoor model must be explicitly supported (the
            // same model may serve several rooms).
            $distinctIndoorIds = array_values(array_unique($indoorIds));
            $links = HvacProductCompatibility::where('parent_product_id', $outdoor->id)
                ->where('compatibility_type', 'multi_split_indoor')
                ->where('is_active', true)
                ->whereIn('compatible_product_id', $distinctIndoorIds)
                ->get();

            if ($links->pluck('compatible_product_id')->unique()->count() < count($distinctIndoorIds)) {
                // Not proven compatible — skip this outdoor entirely (it may
                // simply not support these models). Missing data is reported
                // at a higher level when nothing matches.
                continue;
            }

            if ($outdoor->maximum_connected_indoor_units !== null
                && $outdoor->maximum_connected_indoor_units < $indoorCount) {
                continue;
            }

            // Connected-capacity window (manufacturer connected-ratio rules).
            if ($outdoor->minimum_capacity_kw !== null && $sumNominalKw < $outdoor->minimum_capacity_kw) {
                continue;
            }
            if ($outdoor->maximum_capacity_kw !== null && $sumNominalKw > $outdoor->maximum_capacity_kw) {
                continue;
            }
            if ($outdoor->maximum_capacity_kw === null || $outdoor->minimum_capacity_kw === null) {
                $valid = false;
                $candidateWarnings[] = [
                    'code'    => 'connected_capacity_unknown',
                    'message' => "Buitenunit {$outdoor->model}: aansluitbare capaciteitsgrenzen onbekend — handmatige controle vereist.",
                ];
            }

            // Diversity estimate must be covered by the outdoor capacity.
            if ($outdoor->cooling_capacity_kw !== null && $outdoor->cooling_capacity_kw < $requiredKw) {
                continue;
            }

            $checks = $this->limitChecks($outdoor, $totalPipe, $maxRoomPipe, $maxRise, $rules);
            if ($checks['pipe'] === 'exceeded' || $checks['height'] === 'exceeded') {
                continue;
            }
            if ($checks['pipe'] === 'unknown' || $checks['height'] === 'unknown') {
                $valid = false;
                $candidateWarnings[] = [
                    'code'    => 'limits_unknown',
                    'message' => "Buitenunit {$outdoor->model}: leiding- of hoogtelimieten onbekend in de catalogus — handmatige controle vereist.",
                ];
            }

            $products = array_merge(
                array_map(fn ($c) => $this->productData($c['unit'], $c['room']['load']['room_name']), $chosenIndoors),
                [$this->productData($outdoor)]
            );

            $priceInfo = $this->totalPrice($products);

            return [
                'kind'              => 'multi',
                'products'          => $products,
                'total_sale_price'  => $priceInfo['total'],
                'compatibility'     => 'catalog',
                'valid'             => $valid && ! $priceInfo['missing'],
                'diversity'         => [
                    'indoor_count'         => $indoorCount,
                    'sum_nominal_kw'       => $sumNominalKw,
                    'diversity_factor'     => $system['diversity_factor'],
                    'required_outdoor_kw'  => $requiredKw,
                    'outdoor_capacity_kw'  => $outdoor->cooling_capacity_kw,
                ],
                'checks'            => $checks,
                'warnings'          => array_merge($candidateWarnings, $priceInfo['warnings']),
            ];
        }

        return null;
    }

    // ── Shared helpers ────────────────────────────────────────────────────────

    private function buildCandidate(
        string $kind,
        array $products,
        HvacProduct $limitsProduct,
        array $pipe,
        array $rules,
        string $compatibility
    ): array {
        $warnings = [];
        $checks = $this->limitChecks(
            $limitsProduct,
            (float) $pipe['equivalent_length_m'],
            (float) $pipe['equivalent_length_m'],
            (float) $pipe['vertical_rise_m'],
            $rules
        );

        $valid = $compatibility !== 'missing';

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
            $warnings[] = [
                'code'    => 'limits_unknown',
                'message' => "{$limitsProduct->model}: leiding- of hoogtelimieten onbekend in de catalogus — handmatige controle vereist.",
            ];
            $valid = false;
        }

        $productData = array_map(fn (HvacProduct $p) => $this->productData($p), $products);
        $priceInfo = $this->totalPrice($productData);

        return [
            'kind'             => $kind,
            'products'         => $productData,
            'total_sale_price' => $priceInfo['total'],
            'compatibility'    => $compatibility,
            'valid'            => $valid && ! $priceInfo['missing'],
            'checks'           => $checks,
            'warnings'         => array_merge($warnings, $priceInfo['warnings']),
        ];
    }

    private function limitChecks(HvacProduct $product, float $totalPipe, float $maxPerUnitPipe, float $rise, array $rules): array
    {
        $pipeCheck = 'unknown';
        if ($product->maximum_pipe_length_m !== null) {
            $pipeCheck = $totalPipe <= $product->maximum_pipe_length_m ? 'ok' : 'exceeded';
        }
        if ($pipeCheck !== 'exceeded' && $product->maximum_pipe_length_per_unit_m !== null) {
            $perUnit = $maxPerUnitPipe <= $product->maximum_pipe_length_per_unit_m ? 'ok' : 'exceeded';
            $pipeCheck = $perUnit === 'exceeded' ? 'exceeded' : ($pipeCheck === 'unknown' ? 'ok' : $pipeCheck);
        }

        $heightCheck = 'unknown';
        if ($product->maximum_height_difference_m !== null) {
            $heightCheck = $rise <= $product->maximum_height_difference_m ? 'ok' : 'exceeded';
        }

        // Electrical supply is unknown from the form → informational only.
        return [
            'pipe'       => $pipeCheck,
            'height'     => $heightCheck,
            'electrical' => 'unknown_supply',
        ];
    }

    private function productData(HvacProduct $product, ?string $forRoom = null): array
    {
        $saleprice = $product->default_sale_price_excl_vat;
        $priceSource = 'catalog_sale_price';
        // No invented prices: purchase-price fallback is computed by the
        // pricing service later and clearly labeled; here we only expose facts.
        if ($saleprice === null) {
            $priceSource = $product->purchase_price_excl_vat !== null
                ? 'purchase_price_needs_margin'
                : 'missing';
        }

        return [
            'id'                  => $product->id,
            'brand'               => $product->brand?->name,
            'sku'                 => $product->sku,
            'model'               => $product->model,
            'name'                => $product->name,
            'product_type'        => $product->product_type,
            'cooling_capacity_kw' => $product->cooling_capacity_kw,
            'sound_level_db'      => $product->sound_level_db,
            'wifi_included'       => $product->wifi_included,
            'stock_quantity'      => $product->stock_quantity,
            'lead_time_days'      => $product->lead_time_days,
            'purchase_price'      => $product->purchase_price_excl_vat,
            'sale_price'          => $saleprice,
            'price_source'        => $priceSource,
            'required_breaker_a'  => $product->required_breaker_a,
            'required_cable'      => $product->required_cable,
            'supported_voltage'   => $product->supported_voltage,
            'for_room'            => $forRoom,
        ];
    }

    private function totalPrice(array $productData): array
    {
        $total = 0.0;
        $missing = false;
        $warnings = [];

        foreach ($productData as $p) {
            if ($p['sale_price'] !== null) {
                $total += (float) $p['sale_price'];
            } elseif ($p['purchase_price'] !== null) {
                // Marked for the pricing service; not yet a price.
                $missing = false;
            } else {
                $missing = true;
                $warnings[] = [
                    'code'    => 'price_missing',
                    'message' => "{$p['model']}: geen verkoop- of aankoopprijs in de catalogus — prijs kan niet bepaald worden.",
                ];
            }
        }

        return ['total' => $missing ? null : round($total, 2), 'missing' => $missing, 'warnings' => $warnings];
    }

    private function sortByPreference(Collection $products): Collection
    {
        // Stock first, then shortest lead time, then price.
        return $products->sortBy([
            fn (HvacProduct $a, HvacProduct $b) => (($b->stock_quantity ?? 0) > 0) <=> (($a->stock_quantity ?? 0) > 0),
            fn (HvacProduct $a, HvacProduct $b) => ($a->lead_time_days ?? PHP_INT_MAX) <=> ($b->lead_time_days ?? PHP_INT_MAX),
            fn (HvacProduct $a, HvacProduct $b) => ($a->default_sale_price_excl_vat ?? PHP_FLOAT_MAX) <=> ($b->default_sale_price_excl_vat ?? PHP_FLOAT_MAX),
        ])->values();
    }

    private function rank(array $candidates): array
    {
        usort($candidates, function (array $a, array $b) {
            // Valid candidates first, then in-stock, then price.
            if ($a['valid'] !== $b['valid']) {
                return $a['valid'] ? -1 : 1;
            }
            $aStock = $this->candidateInStock($a);
            $bStock = $this->candidateInStock($b);
            if ($aStock !== $bStock) {
                return $aStock ? -1 : 1;
            }

            return ($a['total_sale_price'] ?? PHP_FLOAT_MAX) <=> ($b['total_sale_price'] ?? PHP_FLOAT_MAX);
        });

        return $candidates;
    }

    private function candidateInStock(array $candidate): bool
    {
        foreach ($candidate['products'] as $p) {
            if (($p['stock_quantity'] ?? 0) <= 0) {
                return false;
            }
        }

        return true;
    }
}
