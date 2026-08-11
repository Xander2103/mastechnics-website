<?php

namespace Tests\Feature\Hvac;

use App\Models\HvacBrand;
use App\Models\HvacImportCatalog;
use App\Models\HvacImportRun;
use App\Models\HvacProduct;
use App\Models\HvacSupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HvacImportCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(string $sku = 'TB-1'): HvacProduct
    {
        $supplier = HvacSupplier::firstOrCreate(['name' => 'TestSupplier BV'], ['is_active' => true]);
        $brand = HvacBrand::firstOrCreate(['slug' => 'testbrand'], ['name' => 'TestBrand', 'is_active' => true]);

        return HvacProduct::create([
            'hvac_supplier_id' => $supplier->id, 'hvac_brand_id' => $brand->id,
            'sku' => $sku, 'model' => $sku, 'name' => "Product {$sku}",
            'product_type' => 'installation_accessory', 'is_active' => true,
        ]);
    }

    private function makeCatalog(string $name = 'TestSupplier 2026'): HvacImportCatalog
    {
        return HvacImportCatalog::create([
            'name' => $name,
            'hvac_supplier_id' => HvacSupplier::firstOrCreate(['name' => 'TestSupplier BV'], ['is_active' => true])->id,
            'source_filename' => 'CatalogFR.csv',
            'source_type' => 'guided',
            'imported_by' => 'admin@test.com',
            'imported_at' => now(),
            'status' => HvacImportCatalog::STATUS_ACTIVE,
        ]);
    }

    public function test_catalog_links_products_with_source_facts(): void
    {
        $catalog = $this->makeCatalog();
        $product = $this->makeProduct();

        $catalog->products()->attach($product->id, [
            'source_row' => 12, 'imported_at' => now(), 'source_price' => 403.50,
        ]);

        $linked = $catalog->products()->first();
        $this->assertTrue($product->is($linked));
        $this->assertSame(12, $linked->pivot->source_row);
        $this->assertEquals(403.50, (float) $linked->pivot->source_price);
    }

    public function test_a_product_can_belong_to_multiple_catalogs(): void
    {
        $old = $this->makeCatalog('TestSupplier 2025');
        $new = $this->makeCatalog('TestSupplier 2026');
        $product = $this->makeProduct();

        $old->products()->attach($product->id, ['source_row' => 5]);
        $new->products()->attach($product->id, ['source_row' => 9]);

        $this->assertCount(2, $product->importCatalogs);
        $this->assertSame(2, HvacImportCatalog::count());
    }

    public function test_deleting_a_catalog_never_deletes_products(): void
    {
        $catalog = $this->makeCatalog();
        $product = $this->makeProduct();
        $catalog->products()->attach($product->id);

        $catalog->delete(); // pivot rows cascade, products must survive

        $this->assertDatabaseHas('hvac_products', ['id' => $product->id]);
        $this->assertDatabaseMissing('hvac_import_catalog_product', ['hvac_product_id' => $product->id]);
    }

    public function test_runs_record_import_history_newest_first(): void
    {
        $catalog = $this->makeCatalog();
        HvacImportRun::create([
            'hvac_import_catalog_id' => $catalog->id,
            'created_count' => 123, 'warning_count' => 7,
            'imported_by' => 'admin@test.com', 'source_filename' => 'CatalogFR.csv',
            'created_at' => now()->subDay(),
        ]);
        HvacImportRun::create([
            'hvac_import_catalog_id' => $catalog->id,
            'created_count' => 4, 'updated_count' => 15,
        ]);

        $runs = $catalog->runs;
        $this->assertCount(2, $runs);
        $this->assertSame(4, $runs->first()->created_count);
        $this->assertSame(123, $runs->last()->created_count);
    }

    public function test_archiving_is_a_status_change_that_leaves_products_untouched(): void
    {
        $catalog = $this->makeCatalog();
        $product = $this->makeProduct();
        $catalog->products()->attach($product->id);

        $catalog->update(['status' => HvacImportCatalog::STATUS_ARCHIVED]);

        $this->assertTrue($catalog->fresh()->isArchived());
        $this->assertTrue($product->fresh()->is_active, 'archiving must not deactivate products');
        $this->assertCount(1, $catalog->fresh()->products);
    }
}
