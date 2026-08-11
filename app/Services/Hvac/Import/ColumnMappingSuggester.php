<?php

namespace App\Services\Hvac\Import;

use App\Services\Hvac\HvacCsvImporter;

/**
 * Suggests internal-field mappings for supplier column headers using an
 * alias vocabulary (nl/fr/en + common supplier wording).
 *
 * The suggester is advisory only: 'exact' suggestions may be preselected in
 * the mapping UI, 'fuzzy' and ambiguous ones must be confirmed by the admin,
 * and nothing is ever imported without explicit confirmation.
 */
class ColumnMappingSuggester
{
    /** @var array<string, string[]> internal field => normalized aliases */
    private const ALIASES = [
        'supplier' => ['leverancier', 'fournisseur', 'verdeler', 'supplier name'],
        'brand'    => ['merk', 'marque', 'fabrikant', 'manufacturer'],
        'sku'      => [
            'artikelnummer', 'artikelnr', 'art nr', 'artnr', 'artikelcode', 'artikel',
            'item number', 'item no', 'reference', 'referentie', 'ref',
            'code article', 'productcode', 'product code',
        ],
        'model' => ['modelcode', 'model code', 'modelnummer', 'modele', 'type model'],
        'name'  => [
            'naam', 'omschrijving', 'beschrijving', 'description', 'designation',
            'product name', 'productnaam', 'libelle', 'benaming',
        ],
        'product_type'        => ['producttype', 'type product', 'categorie', 'category', 'productcategorie'],
        'cooling_capacity_kw' => [
            'koelvermogen', 'koelvermogen kw', 'koelcapaciteit', 'cooling capacity',
            'cooling kw', 'puissance froid', 'koelen kw', 'capacity cooling',
        ],
        'heating_capacity_kw' => [
            'verwarmingsvermogen', 'verwarmingscapaciteit', 'heating capacity',
            'heating kw', 'puissance chaud', 'verwarmen kw',
        ],
        'minimum_capacity_kw' => ['minimum vermogen', 'min vermogen', 'min capacity', 'minimum capacity'],
        'maximum_capacity_kw' => ['maximum vermogen', 'max vermogen', 'max capacity', 'maximum capacity'],
        'purchase_price_excl_vat' => [
            'netto prijs', 'nettoprijs', 'netto', 'inkoopprijs', 'aankoopprijs',
            'purchase price', 'prix net', 'prix achat', 'net price', 'netto excl btw',
            'inkoop', 'aankoop',
        ],
        'sale_price_excl_vat' => [
            'verkoopprijs', 'bruto prijs', 'brutoprijs', 'adviesprijs', 'catalogusprijs',
            'sale price', 'list price', 'prix vente', 'prix catalogue', 'bruto',
        ],
        'stock_quantity' => ['voorraad', 'stock', 'stock quantity', 'qty', 'quantite', 'aantal', 'aantal stuks'],
        'lead_time_days' => ['levertijd', 'levertermijn', 'lead time', 'delai', 'levertijd dagen'],
        'voltage'        => ['spanning', 'tension', 'voltage'],
        'phase'          => ['fase', 'phase', 'fasen'],
        'breaker_a'      => ['zekering', 'automaat', 'breaker', 'disjoncteur', 'zekering a'],
        'cable'          => ['kabel', 'cable', 'kabeltype', 'voedingskabel'],
        'liquid_pipe_diameter' => ['vloeistofleiding', 'liquid pipe', 'vloeistof diameter'],
        'gas_pipe_diameter'    => ['gasleiding', 'gas pipe', 'gas diameter', 'zuigleiding'],
        'max_pipe_length_m'    => ['max leidinglengte', 'maximale leidinglengte', 'max pipe length'],
        'max_pipe_length_per_unit_m' => ['max leidinglengte per unit', 'max pipe length per unit'],
        'max_height_difference_m'    => ['max hoogteverschil', 'maximaal hoogteverschil', 'max height difference'],
        'max_connected_indoor_units' => ['max binnenunits', 'max indoor units', 'aantal binnenunits'],
        'sound_level_db' => ['geluidsniveau', 'geluid', 'sound level', 'noise level', 'db', 'niveau sonore'],
        'seer'           => ['seer'],
        'scop'           => ['scop'],
        'wifi_included'  => ['wifi', 'wifi inbegrepen', 'wifi included'],
        'active'         => ['actief', 'active', 'beschikbaar', 'available'],
        'notes'          => ['opmerking', 'opmerkingen', 'notities', 'remarks', 'commentaar', 'remarques'],
    ];

    /**
     * Lower-case, strip punctuation/underscores to single spaces, collapse.
     */
    public static function normalize(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $header = str_replace(
            ['é', 'è', 'ê', 'ë', 'à', 'â', 'ç', 'î', 'ï', 'ô', 'û', 'ü'],
            ['e', 'e', 'e', 'e', 'a', 'a', 'c', 'i', 'i', 'o', 'u', 'u'],
            $header
        );
        $header = preg_replace('/[_\-.\/()\[\]:;,]+/u', ' ', $header) ?? $header;

        return trim(preg_replace('/\s+/u', ' ', $header) ?? $header);
    }

    /**
     * @param array<int, string|null> $headers source column headers
     * @return array<int, array{field: string|null, confidence: 'exact'|'fuzzy'|'none'}>
     */
    public function suggest(array $headers): array
    {
        $suggestions = [];
        foreach (array_values($headers) as $i => $header) {
            $suggestions[$i] = $this->suggestOne((string) ($header ?? ''));
        }

        // Two columns pointing at the same internal field can never be
        // auto-accepted — downgrade both to fuzzy so the admin must decide.
        $byField = [];
        foreach ($suggestions as $i => $s) {
            if ($s['field'] !== null) {
                $byField[$s['field']][] = $i;
            }
        }
        foreach ($byField as $indexes) {
            if (count($indexes) > 1) {
                foreach ($indexes as $i) {
                    $suggestions[$i]['confidence'] = 'fuzzy';
                }
            }
        }

        return $suggestions;
    }

    /** @return array{field: string|null, confidence: 'exact'|'fuzzy'|'none'} */
    private function suggestOne(string $header): array
    {
        $normalized = self::normalize($header);
        if ($normalized === '') {
            return ['field' => null, 'confidence' => 'none'];
        }

        // Internal column names always match exactly (template re-imports).
        foreach (HvacCsvImporter::COLUMNS as $field) {
            if ($normalized === self::normalize($field)) {
                return ['field' => $field, 'confidence' => 'exact'];
            }
        }

        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                if ($normalized === $alias) {
                    return ['field' => $field, 'confidence' => 'exact'];
                }
            }
        }

        // Containment (either direction, ≥4 chars) is only ever a hint.
        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (mb_strlen($alias) >= 4
                    && (str_contains($normalized, $alias) || str_contains($alias, $normalized))
                    && mb_strlen($normalized) >= 4) {
                    return ['field' => $field, 'confidence' => 'fuzzy'];
                }
            }
        }

        return ['field' => null, 'confidence' => 'none'];
    }

    /**
     * Alias hit test used by the header-row detector.
     */
    public function looksLikeHeaderCell(string $value): bool
    {
        return $this->suggestOne($value)['confidence'] === 'exact';
    }
}
