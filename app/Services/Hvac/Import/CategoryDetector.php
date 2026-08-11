<?php

namespace App\Services\Hvac\Import;

/**
 * Generic category-column detection for supplier files: finds the text column
 * that most looks like a product category (few distinct values relative to the
 * number of rows, category-ish header name) so the wizard can offer "Welke
 * producten wilt u importeren?" without hardcoded knowledge of any supplier.
 *
 * Advisory only: the admin sees the detected categories with counts and always
 * decides the selection; nothing is excluded silently.
 */
class CategoryDetector
{
    /** Ordered: earlier hints outrank later ones (group > family > rubric). */
    public const NAME_HINTS = [
        'groupname', 'group name', 'groep', 'groupe', 'group',
        'categorie', 'category', 'categorie produit',
        'familyname', 'family name', 'famille', 'family', 'familie',
        'rubricname', 'rubric', 'rubriek', 'rubrique',
        'soort', 'type produit', 'assortiment',
    ];

    /** Substrings (accent-normalised, lowercase) that mark an HVAC-relevant category. */
    private const HVAC_KEYWORDS = [
        'clim', 'airco', 'air condition', 'aircondition', 'split',
        'warmtepomp', 'pompe a chaleur', 'pompes a chaleur', 'heat pump',
        'ventilat', 'koeling', 'koelcel', 'refriger',
    ];

    private const MAX_DISTINCT = 500;
    private const MAX_DISTINCT_RATIO = 0.5;

    /**
     * @param array<int, string|null>              $headerCells
     * @param array<int, array<int, string|null>>  $dataRows     rows AFTER the header row
     * @return array{column: int|null, alternatives: int[], values: array<string, int>}
     */
    public function detect(array $headerCells, array $dataRows): array
    {
        $candidates = [];

        foreach ($headerCells as $col => $header) {
            $stats = $this->columnStats($dataRows, (int) $col);
            if ($stats === null) {
                continue;
            }

            $candidates[(int) $col] = [
                'score'  => $this->score((string) ($header ?? ''), $stats),
                'values' => $stats['values'],
            ];
        }

        if ($candidates === []) {
            return ['column' => null, 'alternatives' => [], 'values' => []];
        }

        uasort($candidates, fn (array $a, array $b) => $b['score'] <=> $a['score']);
        $columns = array_keys($candidates);
        $best = (int) array_shift($columns);

        return [
            'column'       => $best,
            'alternatives' => $columns,
            'values'       => $candidates[$best]['values'],
        ];
    }

    /** Whether a category value looks HVAC-related (used to pre-select, never to exclude). */
    public function hvacLikely(string $value): bool
    {
        $normalized = ColumnMappingSuggester::normalize($value);

        foreach (self::HVAC_KEYWORDS as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Value counts for one column across rows (for use after the admin picked
     * a different column, or for a streaming second pass).
     *
     * @param iterable<int, array<int, string|null>> $dataRows
     * @return array<string, int>
     */
    public function countValues(iterable $dataRows, int $column): array
    {
        $values = [];
        foreach ($dataRows as $cells) {
            $value = trim((string) ($cells[$column] ?? ''));
            if ($value !== '') {
                $values[$value] = ($values[$value] ?? 0) + 1;
            }
        }
        arsort($values);

        return $values;
    }

    /**
     * @param array<int, array<int, string|null>> $dataRows
     * @return array{values: array<string, int>, non_empty: int, distinct: int}|null
     *         null when the column cannot be a category (numeric, constant,
     *         nearly unique, or mostly empty).
     */
    private function columnStats(array $dataRows, int $col): ?array
    {
        $values = [];
        $nonEmpty = 0;
        $numeric = 0;

        foreach ($dataRows as $cells) {
            $value = trim((string) ($cells[$col] ?? ''));
            if ($value === '') {
                continue;
            }
            $nonEmpty++;
            if (is_numeric(str_replace([',', ' '], ['.', ''], $value))) {
                $numeric++;
            }
            $values[$value] = ($values[$value] ?? 0) + 1;
        }

        $distinct = count($values);

        if ($nonEmpty < 2 || $distinct < 2 || $distinct > self::MAX_DISTINCT) {
            return null;
        }
        if ($distinct / $nonEmpty > self::MAX_DISTINCT_RATIO) {
            return null; // nearly unique → identifier, not a category
        }
        if ($numeric / $nonEmpty > 0.5) {
            return null; // numeric column (IDs, prices, weights)
        }

        arsort($values);

        return ['values' => $values, 'non_empty' => $nonEmpty, 'distinct' => $distinct];
    }

    /** @param array{values: array<string, int>, non_empty: int, distinct: int} $stats */
    private function score(string $header, array $stats): float
    {
        $normalized = ColumnMappingSuggester::normalize($header);
        $score = 0.0;

        foreach (self::NAME_HINTS as $i => $hint) {
            if ($normalized === $hint || str_contains($normalized, $hint)) {
                $score += 1000.0 - 10.0 * $i;
                break;
            }
        }

        // Moderate cardinality is the most useful granularity: prefer the
        // candidate whose distinct-value count is closest to ~50.
        $score += 100.0 - min(100.0, abs($stats['distinct'] - 50) * 1.0);

        return $score;
    }
}
