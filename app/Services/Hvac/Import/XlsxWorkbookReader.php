<?php

namespace App\Services\Hvac\Import;

use XMLReader;
use ZipArchive;

/**
 * Minimal, safe, read-only XLSX reader for supplier imports.
 *
 * Deliberately NOT a spreadsheet engine:
 * - formulas are never evaluated — only their cached values are read, and the
 *   caller is told formulas were present;
 * - macro workbooks (vbaProject.bin) are rejected outright;
 * - zip-bomb guards: entry count and uncompressed-size caps before reading;
 * - XML is parsed with libxml NONET (no network), entities are not expanded
 *   and documents containing a DOCTYPE are rejected (XXE/billion-laughs);
 * - only cell text is extracted: styles, charts, images are ignored.
 */
class XlsxWorkbookReader
{
    private const MAX_ENTRIES = 2000;
    private const MAX_TOTAL_UNCOMPRESSED = 400 * 1024 * 1024; // 400 MB
    private const MAX_ENTRY_UNCOMPRESSED = 200 * 1024 * 1024; // 200 MB

    private ZipArchive $zip;

    /** @var array<int, array{name: string, hidden: bool, target: string}> */
    private array $sheets = [];

    /** @var string[]|null lazily loaded shared strings */
    private ?array $sharedStrings = null;

    public function __construct(private readonly string $path)
    {
        $this->zip = new ZipArchive();
        if ($this->zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new XlsxReadException('Het bestand is geen geldig XLSX-bestand (kan het archief niet openen).');
        }

        $this->guardAgainstZipBombs();

        if ($this->zip->locateName('xl/vbaProject.bin') !== false) {
            throw new XlsxReadException('Het bestand bevat macro\'s en is geweigerd. Bewaar het als een gewoon .xlsx-werkboek zonder macro\'s.');
        }

        if ($this->zip->locateName('xl/workbook.xml') === false) {
            throw new XlsxReadException('Het bestand is geen geldig XLSX-werkboek (xl/workbook.xml ontbreekt).');
        }

        $this->readWorkbookStructure();
    }

    public function __destruct()
    {
        // ZipArchive may already be closed; ignore.
        @$this->zip->close();
    }

    /** @return array<int, array{name: string, hidden: bool}> */
    public function sheets(): array
    {
        return array_map(
            fn (array $s) => ['name' => $s['name'], 'hidden' => $s['hidden']],
            $this->sheets
        );
    }

    /**
     * Reads the cell text of one sheet.
     *
     * @return array{rows: array<int, array<int, string|null>>, has_formulas: bool, truncated: bool}
     */
    public function rows(string $sheetName, int $maxRows = 20000, int $maxCols = 64): array
    {
        $sheet = null;
        foreach ($this->sheets as $candidate) {
            if ($candidate['name'] === $sheetName) {
                $sheet = $candidate;
                break;
            }
        }
        if ($sheet === null) {
            throw new XlsxReadException("Werkblad \"{$sheetName}\" bestaat niet in dit bestand.");
        }

        $xml = $this->entry($sheet['target']);
        $reader = $this->openXml($xml, "werkblad \"{$sheetName}\"");

        $rows = [];
        $hasFormulas = false;
        $truncated = false;

        $currentRow = null;
        $rowIndex = -1;
        $cellRef = null;
        $cellType = null;
        $cellHasFormula = false;
        $pendingValue = null;
        $inCell = false;
        $valueContext = null; // 'v' | 'is'

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT) {
                switch ($reader->localName) {
                    case 'row':
                        $rowIndex = ((int) $reader->getAttribute('r')) - 1;
                        if ($rowIndex < 0) {
                            $rowIndex = count($rows);
                        }
                        if ($rowIndex >= $maxRows) {
                            $truncated = true;
                            break 2;
                        }
                        $currentRow = [];
                        break;
                    case 'c':
                        $inCell = true;
                        $cellRef = $reader->getAttribute('r');
                        $cellType = $reader->getAttribute('t') ?? 'n';
                        $cellHasFormula = false;
                        $pendingValue = null;
                        break;
                    case 'f':
                        if ($inCell) {
                            $hasFormulas = true;
                            $cellHasFormula = true;
                        }
                        break;
                    case 'v':
                        $valueContext = 'v';
                        break;
                    case 't':
                        if ($inCell) {
                            $valueContext = 'is';
                        }
                        break;
                }
            } elseif ($reader->nodeType === XMLReader::TEXT || $reader->nodeType === XMLReader::CDATA) {
                if ($inCell && $valueContext !== null) {
                    $pendingValue = ($pendingValue ?? '') . $reader->value;
                }
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT) {
                switch ($reader->localName) {
                    case 'v':
                    case 't':
                        $valueContext = null;
                        break;
                    case 'c':
                        if ($currentRow !== null && $cellRef !== null) {
                            $col = $this->columnIndex($cellRef);
                            if ($col !== null && $col < $maxCols) {
                                $currentRow[$col] = $this->cellText($cellType, $pendingValue, $cellHasFormula);
                            }
                        }
                        $inCell = false;
                        $cellRef = null;
                        $pendingValue = null;
                        break;
                    case 'row':
                        if ($currentRow !== null && $rowIndex >= 0) {
                            $rows[$rowIndex] = $this->fillGaps($currentRow);
                        }
                        $currentRow = null;
                        break;
                }
            }
        }
        $reader->close();

        // Dense, 0-based, gap-free row list (missing rows become empty rows).
        $dense = [];
        $lastIndex = $rows === [] ? -1 : max(array_keys($rows));
        for ($i = 0; $i <= $lastIndex; $i++) {
            $dense[$i] = $rows[$i] ?? [];
        }

        return ['rows' => $dense, 'has_formulas' => $hasFormulas, 'truncated' => $truncated];
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private function guardAgainstZipBombs(): void
    {
        if ($this->zip->numFiles > self::MAX_ENTRIES) {
            throw new XlsxReadException('Het bestand bevat te veel onderdelen en is geweigerd.');
        }

        $total = 0;
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $stat = $this->zip->statIndex($i);
            if ($stat === false) {
                continue;
            }
            if ($stat['size'] > self::MAX_ENTRY_UNCOMPRESSED) {
                throw new XlsxReadException('Een onderdeel van het bestand is onrealistisch groot; het bestand is geweigerd.');
            }
            $total += $stat['size'];
        }
        if ($total > self::MAX_TOTAL_UNCOMPRESSED) {
            throw new XlsxReadException('De uitgepakte inhoud van het bestand is onrealistisch groot; het bestand is geweigerd.');
        }
    }

    private function entry(string $name): string
    {
        $contents = $this->zip->getFromName($name);
        if ($contents === false) {
            throw new XlsxReadException("Het bestand is beschadigd of onvolledig ({$name} ontbreekt).");
        }

        return $contents;
    }

    private function openXml(string $xml, string $label): XMLReader
    {
        $reader = XMLReader::XML($xml, 'UTF-8', LIBXML_NONET | LIBXML_COMPACT);
        if ($reader === false) {
            throw new XlsxReadException("De XML van {$label} kan niet gelezen worden.");
        }

        // Reject any DOCTYPE outright (XXE / entity-expansion vectors).
        if (preg_match('/<!DOCTYPE/i', substr($xml, 0, 4096)) === 1) {
            throw new XlsxReadException("De XML van {$label} bevat een DOCTYPE en is geweigerd.");
        }

        return $reader;
    }

    private function readWorkbookStructure(): void
    {
        // Relationship id → target path.
        $targets = [];
        $relsXml = $this->zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relsXml !== false) {
            $reader = $this->openXml($relsXml, 'de werkboekrelaties');
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'Relationship') {
                    $target = (string) $reader->getAttribute('Target');
                    $target = ltrim($target, '/');
                    if (! str_starts_with($target, 'xl/')) {
                        $target = 'xl/' . $target;
                    }
                    $targets[(string) $reader->getAttribute('Id')] = $target;
                }
            }
            $reader->close();
        }

        $reader = $this->openXml($this->entry('xl/workbook.xml'), 'het werkboek');
        $fallbackIndex = 0;
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'sheet') {
                $fallbackIndex++;
                $rid = $reader->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')
                    ?: $reader->getAttribute('r:id');
                $this->sheets[] = [
                    'name'   => (string) $reader->getAttribute('name'),
                    'hidden' => in_array($reader->getAttribute('state'), ['hidden', 'veryHidden'], true),
                    'target' => $targets[$rid] ?? "xl/worksheets/sheet{$fallbackIndex}.xml",
                ];
            }
        }
        $reader->close();

        if ($this->sheets === []) {
            throw new XlsxReadException('Het werkboek bevat geen werkbladen.');
        }
    }

    /** @return string[] */
    private function sharedStrings(): array
    {
        if ($this->sharedStrings !== null) {
            return $this->sharedStrings;
        }

        $this->sharedStrings = [];
        $xml = $this->zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return $this->sharedStrings;
        }

        $reader = $this->openXml($xml, 'de teksttabel');
        $current = null;
        $inText = false;
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                $current = '';
            } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't') {
                // Concatenate all <t> parts (rich text runs) within one <si>.
                $inText = ! $reader->isEmptyElement;
            } elseif (($reader->nodeType === XMLReader::TEXT || $reader->nodeType === XMLReader::CDATA) && $inText) {
                $current = ($current ?? '') . $reader->value;
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 't') {
                $inText = false;
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si') {
                $this->sharedStrings[] = $current ?? '';
                $current = null;
            }
        }
        $reader->close();

        return $this->sharedStrings;
    }

    private function cellText(?string $type, ?string $raw, bool $hasFormula): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return match ($type) {
            's'     => $this->sharedStrings()[(int) $raw] ?? null,
            'b'     => $raw === '1' ? 'true' : 'false',
            'e'     => null, // error cells (#N/A, #NAME?) carry no usable value
            default => $raw, // n, str, inlineStr text — cached value for formulas
        };
    }

    /** "BC12" → 54 (0-based column index), null when unparsable. */
    private function columnIndex(string $cellRef): ?int
    {
        if (preg_match('/^([A-Z]+)\d+$/', strtoupper($cellRef), $m) !== 1) {
            return null;
        }

        $index = 0;
        foreach (str_split($m[1]) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return $index - 1;
    }

    /** @param array<int, string|null> $cells */
    private function fillGaps(array $cells): array
    {
        if ($cells === []) {
            return [];
        }

        $max = max(array_keys($cells));
        $row = [];
        for ($i = 0; $i <= $max; $i++) {
            $row[$i] = $cells[$i] ?? null;
        }

        return $row;
    }
}
