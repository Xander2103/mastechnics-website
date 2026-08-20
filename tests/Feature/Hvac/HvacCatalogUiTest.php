<?php

namespace Tests\Feature\Hvac;

use App\Models\HvacBrand;
use App\Models\HvacImportCatalog;
use App\Models\HvacImportRun;
use App\Models\HvacProduct;
use App\Models\HvacSupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HvacCatalogUiTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com'];
    }

    private function seedCatalog(string $name, int $products, array $productOverrides = []): HvacImportCatalog
    {
        $supplier = HvacSupplier::firstOrCreate(['name' => 'TestSupplier BV'], ['is_active' => true]);
        $brand = HvacBrand::firstOrCreate(['slug' => 'testbrand'], ['name' => 'TestBrand', 'is_active' => true]);

        $catalog = HvacImportCatalog::create([
            'name' => $name, 'hvac_supplier_id' => $supplier->id,
            'source_type' => 'guided', 'imported_at' => now(), 'product_count' => $products,
        ]);

        for ($i = 1; $i <= $products; $i++) {
            $product = HvacProduct::create(array_merge([
                'hvac_supplier_id' => $supplier->id, 'hvac_brand_id' => $brand->id,
                'sku' => "{$name}-{$i}", 'model' => "M-{$i}", 'name' => "Product {$name} {$i}",
                'product_type' => 'installation_accessory', 'is_active' => true,
            ], $productOverrides));
            $catalog->products()->attach($product->id, ['source_row' => $i + 1, 'imported_at' => now()]);
        }

        return $catalog;
    }

    public function test_products_page_defaults_to_catalog_overview_when_lists_exist(): void
    {
        $this->seedCatalog('Fujilijst 2026', 2);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.products.index'));

        $response->assertOk()
            ->assertViewIs('admin.hvac.catalogs.index')
            ->assertSee('Fujilijst 2026')
            ->assertSee('Productlijsten')
            ->assertSee('Nieuwe lijst importeren');
    }

    public function test_products_page_shows_flat_table_when_no_lists_exist(): void
    {
        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.products.index'))
            ->assertOk()
            ->assertViewIs('admin.hvac.products.index');
    }

    public function test_alle_producten_tab_keeps_the_flat_table(): void
    {
        $this->seedCatalog('Lijst A', 1);
        $this->seedCatalog('Lijst B', 1);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.products.index', ['view' => 'all']));

        $response->assertOk()
            ->assertViewIs('admin.hvac.products.index')
            ->assertSee('Lijst A-1')
            ->assertSee('Lijst B-1');
    }

    public function test_existing_filter_deep_links_keep_the_flat_table(): void
    {
        $this->seedCatalog('Lijst A', 1);

        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.products.index', ['quality' => 'missing_price']))
            ->assertOk()
            ->assertViewIs('admin.hvac.products.index');
    }

    public function test_catalog_detail_shows_only_its_own_products(): void
    {
        $a = $this->seedCatalog('Lijst A', 2);
        $this->seedCatalog('Lijst B', 3);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.catalogs.show', $a));

        $response->assertOk()
            ->assertSee('Lijst A-1')
            ->assertSee('Lijst A-2')
            ->assertDontSee('Lijst B-1');
    }

    public function test_catalog_detail_shows_import_history(): void
    {
        $catalog = $this->seedCatalog('Lijst A', 1);
        HvacImportRun::create([
            'hvac_import_catalog_id' => $catalog->id,
            'created_count' => 123, 'warning_count' => 7,
        ]);

        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.catalogs.show', $catalog))
            ->assertOk()
            ->assertSee('Importgeschiedenis')
            ->assertSee('123 toegevoegd')
            ->assertSee('7 waarschuwingen');
    }

    public function test_catalog_detail_filters_by_type_and_needs_review(): void
    {
        $catalog = $this->seedCatalog('Lijst A', 1);
        $supplier = HvacSupplier::first();
        $brand = HvacBrand::first();
        $review = HvacProduct::create([
            'hvac_supplier_id' => $supplier->id, 'hvac_brand_id' => $brand->id,
            'sku' => 'REVIEW-1', 'model' => 'R-1', 'name' => 'Review product',
            'product_type' => 'indoor_unit', 'cooling_capacity_kw' => 2.5, 'is_active' => true,
            'metadata' => ['import' => ['needs_review' => true]],
        ]);
        $catalog->products()->attach($review->id);

        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.catalogs.show', [$catalog, 'needs_review' => 1]))
            ->assertOk()
            ->assertSee('REVIEW-1')
            ->assertDontSee('Lijst A-1');

        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.catalogs.show', [$catalog, 'type' => 'installation_accessory']))
            ->assertOk()
            ->assertSee('Lijst A-1')
            ->assertDontSee('REVIEW-1');
    }

    public function test_catalog_detail_paginates(): void
    {
        $catalog = $this->seedCatalog('Grote lijst', 30);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.catalogs.show', $catalog));

        $response->assertOk()->assertViewHas('products', fn ($p) => $p->count() === 25 && $p->total() === 30);
    }

    public function test_rename_and_archive_catalog(): void
    {
        $catalog = $this->seedCatalog('Oude naam', 1);

        $this->withSession($this->adminSession())
            ->patch(route('admin.hvac.catalogs.rename', $catalog), ['name' => 'Fujitsu Airstage 2026–2027'])
            ->assertRedirect();
        $this->assertSame('Fujitsu Airstage 2026–2027', $catalog->fresh()->name);

        $this->withSession($this->adminSession())
            ->patch(route('admin.hvac.catalogs.archive', $catalog))
            ->assertRedirect();
        $this->assertTrue($catalog->fresh()->isArchived());
        $this->assertTrue($catalog->products()->first()->is_active, 'archiving never deactivates products');

        // Archived list stays readable.
        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.catalogs.show', $catalog))
            ->assertOk()
            ->assertSee('Gearchiveerd');
    }

    public function test_archived_catalogs_move_to_their_own_filter_tab(): void
    {
        $this->seedCatalog('Actieve lijst 2026', 1);
        $archived = $this->seedCatalog('Oude lijst 2024', 1);
        $archived->update(['status' => HvacImportCatalog::STATUS_ARCHIVED]);

        // Default overview: only active lists, with a link to the archived tab.
        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.products.index', ['view' => 'lists']))
            ->assertOk()
            ->assertSee('Actieve lijst 2026')
            ->assertDontSee('Oude lijst 2024')
            ->assertSee('Gearchiveerd (1)');

        // Archived tab: only archived lists.
        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.products.index', ['view' => 'lists', 'lists' => 'archived']))
            ->assertOk()
            ->assertSee('Oude lijst 2024')
            ->assertDontSee('Actieve lijst 2026');

        // "Alle" shows both.
        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.products.index', ['view' => 'lists', 'lists' => 'all']))
            ->assertOk()
            ->assertSee('Oude lijst 2024')
            ->assertSee('Actieve lijst 2026');
    }

    public function test_product_edit_page_shows_source_block(): void
    {
        $catalog = $this->seedCatalog('Bronlijst 2026', 1);
        $product = $catalog->products()->first();
        $product->update(['metadata' => ['import' => [
            'file' => 'CatalogFR.csv', 'at' => now()->toIso8601String(),
            'price' => ['column' => 'BrutPrice', 'raw' => '403,5', 'meaning' => 'gross'],
            'needs_review' => true,
        ]]]);

        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.products.edit', $product))
            ->assertOk()
            ->assertSee('Bron')
            ->assertSee('Bronlijst 2026')
            ->assertSee('CatalogFR.csv')
            ->assertSee('brutoprijs');
    }

    public function test_catalog_detail_filters_by_capacity_and_missing_compatibility(): void
    {
        $catalog = $this->seedCatalog('Lijst A', 1); // accessory, no capacity
        $supplier = HvacSupplier::first();
        $brand = HvacBrand::first();
        $unit = HvacProduct::create([
            'hvac_supplier_id' => $supplier->id, 'hvac_brand_id' => $brand->id,
            'sku' => 'UNIT-35', 'model' => 'U-35', 'name' => 'Binnenunit 3,5',
            'product_type' => 'indoor_unit', 'cooling_capacity_kw' => 3.5, 'is_active' => true,
        ]);
        $catalog->products()->attach($unit->id);

        // Capacity range keeps only the 3.5 kW unit.
        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.catalogs.show', [$catalog, 'capacity_min' => 3, 'capacity_max' => 4]))
            ->assertOk()->assertSee('UNIT-35')->assertDontSee('Lijst A-1');

        // Missing-compatibility filter: the unit has no compat records.
        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.catalogs.show', [$catalog, 'missing_compat' => 1]))
            ->assertOk()->assertSee('UNIT-35')->assertDontSee('Lijst A-1');
    }

    public function test_template_import_links_products_to_a_template_catalog(): void
    {
        $csv = implode("\r\n", [
            'supplier;brand;sku;model;name;product_type',
            'TEST Leverancier;TESTMERK;TB-77;TB 77;TEST accessoire;wall_bracket',
        ]) . "\r\n";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('sjabloon-2026.csv', $csv);

        $preview = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.preview'), ['file' => $file, 'mode' => 'create_and_update']);
        $preview->assertOk();
        preg_match('/name="token" value="([A-Za-z0-9]{40})"/', $preview->getContent(), $m);

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.confirm'), ['token' => $m[1]])
            ->assertRedirect(route('admin.hvac.import.index'));

        $catalog = HvacImportCatalog::where('source_type', 'template')->firstOrFail();
        $this->assertSame('MAS-sjabloon — sjabloon-2026', $catalog->name);
        $this->assertSame(1, $catalog->products()->count());
        $this->assertSame(1, $catalog->runs()->count());
        $this->assertSame(1, $catalog->product_count);
    }

    public function test_source_block_shows_mapping_profile_name(): void
    {
        $profile = \App\Models\HvacMappingProfile::create([
            'name' => 'TestSupplier — automatisch', 'supplier_name' => 'TestSupplier BV',
            'header_row' => 1, 'column_map' => ['ProductID' => 'sku'], 'decimal_format' => 'auto',
            'is_active' => true,
        ]);
        $catalog = $this->seedCatalog('Bronlijst', 1);
        $product = $catalog->products()->first();
        $product->update(['metadata' => ['import' => ['file' => 'x.csv', 'profile_id' => $profile->id]]]);

        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.products.edit', $product))
            ->assertOk()
            ->assertSee('Leveranciersinstellingen')
            ->assertSee('TestSupplier — automatisch');
    }

    public function test_guests_are_blocked_everywhere(): void
    {
        $catalog = $this->seedCatalog('Lijst A', 1);

        $this->get(route('admin.hvac.products.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.hvac.catalogs.show', $catalog))->assertRedirect(route('admin.login'));
        $this->patch(route('admin.hvac.catalogs.rename', $catalog), ['name' => 'X'])->assertRedirect(route('admin.login'));
        $this->patch(route('admin.hvac.catalogs.archive', $catalog))->assertRedirect(route('admin.login'));
    }
}
