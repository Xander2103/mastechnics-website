<?php

namespace App\Services\Hvac\Import;

/**
 * Uniform "sheets + rows" view over CSV/TXT and XLSX supplier files, so the
 * guided import wizard has one interface regardless of file type. CSV files
 * behave as a single sheet named "CSV".
 */
class TabularFileReader
{
    public const CSV_SHEET_NAME = 'CSV';

    /** @return array<int, array{name: string, hidden: bool}> */
    public function sheets(string $path, string $extension): array
    {
        if ($this->isXlsx($extension)) {
            return (new XlsxWorkbookReader($path))->sheets();
        }

        return [['name' => self::CSV_SHEET_NAME, 'hidden' => false]];
    }

    /**
     * @return array{rows: array<int, array<int, string|null>>, has_formulas: bool, truncated: bool}
     */
    public function rows(string $path, string $extension, ?string $sheet = null, int $maxRows = 20000, int $maxCols = 64): array
    {
        if ($this->isXlsx($extension)) {
            $reader = new XlsxWorkbookReader($path);
            $sheet ??= $reader->sheets()[0]['name'];

            return $reader->rows($sheet, $maxRows, $maxCols);
        }

        return $this->csvRows($path, $maxRows, $maxCols);
    }

    private function isXlsx(string $extension): bool
    {
        return strtolower($extension) === 'xlsx';
    }

    /** @return array{rows: array<int, array<int, string|null>>, has_formulas: bool, truncated: bool} */
    private function csvRows(string $path, int $maxRows, int $maxCols): array
    {
        $contents = (string) @file_get_contents($path);

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $converted = @iconv('Windows-1252', 'UTF-8//TRANSLIT', $contents);
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                $contents = $converted;
            } else {
                throw new XlsxReadException('Bestandscodering wordt niet herkend. Bewaar het bestand als UTF-8 CSV.');
            }
        }

        $contents = str_replace("\r\n", "\n", trim($contents, "\xEF\xBB\xBF\n\r"));
        $lines = explode("\n", $contents);

        $firstLine = '';
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $firstLine = $line;
                break;
            }
        }
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $rows = [];
        $truncated = false;
        foreach ($lines as $i => $line) {
            if ($i >= $maxRows) {
                $truncated = true;
                break;
            }
            $cells = $line === '' ? [] : str_getcsv($line, $delimiter);
            $rows[$i] = array_map(
                fn ($cell) => $cell === null || trim((string) $cell) === '' ? null : trim((string) $cell),
                array_slice($cells, 0, $maxCols)
            );
        }

        return ['rows' => $rows, 'has_formulas' => false, 'truncated' => $truncated];
    }
}
