<?php

namespace Tests\Unit\Hvac;

use App\Services\Hvac\Import\TabularFileReader;
use PHPUnit\Framework\TestCase;
use Tests\Support\CatalogFrFixture;

/**
 * Regression: the real CatalogFR.csv (tab-delimited, cp1252) was parsed as
 * ONE giant column because delimiter detection only compared ';' vs ','.
 * Every one of its 21 named columns must be recognised separately.
 */
class CatalogFrStructureTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = CatalogFrFixture::toTempFile();
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    public function test_all_catalogfr_headers_are_recognised_as_separate_columns(): void
    {
        $result = (new TabularFileReader())->rows($this->path, 'csv');

        $headerCells = array_slice($result['rows'][0], 0, count(CatalogFrFixture::HEADERS));

        $this->assertSame(CatalogFrFixture::HEADERS, $headerCells);
        $this->assertSame("\t", $result['delimiter']);
        $this->assertSame('high', $result['delimiter_confidence']);
    }

    public function test_data_rows_are_split_and_encoding_is_rescued(): void
    {
        $result = (new TabularFileReader())->rows($this->path, 'csv');

        $row = $result['rows'][1];
        $this->assertSame('118970', $row[0], 'padded ProductID must be trimmed');
        $this->assertSame('TB clim murale intérieure 2,5 kW', $row[1]);
        $this->assertSame('403,5', $row[6], 'BrutPrice keeps its comma decimal at read time');
        $this->assertSame(CatalogFrFixture::GROUP_CLIMATE, $row[13]);
        $this->assertNull($row[14], 'empty RubricID becomes null');
    }

    public function test_fixture_contains_expected_group_distribution(): void
    {
        $result = (new TabularFileReader())->rows($this->path, 'csv');

        $groups = [];
        foreach ($result['rows'] as $index => $cells) {
            if ($index === 0) {
                continue;
            }
            $groups[$cells[13]] = ($groups[$cells[13]] ?? 0) + 1;
        }

        $this->assertSame(CatalogFrFixture::CLIMATE_COUNT, $groups[CatalogFrFixture::GROUP_CLIMATE]);
        $this->assertSame(CatalogFrFixture::TOTAL_COUNT, array_sum($groups));
    }
}
