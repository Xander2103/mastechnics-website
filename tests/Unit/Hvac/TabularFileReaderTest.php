<?php

namespace Tests\Unit\Hvac;

use App\Services\Hvac\Import\TabularFileReader;
use PHPUnit\Framework\TestCase;

class TabularFileReaderTest extends TestCase
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

    private function tempCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hvac-reader-') . '.csv';
        $this->tempFiles[] = $path;
        file_put_contents($path, $contents);

        return $path;
    }

    public function test_tab_delimited_file_is_split_into_separate_columns(): void
    {
        $path = $this->tempCsv("ProductID\tLabelNL\tBrutPrice\n123\tKelderpomp\t403,5\n");

        $result = (new TabularFileReader())->rows($path, 'csv');

        $this->assertSame(['ProductID', 'LabelNL', 'BrutPrice'], $result['rows'][0]);
        $this->assertSame(['123', 'Kelderpomp', '403,5'], $result['rows'][1]);
        $this->assertSame("\t", $result['delimiter']);
    }

    public function test_utf8_bom_is_stripped_from_first_header_cell(): void
    {
        $path = $this->tempCsv("\xEF\xBB\xBFsku;name\nA-1;Product\n");

        $result = (new TabularFileReader())->rows($path, 'csv');

        $this->assertSame('sku', $result['rows'][0][0]);
    }

    public function test_windows_1252_content_is_converted_to_utf8(): void
    {
        // "éponges" with é as 0xE9 (cp1252), as in the real CatalogFR.csv.
        $path = $this->tempCsv("name;group\nPortes-\xE9ponges;Accessoires\n");

        $result = (new TabularFileReader())->rows($path, 'csv');

        $this->assertSame('Portes-éponges', $result['rows'][1][0]);
    }

    public function test_quoted_value_containing_newline_stays_one_row(): void
    {
        $path = $this->tempCsv("sku;note\nA-1;\"regel een\nregel twee\"\nB-2;ok\n");

        $result = (new TabularFileReader())->rows($path, 'csv');

        $this->assertSame("regel een\nregel twee", $result['rows'][1][1]);
        $this->assertSame('B-2', $result['rows'][2][0]);
    }

    public function test_explicit_delimiter_overrides_detection(): void
    {
        // One comma per line would auto-detect ',' — the caller forces ';'.
        $path = $this->tempCsv("a;b,c\nd;e,f\n");

        $result = (new TabularFileReader())->rows($path, 'csv', null, 100, 64, ';');

        $this->assertSame(['a', 'b,c'], $result['rows'][0]);
        $this->assertSame(';', $result['delimiter']);
    }

    public function test_row_filter_keeps_only_matching_rows_and_preserves_source_indexes(): void
    {
        $path = $this->tempCsv("sku;group\nA;keep\nB;drop\nC;keep\n");

        $result = (new TabularFileReader())->rows(
            $path, 'csv', null, 100, 64, null,
            fn (array $cells, int $index) => $index === 0 || ($cells[1] ?? '') === 'keep'
        );

        $this->assertArrayHasKey(0, $result['rows']);
        $this->assertArrayHasKey(1, $result['rows']);
        $this->assertArrayNotHasKey(2, $result['rows']);
        $this->assertSame('C', $result['rows'][3][0]);
    }

    public function test_max_rows_counts_kept_rows_and_reports_truncation(): void
    {
        $lines = ["sku;name"];
        for ($i = 1; $i <= 30; $i++) {
            $lines[] = "S{$i};Product {$i}";
        }
        $path = $this->tempCsv(implode("\n", $lines) . "\n");

        $result = (new TabularFileReader())->rows($path, 'csv', null, 10);

        $this->assertCount(10, $result['rows']);
        $this->assertTrue($result['truncated']);
    }
}
