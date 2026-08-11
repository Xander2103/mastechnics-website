<?php

namespace App\Services\Hvac\Import;

/**
 * Heuristic header-row detection for arbitrary supplier files: scans the
 * first rows for the one that most looks like a header (many distinct text
 * cells, alias hits, few numbers). The admin can always override the result
 * in the mapping wizard — this is a starting point, never an authority.
 */
class HeaderRowDetector
{
    public function __construct(
        private readonly ColumnMappingSuggester $suggester = new ColumnMappingSuggester(),
    ) {
    }

    /**
     * @param array<int, array<int, string|null>> $rows
     * @return array{row: int, confidence: 'high'|'low'}
     */
    public function detect(array $rows, int $scanLimit = 20): array
    {
        $bestRow = 0;
        $bestScore = PHP_FLOAT_MIN;
        $bestAliasHits = 0;

        foreach (array_slice($rows, 0, $scanLimit, true) as $index => $cells) {
            $nonEmpty = 0;
            $aliasHits = 0;
            $numeric = 0;
            $texts = [];

            foreach ($cells as $cell) {
                $value = trim((string) ($cell ?? ''));
                if ($value === '') {
                    continue;
                }
                $nonEmpty++;
                if (is_numeric(str_replace(',', '.', $value))) {
                    $numeric++;
                } else {
                    $texts[mb_strtolower($value)] = true;
                }
                if ($this->suggester->looksLikeHeaderCell($value)) {
                    $aliasHits++;
                }
            }

            if ($nonEmpty < 2) {
                continue;
            }

            $score = $nonEmpty + 3 * $aliasHits + count($texts) - 2 * $numeric;

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRow = $index;
                $bestAliasHits = $aliasHits;
            }
        }

        return [
            'row'        => $bestRow,
            'confidence' => $bestAliasHits >= 2 ? 'high' : 'low',
        ];
    }
}
