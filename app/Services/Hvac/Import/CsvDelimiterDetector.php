<?php

namespace App\Services\Hvac\Import;

/**
 * Detects the column delimiter of a CSV/TXT supplier file by parsing a sample
 * of lines with each candidate delimiter and scoring how consistently it
 * produces the same multi-column layout. Quote-aware (a ";" inside "..." is
 * not a delimiter). When two candidates are equally plausible the result is
 * 'ambiguous' and the wizard asks the admin instead of guessing silently.
 */
class CsvDelimiterDetector
{
    public const CANDIDATES = [';', ',', "\t", '|'];

    private const SAMPLE_LINES = 25;
    private const MIN_CONSISTENCY = 0.9;

    /**
     * @return array{
     *   delimiter: string|null,
     *   confidence: 'high'|'ambiguous',
     *   candidates: array<string, array{columns: int, consistency: float}>
     * }
     */
    public function detect(string $sampleText): array
    {
        $lines = $this->sampleLines($sampleText);
        $stats = [];

        foreach (self::CANDIDATES as $delimiter) {
            $stats[$delimiter] = $this->scoreCandidate($lines, $delimiter);
        }

        if ($lines === []) {
            return ['delimiter' => null, 'confidence' => 'ambiguous', 'candidates' => $stats];
        }

        // Viable: at least two columns, and the same column count on (nearly)
        // every sampled line.
        $viable = array_filter(
            $stats,
            fn (array $s) => $s['columns'] >= 2 && $s['consistency'] >= self::MIN_CONSISTENCY
        );

        if ($viable === []) {
            return ['delimiter' => null, 'confidence' => 'ambiguous', 'candidates' => $stats];
        }

        // More columns wins; consistency breaks ties in ordering but an exact
        // column-count tie between different delimiters stays ambiguous.
        uasort($viable, fn (array $a, array $b) => [$b['columns'], $b['consistency']] <=> [$a['columns'], $a['consistency']]);

        $delimiters = array_keys($viable);
        $best = $viable[$delimiters[0]];
        $runnerUp = $delimiters[1] ?? null;

        $confidence = ($runnerUp !== null && $viable[$runnerUp]['columns'] === $best['columns'])
            ? 'ambiguous'
            : 'high';

        return [
            'delimiter'  => (string) $delimiters[0],
            'confidence' => $confidence,
            'candidates' => $stats,
        ];
    }

    /** @return string[] */
    private function sampleLines(string $sampleText): array
    {
        $sampleText = str_replace(["\r\n", "\r"], "\n", $sampleText);
        $lines = [];

        foreach (explode("\n", $sampleText) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $lines[] = $line;
            if (count($lines) >= self::SAMPLE_LINES) {
                break;
            }
        }

        return $lines;
    }

    /**
     * @param string[] $lines
     * @return array{columns: int, consistency: float}
     */
    private function scoreCandidate(array $lines, string $delimiter): array
    {
        if ($lines === []) {
            return ['columns' => 0, 'consistency' => 0.0];
        }

        $countPerLine = [];
        foreach ($lines as $line) {
            $countPerLine[] = count(str_getcsv($line, $delimiter, '"', '\\'));
        }

        // Modal column count and the fraction of lines that match it.
        $frequency = array_count_values($countPerLine);
        arsort($frequency);
        $modalColumns = (int) array_key_first($frequency);
        $consistency = $frequency[$modalColumns] / count($countPerLine);

        return ['columns' => $modalColumns, 'consistency' => $consistency];
    }
}
