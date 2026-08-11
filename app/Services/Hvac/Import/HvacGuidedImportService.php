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

        // Supplier: a business fact the admin provides once for the file.
        if (($raw['supplier'] ?? '') === '' && trim((string) ($options['supplier'] ?? '')) !== '') {
            $raw['supplier'] = trim((string) $options['supplier']);
            $fields['supplier'] = 'manual';
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

        return [
            'fields'       => $fields,
            'price'        => $price,
            'ean'          => ($virtual['ean']['value'] ?? '') !== '' ? $virtual['ean']['value'] : null,
            'needs_review' => $needsReview,
        ];
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
     * Checks whether a saved profile still fits the uploaded file.
     *
     * @param array<int, array{name: string, hidden: bool}> $sheets
     * @param callable(string): array<int, array<int, string|null>> $rowsForSheet
     * @return array{
     *   ok: bool, problems: string[], sheet: string|null,
     *   header_row: int, column_map: array<int, string>
     * }
     */
    public function matchProfile(HvacMappingProfile $profile, array $sheets, callable $rowsForSheet): array
    {
        $problems = [];

        $sheet = $this->pickSheet($profile, $sheets);
        if ($sheet === null) {
            return [
                'ok'         => false,
                'problems'   => ["Geen werkblad gevonden dat past bij het profielpatroon \"{$profile->worksheet_name_pattern}\"."],
                'sheet'      => null,
                'header_row' => max(0, $profile->header_row - 1),
                'column_map' => [],
            ];
        }

        $rows = $rowsForSheet($sheet);
        $headerRow = max(0, $profile->header_row - 1);
        $headerCells = $rows[$headerRow] ?? [];

        $headerByName = [];
        foreach ($headerCells as $col => $cell) {
            $normalized = ColumnMappingSuggester::normalize((string) ($cell ?? ''));
            if ($normalized !== '' && ! isset($headerByName[$normalized])) {
                $headerByName[$normalized] = $col;
            }
        }

        $columnMap = [];
        foreach ((array) $profile->column_map as $sourceHeader => $field) {
            $normalized = ColumnMappingSuggester::normalize((string) $sourceHeader);
            if (! isset($headerByName[$normalized])) {
                $problems[] = "Kolom \"{$sourceHeader}\" uit het profiel is niet gevonden op rij {$profile->header_row} van werkblad \"{$sheet}\".";
                continue;
            }
            $columnMap[$headerByName[$normalized]] = (string) $field;
        }

        if ($headerCells === [] || array_filter($headerCells, fn ($c) => trim((string) ($c ?? '')) !== '') === []) {
            $problems[] = "Rij {$profile->header_row} van werkblad \"{$sheet}\" is leeg — de kolomkoppen staan vermoedelijk ergens anders.";
        }

        return [
            'ok'         => $problems === [],
            'problems'   => $problems,
            'sheet'      => $sheet,
            'header_row' => $headerRow,
            'column_map' => $columnMap,
        ];
    }

    /** @param array<int, array{name: string, hidden: bool}> $sheets */
    private function pickSheet(HvacMappingProfile $profile, array $sheets): ?string
    {
        $visible = array_values(array_filter($sheets, fn (array $s) => ! $s['hidden']));
        $candidates = $visible !== [] ? $visible : $sheets;

        $pattern = trim((string) $profile->worksheet_name_pattern);
        if ($pattern === '') {
            return $candidates[0]['name'] ?? null;
        }

        foreach ($candidates as $sheet) {
            if (mb_stripos($sheet['name'], $pattern) !== false) {
                return $sheet['name'];
            }
        }

        return null;
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
