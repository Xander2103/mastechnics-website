<?php

namespace Tests\Feature\Hvac;

use App\Models\HvacProduct;
use App\Models\HvacSupplier;
use App\Services\Hvac\HvacCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class HvacCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com'];
    }

    private function csv(array $rows): string
    {
        $header = 'supplier;brand;sku;model;name;product_type;cooling_capacity_kw;purchase_price_excl_vat;sale_price_excl_vat;stock_quantity;max_pipe_length_m;max_height_difference_m;wifi_included';

        return implode("\r\n", array_merge([$header], $rows)) . "\r\n";
    }

    private function validRow(string $sku = 'TB-SET-35'): string
    {
        return "Test Leverancier;TestBrand;{$sku};TestBrand 35 Set;TestBrand single split 3.5;single_split_set;3,5;900.00;1450.00;3;20;10;ja";
    }

    public function test_parser_reads_valid_rows_with_comma_decimals(): void
    {
        $parsed = (new HvacCsvImporter())->parse($this->csv([$this->validRow()]));

        $this->assertCount(1, $parsed['rows']);
        $row = $parsed['rows'][0];
        $this->assertSame([], $row['errors']);
        $this->assertSame('create', $row['action']);
        $this->assertSame(3.5, $row['data']['cooling_capacity_kw']);
        $this->assertTrue($row['data']['wifi_included']);
    }

    public function test_missing_required_columns_fails_globally(): void
    {
        $parsed = (new HvacCsvImporter())->parse("foo;bar\n1;2\n");

        $this->assertSame([], $parsed['rows']);
        $this->assertNotEmpty($parsed['global_errors']);
    }

    public function test_unknown_product_type_is_a_row_error(): void
    {
        $parsed = (new HvacCsvImporter())->parse($this->csv([
            'Test Leverancier;TestBrand;X-1;Model;Naam;straaljager;2.5;;;;;;',
        ]));

        $this->assertNotEmpty($parsed['rows'][0]['errors']);
    }

    public function test_formula_injection_is_rejected(): void
    {
        $parsed = (new HvacCsvImporter())->parse($this->csv([
            'Test Leverancier;TestBrand;X-1;=cmd|calc;Naam;indoor_unit;2.5;;;;;;',
        ]));

        $errors = implode(' ', $parsed['rows'][0]['errors']);
        $this->assertStringContainsString('formule', $errors);
    }

    public function test_import_creates_products_and_reuses_supplier_and_brand(): void
    {
        $importer = new HvacCsvImporter();
        $parsed = $importer->parse($this->csv([$this->validRow(), $this->validRow('TB-SET-50')]));

        $result = $importer->import($parsed['rows'], 'create_and_update');

        $this->assertSame(2, $result['created']);
        $this->assertDatabaseCount('hvac_products', 2);
        $this->assertDatabaseCount('hvac_suppliers', 1);
        $this->assertDatabaseCount('hvac_brands', 1);
    }

    public function test_import_updates_existing_by_supplier_and_sku(): void
    {
        $importer = new HvacCsvImporter();
        $importer->import($importer->parse($this->csv([$this->validRow()]))['rows'], 'create_and_update');
        $this->assertEquals(1450.0, HvacProduct::first()->default_sale_price_excl_vat);

        $updated = 'Test Leverancier;TestBrand;TB-SET-35;TestBrand 35 Set;TestBrand single split 3.5;single_split_set;3,5;950.00;1550.00;5;20;10;ja';
        $result = $importer->import($importer->parse($this->csv([$updated]))['rows'], 'create_and_update');

        $this->assertSame(1, $result['updated']);
        $this->assertDatabaseCount('hvac_products', 1);
        $this->assertEquals(1550.0, HvacProduct::first()->default_sale_price_excl_vat);
    }

    public function test_create_only_mode_skips_existing_products(): void
    {
        $importer = new HvacCsvImporter();
        $importer->import($importer->parse($this->csv([$this->validRow()]))['rows'], 'create_and_update');

        $result = $importer->import($importer->parse($this->csv([$this->validRow()]))['rows'], 'create_only');

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['skipped']);
    }

    public function test_error_rows_are_never_imported(): void
    {
        $importer = new HvacCsvImporter();
        $parsed = $importer->parse($this->csv([
            $this->validRow(),
            'Test Leverancier;TestBrand;X-1;Model;Naam;straaljager;2.5;;;;;;',
        ]));

        $result = $importer->import($parsed['rows'], 'create_and_update');

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseCount('hvac_products', 1);
    }

    // ── HTTP flow ─────────────────────────────────────────────────────────────

    public function test_import_flow_requires_admin(): void
    {
        $this->get(route('admin.hvac.import.index'))->assertRedirect(route('admin.login'));
    }

    public function test_preview_and_confirm_flow(): void
    {
        $file = UploadedFile::fake()->createWithContent('producten.csv', $this->csv([$this->validRow()]));

        $preview = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.preview'), ['file' => $file, 'mode' => 'create_and_update']);

        $preview->assertOk();
        $preview->assertSee('1');
        $preview->assertSee('TB-SET-35');
        $this->assertDatabaseCount('hvac_products', 0); // nothing written before confirm

        $token = (string) $preview->viewData('token');

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.confirm'), ['token' => $token])
            ->assertRedirect(route('admin.hvac.import.index'));

        $this->assertDatabaseCount('hvac_products', 1);
        $this->assertSame('TB-SET-35', HvacProduct::first()->sku);
    }

    public function test_expired_token_is_rejected_gracefully(): void
    {
        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.confirm'), ['token' => str_repeat('x', 40)])
            ->assertRedirect(route('admin.hvac.import.index'))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('hvac_products', 0);
    }

    public function test_template_is_downloadable(): void
    {
        $response = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.template'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('supplier;brand;sku', $response->getContent());
    }
}
