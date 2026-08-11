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
    /**
     * Applies a column mapping to sheet rows and returns the raw rows for
     * HvacCsvImporter::validateRows(). Data rows start AFTER the header row;
     * fully empty rows are skipped. 'line' is the 1-based sheet row number so
     * error reports point at the actual row in the supplier file.
     *
     * @param array<int, array<int, string|null>> $rows        all sheet rows (0-based)
     * @param int                                  $headerRow   0-based header row index
     * @param array<int, string>                   $columnMap   column index => internal field
     * @return array<int, array{line: int, raw: array<string, string>}>
     */
    public function normalizeRows(array $rows, int $headerRow, array $columnMap, string $decimalFormat = 'auto'): array
    {
        $numericFields = HvacCsvImporter::numericFields();
        $rawRows = [];

        foreach ($rows as $index => $cells) {
            if ($index <= $headerRow) {
                continue;
            }

            $raw = [];
            $hasValue = false;
            foreach ($columnMap as $col => $field) {
                $value = trim((string) ($cells[$col] ?? ''));
                if ($value !== '') {
                    $hasValue = true;
                }
                if (in_array($field, $numericFields, true)) {
                    $value = $this->normalizeDecimal($value, $decimalFormat);
                }
                $raw[$field] = $value;
            }

            if (! $hasValue) {
                continue; // empty spacer row — never an error
            }

            $rawRows[] = ['line' => $index + 1, 'raw' => $raw];
        }

        return $rawRows;
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
