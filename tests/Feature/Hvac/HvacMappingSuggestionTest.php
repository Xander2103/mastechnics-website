<?php

namespace Tests\Feature\Hvac;

use App\Services\Hvac\Import\ColumnMappingSuggester;
use App\Services\Hvac\Import\HeaderRowDetector;
use Tests\TestCase;

class HvacMappingSuggestionTest extends TestCase
{
    public function test_detects_header_row_below_title_and_empty_rows(): void
    {
        $rows = [
            ['Prijslijst 2026 — TestBrand'],
            [],
            [null, 'geldig vanaf', '2026-01-01'],
            ['Artikelnummer', 'Omschrijving', 'Koelvermogen kW', 'Netto prijs', 'Voorraad'],
            ['TB-25', 'TestBrand single split', '2.5', '780,00', '4'],
            ['TB-35', 'TestBrand single split', '3.5', '890,00', '2'],
        ];

        $result = (new HeaderRowDetector())->detect($rows);

        $this->assertSame(3, $result['row']);
        $this->assertSame('high', $result['confidence']);
    }

    public function test_header_on_first_row_is_detected(): void
    {
        $rows = [
            ['sku', 'name', 'brand', 'supplier'],
            ['TB-1', 'X', 'TestBrand', 'TEST Leverancier'],
        ];

        $result = (new HeaderRowDetector())->detect($rows);

        $this->assertSame(0, $result['row']);
    }

    public function test_suggests_exact_dutch_aliases(): void
    {
        $suggestions = (new ColumnMappingSuggester())->suggest([
            'Artikelnummer', 'Omschrijving', 'Koelvermogen kW', 'Netto prijs', 'Voorraad', 'Modelcode',
        ]);

        $this->assertSame('sku', $suggestions[0]['field']);
        $this->assertSame('exact', $suggestions[0]['confidence']);
        $this->assertSame('name', $suggestions[1]['field']);
        $this->assertSame('cooling_capacity_kw', $suggestions[2]['field']);
        $this->assertSame('purchase_price_excl_vat', $suggestions[3]['field']);
        $this->assertSame('stock_quantity', $suggestions[4]['field']);
        $this->assertSame('model', $suggestions[5]['field']);
    }

    public function test_internal_field_names_match_exactly(): void
    {
        $suggestions = (new ColumnMappingSuggester())->suggest(['cooling_capacity_kw', 'sku']);

        $this->assertSame('cooling_capacity_kw', $suggestions[0]['field']);
        $this->assertSame('exact', $suggestions[0]['confidence']);
    }

    public function test_unknown_headers_get_no_suggestion(): void
    {
        $suggestions = (new ColumnMappingSuggester())->suggest(['Interne kolom XYZ']);

        $this->assertNull($suggestions[0]['field']);
        $this->assertSame('none', $suggestions[0]['confidence']);
    }

    public function test_duplicate_targets_are_downgraded_to_ambiguous(): void
    {
        // Two columns that both look like a price → neither may be
        // auto-accepted silently.
        $suggestions = (new ColumnMappingSuggester())->suggest(['Netto prijs', 'nettoprijs']);

        $this->assertSame('purchase_price_excl_vat', $suggestions[0]['field']);
        $this->assertSame('purchase_price_excl_vat', $suggestions[1]['field']);
        $this->assertNotSame('exact', $suggestions[0]['confidence']);
        $this->assertNotSame('exact', $suggestions[1]['confidence']);
    }

    public function test_fuzzy_containment_is_marked_fuzzy(): void
    {
        $suggestions = (new ColumnMappingSuggester())->suggest(['Koelvermogen nominaal (kW)']);

        $this->assertSame('cooling_capacity_kw', $suggestions[0]['field']);
        $this->assertSame('fuzzy', $suggestions[0]['confidence']);
    }
}
