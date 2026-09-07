<?php

namespace Tests\Unit\Hvac;

use App\Services\Hvac\Import\TabularFileReader;
use App\Services\Hvac\Import\XlsxReadException;
use App\Services\Hvac\Import\XlsxWorkbookReader;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Reader hardening (import audit): XLSX row filters, UTF-16 files, DOCTYPE
 * detection beyond the first 4 KB and corrupt sheet XML.
 */
class TabularFileReaderHardeningTest extends TestCase
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

    private function tempFile(string $contents, string $extension = 'csv'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hvac-hard-') . '.' . $extension;
        $this->tempFiles[] = $path;
        file_put_contents($path, $contents);

        return $path;
    }

    /** Minimal single-sheet workbook around a raw sheet XML string. */
    private function xlsxWithSheetXml(string $sheetXml): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hvac-hard-') . '.xlsx';
        $this->tempFiles[] = $path;

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Blad1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="x" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        return $path;
    }

    private function sheetXmlWithRows(int $count): string
    {
        $xml = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        for ($r = 1; $r <= $count; $r++) {
            $xml .= "<row r=\"{$r}\"><c r=\"A{$r}\" t=\"inlineStr\"><is><t>v{$r}</t></is></c></row>";
        }

        return $xml . '</sheetData></worksheet>';
    }

    public function test_xlsx_row_filter_sees_every_row_even_with_max_rows_one(): void
    {
        $path = $this->xlsxWithSheetXml($this->sheetXmlWithRows(5));

        $seen = [];
        $result = (new TabularFileReader())->rows($path, 'xlsx', null, 1, 64, null, function (array $cells, int $index) use (&$seen): bool {
            $seen[] = $index;

            return false; // pure counting pass, like the wizard's category count
        });

        $this->assertSame([0, 1, 2, 3, 4], $seen, 'the filter must run over all rows before the row limit applies');
        $this->assertSame([], $result['rows']);
        $this->assertFalse($result['truncated']);
    }

    public function test_xlsx_row_filter_limit_counts_kept_rows_and_preserves_source_indexes(): void
    {
        $path = $this->xlsxWithSheetXml($this->sheetXmlWithRows(6));

        // Keep the header (row 0) and only odd source rows; allow 2 kept rows.
        $result = (new TabularFileReader())->rows($path, 'xlsx', null, 2, 64, null, fn (array $cells, int $index) => $index === 0 || $index % 2 === 1);

        $this->assertSame([0, 1], array_keys($result['rows']));
        $this->assertSame('v2', $result['rows'][1][0]);
        $this->assertTrue($result['truncated']);
    }

    public function test_utf16le_csv_with_bom_is_decoded(): void
    {
        $utf16 = "\xFF\xFE" . mb_convert_encoding("sku;name\r\nA1;Café\r\n", 'UTF-16LE', 'UTF-8');
        $path = $this->tempFile($utf16);

        $reader = new TabularFileReader();
        $this->assertSame(';', $reader->detectDelimiter($path)['delimiter']);

        $rows = $reader->rows($path, 'csv')['rows'];
        $this->assertSame(['sku', 'name'], $rows[0]);
        $this->assertSame(['A1', 'Café'], $rows[1]);
    }

    public function test_utf16be_csv_with_bom_is_decoded(): void
    {
        $utf16 = "\xFE\xFF" . mb_convert_encoding("sku;name\r\nA1;Café\r\n", 'UTF-16BE', 'UTF-8');
        $path = $this->tempFile($utf16);

        $rows = (new TabularFileReader())->rows($path, 'csv')['rows'];
        $this->assertSame(['A1', 'Café'], $rows[1]);
    }

    public function test_doctype_after_5kb_of_whitespace_is_rejected(): void
    {
        $sheet = '<?xml version="1.0" encoding="UTF-8"?><!--' . str_repeat(' ', 5000) . '-->'
            . '<!DOCTYPE worksheet [<!ENTITY inl "INLINE-EXPANDED">]>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . '<row r="1"><c r="A1" t="inlineStr"><is><t>&inl;</t></is></c></row>'
            . '</sheetData></worksheet>';
        $path = $this->xlsxWithSheetXml($sheet);

        $this->expectException(XlsxReadException::class);
        $this->expectExceptionMessage('DOCTYPE');

        (new XlsxWorkbookReader($path))->rows('Blad1');
    }

    public function test_corrupt_sheet_xml_raises_a_readable_xlsx_exception(): void
    {
        $path = $this->xlsxWithSheetXml('this is not xml at all <<<');

        $this->expectException(XlsxReadException::class);

        (new XlsxWorkbookReader($path))->rows('Blad1');
    }

    public function test_truncated_sheet_xml_raises_a_readable_xlsx_exception(): void
    {
        $xml = $this->sheetXmlWithRows(3);
        $path = $this->xlsxWithSheetXml(substr($xml, 0, (int) (strlen($xml) * 0.6)));

        $this->expectException(XlsxReadException::class);

        (new XlsxWorkbookReader($path))->rows('Blad1');
    }
}
