<?php

namespace Tests\Feature\Hvac;

use App\Services\Hvac\Import\XlsxReadException;
use App\Services\Hvac\Import\XlsxWorkbookReader;
use Tests\TestCase;
use ZipArchive;

/**
 * The native XLSX reader used for supplier imports: read-only, never
 * evaluates formulas, rejects macro workbooks, guards against zip bombs.
 */
class HvacXlsxReaderTest extends TestCase
{
    /** @var string[] */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /**
     * Builds a minimal real XLSX (a zip of OOXML parts) without any
     * spreadsheet library. $sheets: name => rows (list of cell lists; cells
     * are strings/numbers/null or ['f' => formula, 'v' => cached]).
     */
    private function buildXlsx(array $sheets, array $options = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hvac-test-') . '.xlsx';
        $this->tempFiles[] = $path;

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $sheetNames = array_keys($sheets);
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        foreach ($sheetNames as $i => $name) {
            $n = $i + 1;
            $contentTypes .= "<Override PartName=\"/xl/worksheets/sheet{$n}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        }
        if ($options['shared_strings'] ?? false) {
            $contentTypes .= '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>';
        }
        $contentTypes .= '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($sheetNames as $i => $name) {
            $n = $i + 1;
            $hidden = in_array($name, $options['hidden'] ?? [], true) ? ' state="hidden"' : '';
            $workbook .= "<sheet name=\"" . htmlspecialchars($name, ENT_XML1) . "\" sheetId=\"{$n}\"{$hidden} r:id=\"rId{$n}\"/>";
            $rels .= "<Relationship Id=\"rId{$n}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$n}.xml\"/>";
        }
        $workbook .= '</sheets></workbook>';
        $sharedRelId = count($sheetNames) + 1;
        if ($options['shared_strings'] ?? false) {
            $rels .= "<Relationship Id=\"rId{$sharedRelId}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings\" Target=\"sharedStrings.xml\"/>";
        }
        $rels .= '</Relationships>';
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);

        if ($options['shared_strings'] ?? false) {
            $sst = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
            foreach ($options['shared_strings'] as $s) {
                $sst .= '<si><t>' . htmlspecialchars($s, ENT_XML1) . '</t></si>';
            }
            $sst .= '</sst>';
            $zip->addFromString('xl/sharedStrings.xml', $sst);
        }

        foreach (array_values($sheets) as $i => $rows) {
            $n = $i + 1;
            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
            foreach ($rows as $r => $cells) {
                $rowNum = $r + 1;
                $xml .= "<row r=\"{$rowNum}\">";
                foreach ($cells as $c => $cell) {
                    if ($cell === null) {
                        continue;
                    }
                    $ref = $this->cellRef($c, $rowNum);
                    if (is_array($cell) && isset($cell['f'])) {
                        $cached = htmlspecialchars((string) ($cell['v'] ?? ''), ENT_XML1);
                        $xml .= "<c r=\"{$ref}\"><f>" . htmlspecialchars($cell['f'], ENT_XML1) . "</f><v>{$cached}</v></c>";
                    } elseif (is_array($cell) && isset($cell['s'])) {
                        // shared-string reference by index
                        $xml .= "<c r=\"{$ref}\" t=\"s\"><v>{$cell['s']}</v></c>";
                    } elseif (is_int($cell) || is_float($cell)) {
                        $xml .= "<c r=\"{$ref}\"><v>{$cell}</v></c>";
                    } else {
                        $xml .= "<c r=\"{$ref}\" t=\"inlineStr\"><is><t>" . htmlspecialchars((string) $cell, ENT_XML1) . "</t></is></c>";
                    }
                }
                $xml .= '</row>';
            }
            $xml .= '</sheetData></worksheet>';
            $zip->addFromString("xl/worksheets/sheet{$n}.xml", $xml);
        }

        if ($options['macro'] ?? false) {
            $zip->addFromString('xl/vbaProject.bin', 'fake-macro-binary');
        }

        $zip->close();

        return $path;
    }

    private function cellRef(int $colIndex, int $rowNum): string
    {
        $letters = '';
        $n = $colIndex;
        do {
            $letters = chr(65 + ($n % 26)) . $letters;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $letters . $rowNum;
    }

    public function test_lists_sheets_including_hidden_state(): void
    {
        $path = $this->buildXlsx(
            ['Prijslijst' => [['a']], 'Interne info' => [['b']]],
            ['hidden' => ['Interne info']]
        );

        $sheets = (new XlsxWorkbookReader($path))->sheets();

        $this->assertSame([
            ['name' => 'Prijslijst', 'hidden' => false],
            ['name' => 'Interne info', 'hidden' => true],
        ], $sheets);
    }

    public function test_reads_rows_with_inline_strings_numbers_and_gaps(): void
    {
        $path = $this->buildXlsx(['Blad1' => [
            ['Artikelnummer', 'Omschrijving', 'Netto prijs'],
            ['ABC-123', null, 780.5],
            [],
            ['DEF-456', 'Buitenunit', 1250],
        ]]);

        $result = (new XlsxWorkbookReader($path))->rows('Blad1');

        $this->assertFalse($result['has_formulas']);
        $this->assertSame(['Artikelnummer', 'Omschrijving', 'Netto prijs'], $result['rows'][0]);
        $this->assertSame(['ABC-123', null, '780.5'], $result['rows'][1]);
        $this->assertSame([], array_filter($result['rows'][2] ?? []));
        $this->assertSame(['DEF-456', 'Buitenunit', '1250'], $result['rows'][3]);
    }

    public function test_reads_shared_strings(): void
    {
        $path = $this->buildXlsx(
            ['Blad1' => [[['s' => 0], ['s' => 1]], [['s' => 1], 'inline']]],
            ['shared_strings' => ['SKU', 'Naam']]
        );

        $result = (new XlsxWorkbookReader($path))->rows('Blad1');

        $this->assertSame(['SKU', 'Naam'], $result['rows'][0]);
        $this->assertSame(['Naam', 'inline'], $result['rows'][1]);
    }

    public function test_formulas_are_never_evaluated_only_cached_values_used(): void
    {
        $path = $this->buildXlsx(['Blad1' => [
            ['prijs', ['f' => 'A2*2', 'v' => '42']],
        ]]);

        $result = (new XlsxWorkbookReader($path))->rows('Blad1');

        $this->assertTrue($result['has_formulas']);
        $this->assertSame('42', $result['rows'][0][1]);
    }

    public function test_macro_workbook_is_rejected(): void
    {
        $path = $this->buildXlsx(['Blad1' => [['a']]], ['macro' => true]);

        $this->expectException(XlsxReadException::class);
        $this->expectExceptionMessageMatches('/macro/i');

        (new XlsxWorkbookReader($path))->sheets();
    }

    public function test_non_xlsx_file_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'hvac-test-') . '.xlsx';
        $this->tempFiles[] = $path;
        file_put_contents($path, 'dit is geen zipbestand');

        $this->expectException(XlsxReadException::class);

        (new XlsxWorkbookReader($path))->sheets();
    }

    public function test_zip_without_workbook_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'hvac-test-') . '.xlsx';
        $this->tempFiles[] = $path;
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('random.txt', 'x');
        $zip->close();

        $this->expectException(XlsxReadException::class);

        (new XlsxWorkbookReader($path))->sheets();
    }

    public function test_row_limit_is_respected(): void
    {
        $rows = [];
        for ($i = 0; $i < 30; $i++) {
            $rows[] = ["rij {$i}"];
        }
        $path = $this->buildXlsx(['Blad1' => $rows]);

        $result = (new XlsxWorkbookReader($path))->rows('Blad1', maxRows: 10);

        $this->assertCount(10, $result['rows']);
        $this->assertTrue($result['truncated']);
    }

    public function test_unknown_sheet_name_is_rejected(): void
    {
        $path = $this->buildXlsx(['Blad1' => [['a']]]);

        $this->expectException(XlsxReadException::class);

        (new XlsxWorkbookReader($path))->rows('Bestaat niet');
    }
}
