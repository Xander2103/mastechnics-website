<?php

namespace Tests\Feature\Hvac;

use App\Models\HvacProduct;
use App\Services\Hvac\HvacCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HvacImportProvenanceTest extends TestCase
{
    use RefreshDatabase;

    private function row(array $overrides = [], array $dataOverrides = []): array
    {
        return array_merge([
            'line'   => 2,
            'action' => 'create',
            'errors' => [],
            'data'   => array_merge([
                'supplier' => 'TestSupplier BV', 'brand' => 'TESTBRAND', 'sku' => 'TB-IN-25',
                'model' => 'TB-IN-25', 'name' => 'TB airco binnenunit 2,5 kW', 'product_type' => 'indoor_unit',
                'cooling_capacity_kw' => 2.5, 'heating_capacity_kw' => null, 'minimum_capacity_kw' => null,
                'maximum_capacity_kw' => null, 'purchase_price_excl_vat' => null, 'sale_price_excl_vat' => null,
                'stock_quantity' => null, 'lead_time_days' => null, 'breaker_a' => null,
                'max_pipe_length_m' => null, 'max_pipe_length_per_unit_m' => null, 'max_height_difference_m' => null,
                'max_connected_indoor_units' => null, 'sound_level_db' => null, 'seer' => null, 'scop' => null,
                'wifi_included' => null, 'active' => null, 'voltage' => null, 'phase' => null, 'cable' => null,
                'liquid_pipe_diameter' => null, 'gas_pipe_diameter' => null, 'notes' => null,
            ], $dataOverrides),
        ], $overrides);
    }

    public function test_import_context_is_stored_as_metadata_provenance(): void
    {
        $importer = new HvacCsvImporter();

        $importer->import([$this->row()], 'create_and_update', [
            'source_file' => 'CatalogFR.csv',
            'profile_id'  => 7,
            'provenance_by_line' => [
                2 => [
                    'fields' => ['supplier' => 'manual', 'model' => 'derived:sku'],
                    'price'  => ['column' => 'BrutPrice', 'raw' => '403,5', 'meaning' => 'gross'],
                    'ean'    => '5400237170001',
                    'source_row' => 2,
                    'needs_review' => true,
                ],
            ],
        ]);

        $product = HvacProduct::where('sku', 'TB-IN-25')->firstOrFail();
        $import = $product->metadata['import'];

        $this->assertSame('CatalogFR.csv', $import['file']);
        $this->assertSame(7, $import['profile_id']);
        $this->assertSame('gross', $import['price']['meaning']);
        $this->assertSame('5400237170001', $import['ean']);
        $this->assertSame(2, $import['source_row']);
        $this->assertTrue($import['needs_review']);
        $this->assertNotEmpty($import['at']);

        // Gross price meaning: catalog price columns stay empty.
        $this->assertNull($product->purchase_price_excl_vat);
        $this->assertNull($product->default_sale_price_excl_vat);
    }

    public function test_update_preserves_existing_notes_and_refreshes_import_block(): void
    {
        $importer = new HvacCsvImporter();

        $importer->import([$this->row(dataOverrides: ['notes' => 'eerste import'])], 'create_and_update', [
            'source_file' => 'oud.csv',
            'provenance_by_line' => [2 => ['needs_review' => true, 'source_row' => 2]],
        ]);

        $importer->import([$this->row()], 'create_and_update', [
            'source_file' => 'CatalogFR.csv',
            'provenance_by_line' => [2 => ['source_row' => 9, 'needs_review' => false]],
        ]);

        $product = HvacProduct::where('sku', 'TB-IN-25')->firstOrFail();

        $this->assertSame('eerste import', $product->metadata['notes'], 'notes from earlier import survive');
        $this->assertSame('CatalogFR.csv', $product->metadata['import']['file']);
        $this->assertSame(9, $product->metadata['import']['source_row']);
        $this->assertArrayNotHasKey('needs_review', $product->metadata['import']);
    }

    public function test_import_without_context_behaves_as_before(): void
    {
        (new HvacCsvImporter())->import([$this->row()], 'create_and_update');

        $product = HvacProduct::where('sku', 'TB-IN-25')->firstOrFail();
        $this->assertNull($product->metadata);
    }
}
