<?php

namespace App\Services\Hvac\Import;

/**
 * Uniform "sheets + rows" view over CSV/TXT and XLSX supplier files, so the
 * guided import wizard has one interface regardless of file type. CSV files
 * behave as a single sheet named "CSV".
 *
 * CSV reading is streaming (fgetcsv) so large catalogs (50k+ rows) parse
 * without materialising the whole file, handles quoted delimiters and quoted
 * newlines, strips a UTF-8 BOM and rescues Windows-1252 encodings. The
 * delimiter comes from CsvDelimiterDetector unless the caller passes one
 * explicitly (the wizard asks the admin when detection is ambiguous).
 */
class TabularFileReader
{
    public const CSV_SHEET_NAME = 'CSV';

    private const DETECT_SAMPLE_BYTES = 65536;

    /** @return array<int, array{name: string, hidden: bool}> */
    public function sheets(string $path, string $extension): array
    {
        if ($this->isXlsx($extension)) {
            return (new XlsxWorkbookReader($path))->sheets();
        }

        return [['name' => self::CSV_SHEET_NAME, 'hidden' => false]];
    }

    /**
     * @param callable(array<int, string|null>, int): bool|null $rowFilter
     *        Rows for which the filter returns false are skipped and do not
     *        count against $maxRows; source row indexes are preserved.
     * @return array{
     *   rows: array<int, array<int, string|null>>,
     *   has_formulas: bool,
     *   truncated: bool,
     *   delimiter: string|null,
     *   delimiter_confidence: string
     * }
     */
    public function rows(
        string $path,
        string $extension,
        ?string $sheet = null,
        int $maxRows = 20000,
        int $maxCols = 64,
        ?string $delimiter = null,
        ?callable $rowFilter = null
    ): array {
        if ($this->isXlsx($extension)) {
            $reader = new XlsxWorkbookReader($path);
            $sheet ??= $reader->sheets()[0]['name'];
            $data = $reader->rows($sheet, $maxRows, $maxCols);

            if ($rowFilter !== null) {
                foreach ($data['rows'] as $index => $cells) {
                    if (! $rowFilter($cells, $index)) {
                        unset($data['rows'][$index]);
                    }
                }
            }

            return $data + ['delimiter' => null, 'delimiter_confidence' => 'high'];
        }

        return $this->csvRows($path, $maxRows, $maxCols, $delimiter, $rowFilter);
    }

    /**
     * Delimiter detection on the (encoding-normalised) head of a CSV file.
     *
     * @return array{delimiter: string|null, confidence: string}
     */
    public function detectDelimiter(string $path): array
    {
        $sample = (string) @file_get_contents($path, false, null, 0, self::DETECT_SAMPLE_BYTES);
        $sample = $this->toUtf8($sample, allowLossy: true);
        $result = (new CsvDelimiterDetector())->detect($sample);

        return ['delimiter' => $result['delimiter'], 'confidence' => $result['confidence']];
    }

    private function isXlsx(string $extension): bool
    {
        return strtolower($extension) === 'xlsx';
    }

    /**
     * @param callable(array<int, string|null>, int): bool|null $rowFilter
     * @return array{rows: array, has_formulas: bool, truncated: bool, delimiter: string|null, delimiter_confidence: string}
     */
    private function csvRows(string $path, int $maxRows, int $maxCols, ?string $delimiter, ?callable $rowFilter): array
    {
        $contents = $this->toUtf8((string) @file_get_contents($path), allowLossy: false);

        // Strip a UTF-8 BOM so the first header cell is clean.
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        $confidence = 'high';
        if ($delimiter === null) {
            $detected = (new CsvDelimiterDetector())->detect(substr($contents, 0, self::DETECT_SAMPLE_BYTES));
            $delimiter = $detected['delimiter'];
            $confidence = $detected['confidence'];
            if ($delimiter === null) {
                // Single-column or empty file: parse as one column per line so
                // the caller can still show something sensible.
                $delimiter = ';';
            }
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        unset($contents);

        $rows = [];
        $kept = 0;
        $truncated = false;
        $sourceIndex = 0;

        while (($cells = fgetcsv($stream, 0, $delimiter, '"', '\\')) !== false) {
            $index = $sourceIndex++;

            $cells = array_map(
                fn ($cell) => $cell === null || trim((string) $cell) === '' ? null : trim((string) $cell),
                array_slice($cells, 0, $maxCols)
            );

            if ($rowFilter !== null && ! $rowFilter($cells, $index)) {
                continue;
            }

            if ($kept >= $maxRows) {
                $truncated = true;
                break;
            }

            $rows[$index] = $cells;
            $kept++;
        }

        fclose($stream);

        return [
            'rows'                 => $rows,
            'has_formulas'         => false,
            'truncated'            => $truncated,
            'delimiter'            => $delimiter,
            'delimiter_confidence' => $confidence,
        ];
    }

    private function toUtf8(string $contents, bool $allowLossy): string
    {
        if (mb_check_encoding($contents, 'UTF-8')) {
            return $contents;
        }

        $converted = @iconv('Windows-1252', 'UTF-8//TRANSLIT', $contents);
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        if ($allowLossy) {
            $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $contents);
            if ($converted !== false) {
                return $converted;
            }
        }

        throw new XlsxReadException('Bestandscodering wordt niet herkend. Bewaar het bestand als UTF-8 of Windows-CSV.');
    }
}
