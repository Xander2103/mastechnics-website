<?php

namespace Tests\Unit\Hvac;

use App\Services\Hvac\Import\CategoryDetector;
use App\Services\Hvac\Import\TabularFileReader;
use PHPUnit\Framework\TestCase;
use Tests\Support\CatalogFrFixture;

class CategoryDetectorTest extends TestCase
{
    private CategoryDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new CategoryDetector();
    }

    /** @return array{0: array<int, string|null>, 1: array<int, array<int, string|null>>} */
    private function fixtureHeaderAndRows(): array
    {
        $path = CatalogFrFixture::toTempFile();
        $rows = (new TabularFileReader())->rows($path, 'csv')['rows'];
        @unlink($path);

        $header = $rows[0];
        unset($rows[0]);

        return [$header, $rows];
    }

    public function test_detects_groupname_as_category_column_in_catalogfr(): void
    {
        [$header, $rows] = $this->fixtureHeaderAndRows();

        $result = $this->detector->detect($header, $rows);

        $this->assertSame(13, $result['column']);
        $this->assertSame('GroupName', $header[$result['column']]);
    }

    public function test_counts_values_for_detected_column(): void
    {
        [$header, $rows] = $this->fixtureHeaderAndRows();

        $result = $this->detector->detect($header, $rows);

        $this->assertSame(CatalogFrFixture::CLIMATE_COUNT, $result['values'][CatalogFrFixture::GROUP_CLIMATE]);
        $this->assertSame(CatalogFrFixture::TOTAL_COUNT, array_sum($result['values']));
    }

    public function test_family_and_rubric_columns_are_offered_as_alternatives(): void
    {
        [$header, $rows] = $this->fixtureHeaderAndRows();

        $result = $this->detector->detect($header, $rows);

        $alternativeNames = array_map(fn (int $col) => $header[$col], $result['alternatives']);
        $this->assertContains('FamilyName', $alternativeNames);
    }

    public function test_no_category_column_in_a_file_of_unique_names(): void
    {
        $header = ['sku', 'name', 'price'];
        $rows = [];
        for ($i = 1; $i <= 20; $i++) {
            $rows[$i] = ["S{$i}", "Uniek product {$i}", (string) ($i * 10)];
        }

        $result = $this->detector->detect($header, $rows);

        $this->assertNull($result['column']);
    }

    public function test_numeric_and_constant_columns_are_never_categories(): void
    {
        // FamilyID (numeric '7200') and DeliveryCode (constant '1A') must not win.
        [$header, $rows] = $this->fixtureHeaderAndRows();

        $result = $this->detector->detect($header, $rows);

        $this->assertNotSame(10, $result['column'], 'FamilyID is numeric');
        $this->assertNotSame(7, $result['column'], 'DeliveryCode is constant');
        $this->assertNotContains(10, $result['alternatives']);
    }

    public function test_hvac_likely_recognises_climate_categories_only(): void
    {
        $this->assertTrue($this->detector->hvacLikely('Climatiseurs'));
        $this->assertTrue($this->detector->hvacLikely('Accessoires et pièces détachées pour climatiseurs'));
        $this->assertTrue($this->detector->hvacLikely('Pompes à chaleur et chaudières'));
        $this->assertTrue($this->detector->hvacLikely('Airco toebehoren'));
        $this->assertTrue($this->detector->hvacLikely('Ventilation'));

        $this->assertFalse($this->detector->hvacLikely('Pompes vide-cave'));
        $this->assertFalse($this->detector->hvacLikely('Barres de douche'));
        $this->assertFalse($this->detector->hvacLikely('Meubles'));
        $this->assertFalse($this->detector->hvacLikely('Robinetterie'));
    }
}
