<?php

namespace App\Services\Hvac;

use App\Models\HvacProduct;

/**
 * Determines required installation materials for a selected system.
 *
 * Quantities come from rule-set rules; prices ONLY from the catalog. A
 * material without a catalog product gets price_source "missing" and a
 * warning — never an invented price. Every line carries its reason and
 * whether it is mandatory.
 */
class AccessorySelector
{
    /**
     * @param array $candidate  a ProductSelector candidate
     * @param array $rooms      room results from the calculation
     * @return array{items: array, warnings: array}
     */
    public function select(array $candidate, array $rooms, array $rules): array
    {
        $accessoryRules = $rules['accessories'] ?? [];
        $items = [];
        $warnings = [];

        $indoorCount = count($rooms);
        $outdoorCount = 1;
        $totalPipeM = 0.0;
        foreach ($rooms as $room) {
            $totalPipeM += (float) $room['pipe']['actual_length_m'];
        }
        $totalPipeM = round($totalPipeM, 1);

        $lines = [
            [
                'key'       => 'wall_bracket',
                'type'      => 'wall_bracket',
                'quantity'  => $outdoorCount * (int) ($accessoryRules['wall_bracket_per_outdoor_unit'] ?? 1),
                'unit'      => 'stuk',
                'reason'    => 'Muurbeugel voor de buitenunit (plaatsing onbekend — muurmontage aangenomen).',
                'mandatory' => true,
            ],
            [
                'key'       => 'vibration_damper',
                'type'      => 'vibration_damper',
                'quantity'  => $outdoorCount * (int) ($accessoryRules['vibration_dampers_per_outdoor_unit'] ?? 1),
                'unit'      => 'set',
                'reason'    => 'Trillingsdempers voor de buitenunit.',
                'mandatory' => true,
            ],
            [
                'key'       => 'pipe',
                'type'      => 'pipe',
                'quantity'  => $totalPipeM,
                'unit'      => 'm',
                'reason'    => "Koelleidingen: geschatte {$totalPipeM} m (aanname — controleer ter plaatse).",
                'mandatory' => true,
            ],
            [
                'key'       => 'trunking',
                'type'      => 'trunking',
                'quantity'  => round($totalPipeM * (float) ($accessoryRules['trunking_m_factor_of_pipe'] ?? 1.0), 1),
                'unit'      => 'm',
                'reason'    => 'Leidinggoot langs het leidingtraject.',
                'mandatory' => true,
            ],
            [
                'key'       => 'cable',
                'type'      => 'electrical_accessory',
                'quantity'  => round($totalPipeM * (float) ($accessoryRules['cable_m_factor_of_pipe'] ?? 1.2), 1),
                'unit'      => 'm',
                'reason'    => 'Interconnectie- en voedingskabel (schatting op basis van leidingtraject).',
                'mandatory' => true,
            ],
            [
                'key'       => 'drain_hose',
                'type'      => 'drain_hose',
                'quantity'  => $indoorCount * (float) ($accessoryRules['drain_hose_m_per_indoor_unit'] ?? 5),
                'unit'      => 'm',
                'reason'    => 'Condensafvoerslang per binnenunit.',
                'mandatory' => true,
            ],
            [
                'key'       => 'condensate_pump',
                'type'      => 'condensate_pump',
                'quantity'  => $indoorCount,
                'unit'      => 'stuk',
                'reason'    => 'Afvoer onbekend — condensaatpomp mogelijk nodig. Optioneel tot bevestiging ter plaatse.',
                'mandatory' => false,
            ],
        ];

        foreach ($lines as $line) {
            $product = $this->cheapestCatalogProduct($line['type']);

            $item = [
                'key'                 => $line['key'],
                'item_type'           => 'material',
                'product_id'          => $product?->id,
                'sku'                 => $product?->sku,
                'description'         => $product?->name ?? $this->fallbackDescription($line['key']),
                'quantity'            => $line['quantity'],
                'unit'                => $line['unit'],
                'purchase_unit_price' => $product?->purchase_price_excl_vat,
                'sale_unit_price'     => $product?->default_sale_price_excl_vat,
                'price_source'        => $product !== null
                    ? ($product->default_sale_price_excl_vat !== null ? 'catalog' : 'purchase_price_needs_margin')
                    : 'missing',
                'reason'              => $line['reason'],
                'mandatory'           => $line['mandatory'],
            ];

            if ($product === null) {
                $warnings[] = [
                    'code'    => 'accessory_missing_in_catalog',
                    'message' => "Geen catalogusproduct voor '{$this->fallbackDescription($line['key'])}' — hoeveelheid berekend, prijs ontbreekt.",
                ];
            }

            $items[] = $item;
        }

        return ['items' => $items, 'warnings' => $warnings];
    }

    private function cheapestCatalogProduct(string $type): ?HvacProduct
    {
        return HvacProduct::active()
            ->where('product_type', $type)
            ->where(function ($q) {
                $q->whereNotNull('default_sale_price_excl_vat')
                    ->orWhereNotNull('purchase_price_excl_vat');
            })
            ->orderByRaw('COALESCE(default_sale_price_excl_vat, purchase_price_excl_vat * 2) asc')
            ->first();
    }

    private function fallbackDescription(string $key): string
    {
        return [
            'wall_bracket'     => 'Muurbeugel buitenunit',
            'vibration_damper' => 'Trillingsdempers (set)',
            'pipe'             => 'Koelleiding (per meter)',
            'trunking'         => 'Leidinggoot (per meter)',
            'cable'            => 'Kabel (per meter)',
            'drain_hose'       => 'Condensafvoerslang (per meter)',
            'condensate_pump'  => 'Condensaatpomp',
        ][$key] ?? $key;
    }
}
