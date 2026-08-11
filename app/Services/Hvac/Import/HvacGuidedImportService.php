<?php

namespace App\Services\Hvac\Import;

use App\Models\HvacMappingProfile;
use App\Services\Hvac\HvacCsvImporter;

/**
 * Pure helpers for the guided mapping import: turning mapped sheet rows into
 * the raw rows HvacCsvImporter validates, and checking whether a saved
 * mapping profile still matches an uploaded file. No file or DB writes here.
 */
class HvacGuidedImportService
{
    /** Conservative name keywords per product type (normalized text). */
    private const TYPE_KEYWORDS = [
        'indoor_unit'      => ['binnenunit', 'binnendeel', 'indoor unit', 'unite interieure', 'clim murale', 'wandmodel', 'cassette'],
        'outdoor_unit'     => ['buitenunit', 'buitendeel', 'outdoor unit', 'unite exterieure', 'groupe exterieur'],
        'single_split_set' => ['single split', 'monosplit set', 'mono split set'],
    ];

    /**
     * Applies a column mapping to sheet rows and returns the raw rows for
     * HvacCsvImporter::validateRows(), plus per-row provenance. Data rows
     * start AFTER the header row; fully empty rows are skipped. 'line' is the
     * 1-based sheet row number so error reports point at the actual row in
     * the supplier file.
     *
     * Business decisions arrive via $options and are recorded as provenance —
     * the system derives, the admin decides:
     * - supplier: fills the supplier field when no column is mapped
     * - price_meaning: 'net_purchase'|'sale' route the virtual 'price' column
     *   into the matching price field; 'gross'|'unknown' keep the price OUT of
     *   the catalog price columns (not safely priced) and flag review
     * - category: ['column' => int, 'selected' => string[]] row filter
     * - type_fallback: product type for rows whose type cannot be inferred
     * - header_cells: column index => header text, for provenance labels
     *
     * @param array<int, array<int, string|null>> $rows        all sheet rows (0-based)
     * @param int                                  $headerRow   0-based header row index
     * @param array<int, string>                   $columnMap   column index => internal or virtual field
     * @return array<int, array{line: int, raw: array<string, string>, provenance: array}>
     */
    public function normalizeRows(array $rows, int $headerRow, array $columnMap, string $decimalFormat = 'auto', array $options = []): array
    {
        $numericFields = HvacCsvImporter::numericFields();
        $headerCells = (array) ($options['header_cells'] ?? []);
        $category = $options['category'] ?? null;
        $priceMeaning = $options['price_meaning'] ?? null;
        $rawRows = [];

        foreach ($rows as $index => $cells) {
            if ($index <= $headerRow) {
                continue;
            }

            if ($category !== null && $category['selected'] !== []) {
                $value = trim((string) ($cells[$category['column']] ?? ''));
                if (! in_array($value, $category['selected'], true)) {
                    continue;
                }
            }

            $raw = [];
            $virtual = [];
            $fields = [];
            $hasValue = false;

            foreach ($columnMap as $col => $field) {
                $value = trim((string) ($cells[$col] ?? ''));
                // Supplier placeholder for "no value" ("-", "--"): treat as
                // empty so derivations (e.g. model ← SKU) can step in and the
                // formula-injection guard has nothing to reject.
                if (preg_match('/^-+$/', $value) === 1) {
                    $value = '';
                }
                if ($value !== '') {
                    $hasValue = true;
                }
                if (in_array($field, ColumnMappingSuggester::VIRTUAL_FIELDS, true)) {
                    $virtual[$field] = ['value' => $value, 'column' => (string) ($headerCells[$col] ?? 'kolom ' . ($col + 1))];
                    continue;
                }
                if (in_array($field, $numericFields, true)) {
                    $value = $this->normalizeDecimal($value, $decimalFormat);
                }
                $raw[$field] = $value;
                $fields[$field] = 'column:' . (string) ($headerCells[$col] ?? 'kolom ' . ($col + 1));
            }

            if (! $hasValue) {
                continue; // empty spacer row — never an error
            }

            $provenance = $this->applyDerivations($raw, $fields, $virtual, $options, $decimalFormat);
            if ($category !== null) {
                $provenance['category'] = trim((string) ($cells[$category['column']] ?? ''));
            }
            $provenance['source_row'] = $index + 1;

            $rawRows[] = ['line' => $index + 1, 'raw' => $raw, 'provenance' => $provenance];
        }

        return $rawRows;
    }

    /**
     * Explicit, visible derivation rules. Mutates $raw/$fields and returns the
     * provenance block for the row.
     *
     * @param array<string, string>                                  $raw
     * @param array<string, string>                                  $fields
     * @param array<string, array{value: string, column: string}>    $virtual
     */
    private function applyDerivations(array &$raw, array &$fields, array $virtual, array $options, string $decimalFormat): array
    {
        $needsReview = false;

        // Supplier/brand: business facts the admin provides once for the file
        // when no column carries them.
        if (($raw['supplier'] ?? '') === '' && trim((string) ($options['supplier'] ?? '')) !== '') {
            $raw['supplier'] = trim((string) $options['supplier']);
            $fields['supplier'] = 'manual';
        }
        if (($raw['brand'] ?? '') === '' && trim((string) ($options['brand'] ?? '')) !== '') {
            $raw['brand'] = trim((string) $options['brand']);
            $fields['brand'] = 'manual';
        }

        // Name: prefer the primary label, fall back to the secondary (e.g. FR).
        $fallback = $virtual['name_fallback'] ?? null;
        if (($raw['name'] ?? '') === '' && $fallback !== null && $fallback['value'] !== '') {
            $raw['name'] = $fallback['value'];
            $fields['name'] = 'derived:' . $fallback['column'];
        }

        // Model: supplier catalogs often have no separate model — the SKU is
        // the only identifier. Explicit, visible fallback.
        if (($raw['model'] ?? '') === '' && ($raw['sku'] ?? '') !== '') {
            $raw['model'] = $raw['sku'];
            $fields['model'] = 'derived:sku';
        }

        // Price semantics: only a price whose meaning is known lands in the
        // catalog price columns. Gross/unknown prices stay provenance-only so
        // the product is never treated as safely priced for quotations.
        $price = null;
        if (isset($virtual['price']) && $virtual['price']['value'] !== '') {
            $value = $this->normalizeDecimal($virtual['price']['value'], $decimalFormat);
            $price = [
                'column'  => $virtual['price']['column'],
                'raw'     => $virtual['price']['value'],
                'meaning' => $priceMeaningLabel = (string) ($options['price_meaning'] ?? 'unknown'),
            ];
            if ($priceMeaningLabel === 'net_purchase') {
                $raw['purchase_price_excl_vat'] = $value;
                $fields['purchase_price_excl_vat'] = 'column:' . $virtual['price']['column'];
            } elseif ($priceMeaningLabel === 'sale') {
                $raw['sale_price_excl_vat'] = $value;
                $fields['sale_price_excl_vat'] = 'column:' . $virtual['price']['column'];
            } else {
                $needsReview = true; // gross or unknown: not safely priced
            }
        }

        // Product type: conservative name-based inference, then the admin's
        // fallback choice, never a fuzzy guess.
        if (($raw['product_type'] ?? '') === '') {
            $inferred = $this->inferProductType((string) ($raw['name'] ?? ''));
            if ($inferred !== null) {
                $raw['product_type'] = $inferred;
                $fields['product_type'] = 'derived:naam';
            } elseif (trim((string) ($options['type_fallback'] ?? '')) !== '') {
                $raw['product_type'] = trim((string) $options['type_fallback']);
                $fields['product_type'] = 'manual:fallback';
                $needsReview = true;
            } else {
                $raw['product_type'] = '';
                $needsReview = true;
            }
        }

        // Cooling capacity from the product name ("... 2,5 kW" / "10500W"):
        // units without a capacity are rejected by the importer, and
        // wholesaler catalogs rarely have a structured capacity column.
        // Explicit derivation, always flagged for review — the engine may
        // only rely on it after Martin checked it.
        if (($raw['cooling_capacity_kw'] ?? '') === ''
            && in_array($raw['product_type'] ?? '', ['indoor_unit', 'outdoor_unit', 'single_split_set', 'multi_split_outdoor'], true)) {
            $capacity = $this->capacityFromName((string) ($raw['name'] ?? ''));
            if ($capacity !== null) {
                $raw['cooling_capacity_kw'] = $capacity;
                $fields['cooling_capacity_kw'] = 'derived:naam';
                $needsReview = true;
            }
        }

        return [
            'fields'       => $fields,
            'price'        => $price,
            'ean'          => ($virtual['ean']['value'] ?? '') !== '' ? $virtual['ean']['value'] : null,
            'needs_review' => $needsReview,
        ];
    }

    /**
     * "2,5 kW" → "2.5"; "10500W" → "10.5". Watts only in the plausible unit
     * range (500–30000 W) so voltages and article numbers never match.
     */
    private function capacityFromName(string $name): ?string
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*kw\b/i', $name, $m) === 1) {
            return str_replace(',', '.', $m[1]);
        }

        if (preg_match('/(?<![\dkK.,])(\d{3,5})\s*[wW]\b/', $name, $m) === 1) {
            $watts = (int) $m[1];
            if ($watts >= 500 && $watts <= 30000) {
                return (string) round($watts / 1000, 2);
            }
        }

        return null;
    }

    private function inferProductType(string $name): ?string
    {
        $normalized = ColumnMappingSuggester::normalize($name);
        if ($normalized === '') {
            return null;
        }

        foreach (self::TYPE_KEYWORDS as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * Finds the saved profile that recognizes this file by its header
     * signature: every header the profile was saved with must be present in
     * the uploaded file. The best (most headers matched, most recently
     * updated) active profile wins; none → null and the wizard runs manually.
     *
     * @param array<int, string|null> $headerCells
     */
    public function recognizeProfile(array $headerCells): ?HvacMappingProfile
    {
        $fileHeaders = [];
        foreach ($headerCells as $cell) {
            $normalized = ColumnMappingSuggester::normalize((string) ($cell ?? ''));
            if ($normalized !== '') {
                $fileHeaders[$normalized] = true;
            }
        }
        if ($fileHeaders === []) {
            return null;
        }

        $best = null;
        $bestScore = 0;
        foreach (HvacMappingProfile::where('is_active', true)->orderByDesc('updated_at')->get() as $profile) {
            $signature = array_filter(array_map(
                fn ($h) => ColumnMappingSuggester::normalize((string) $h),
                (array) ($profile->source_headers ?? [])
            ));
            if ($signature === []) {
                continue;
            }
            foreach ($signature as $header) {
                if (! isset($fileHeaders[$header])) {
                    continue 2;
                }
            }
            if (count($signature) > $bestScore) {
                $best = $profile;
                $bestScore = count($signature);
            }
        }

        return $best;
    }

    private function normalizeDecimal(string $value, string $format): string
    {
        if ($value === '') {
            return $value;
        }

        return match ($format) {
            // 1.234,56 → 1234.56
            'comma' => str_replace(',', '.', str_replace('.', '', $value)),
            // 1,234.56 → 1234.56
            'point' => str_replace(',', '', $value),
            // 'auto': the importer itself already accepts comma decimals.
            default => $value,
        };
    }
}
