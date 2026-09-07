<?php

namespace Tests\Feature\Hvac;

use App\Models\HvacBrand;
use App\Models\HvacImportCatalog;
use App\Models\HvacImportRun;
use App\Models\HvacMappingProfile;
use App\Models\HvacProduct;
use App\Models\HvacSupplier;
use App\Services\Hvac\HvacCompatibilityCsvImporter;
use App\Services\Hvac\HvacCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * Regression tests for the import-pipeline audit: guided wizard (XLSX
 * categories, deactivate-missing, profile scoping, confirm failures, stale
 * state), classic template import (quoted newlines, truncation, corrupt
 * XLSX, double submit), row validation bounds and supplier/brand identity.
 */
class HvacImportHardeningTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function csvUpload(string $contents, string $name = 'lijst.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    /** Semicolon CSV: Artikelnummer;Omschrijving;Merk;Netto prijs (+ optional other headers). */
    private function standardCsv(array $rows, array $headers = ['Artikelnummer', 'Omschrijving', 'Merk', 'Netto prijs']): string
    {
        $lines = [implode(';', $headers)];
        foreach ($rows as $row) {
            $lines[] = implode(';', $row);
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    /** Minimal real XLSX: sheet name => list of cell lists (inline strings). */
    private function xlsxUpload(array $sheets, string $name = 'prijslijst.xlsx'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'hvac-hard-') . '.xlsx';
        $this->tempFiles[] = $path;

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $workbook = '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $i = 0;
        foreach ($sheets as $sheetName => $rows) {
            $i++;
            $workbook .= '<sheet name="' . htmlspecialchars($sheetName, ENT_XML1) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
            $rels .= '<Relationship Id="rId' . $i . '" Type="x" Target="worksheets/sheet' . $i . '.xml"/>';
            $zip->addFromString('xl/worksheets/sheet' . $i . '.xml', $this->sheetXml($rows));
        }
        $zip->addFromString('xl/workbook.xml', $workbook . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels . '</Relationships>');
        $zip->close();

        return new UploadedFile($path, $name, null, null, true);
    }

    private function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $r => $cells) {
            $xml .= '<row r="' . ($r + 1) . '">';
            foreach ($cells as $c => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $xml .= '<c r="' . chr(65 + $c) . ($r + 1) . '" t="inlineStr"><is><t>' . htmlspecialchars((string) $value, ENT_XML1) . '</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml . '</sheetData></worksheet>';
    }

    /** XLSX whose sheet XML is arbitrary (used for corrupt files). */
    private function rawXlsxUpload(string $sheetXml, string $name = 'kapot.xlsx'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'hvac-hard-') . '.xlsx';
        $this->tempFiles[] = $path;

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="B" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        return new UploadedFile($path, $name, null, null, true);
    }

    private function startWizard(UploadedFile $file, array $extra = []): string
    {
        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.upload'), ['file' => $file] + $extra);

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertMatchesRegularExpression('/guided\/[A-Za-z0-9]{40}$/', $location, 'wizard did not start: ' . json_encode(session('errors')?->all()));

        return basename($location);
    }

    private function state(string $token): ?array
    {
        return Cache::get("hvac-guided:{$token}");
    }

    private function review(string $token, array $data = []): TestResponse
    {
        return $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.review', $token), $data + ['type_fallback' => 'wall_bracket']);
    }

    private function confirm(string $token, array $data = []): TestResponse
    {
        if (($this->state($token)['step'] ?? null) !== 'confirm') {
            $this->review($token)->assertSessionHasNoErrors();
        }

        return $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.confirm', $token), $data + [
                'mode' => 'create_and_update', 'catalog_choice' => 'new', 'catalog_name' => 'Lijst',
            ]);
    }

    // ── 1. XLSX + category column ────────────────────────────────────────────

    public function test_xlsx_with_a_category_column_reaches_the_categories_step_with_counts(): void
    {
        $rows = [['Artikelnummer', 'Omschrijving', 'Groep', 'Merk', 'Netto prijs']];
        for ($i = 1; $i <= 6; $i++) {
            $rows[] = ['SKU' . $i, 'Product ' . $i, $i % 2 ? 'Airco' : 'Sanitair', 'TESTMERK', '10'];
        }
        $token = $this->startWizard($this->xlsxUpload(['Blad1' => $rows]), ['supplier_name' => 'Sup']);

        $state = $this->state($token);
        $this->assertSame('categories', $state['step']);
        $this->assertSame(['Airco' => 3, 'Sanitair' => 3], $state['category']['values']);

        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.guided.step', $token))
            ->assertOk()
            ->assertViewIs('admin.hvac.imports.guided.categories')
            ->assertViewHas('total', 6);

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.categories', $token), ['categories' => ['Airco']])
            ->assertSessionHasNoErrors();

        $this->confirm($token)->assertRedirect(route('admin.hvac.import.guided.result', $token));
        $this->assertSame(3, HvacProduct::count());
        $this->assertSame(['SKU1', 'SKU3', 'SKU5'], HvacProduct::orderBy('sku')->pluck('sku')->all());
    }

    // ── 2 + 3. deactivate-missing must only touch products absent from the file ──

    public function test_create_only_reimport_with_deactivate_missing_keeps_products_in_the_file_active(): void
    {
        $csv = $this->standardCsv([['A1', 'Prod 1', 'M', '10'], ['A2', 'Prod 2', 'M', '20']]);
        $token = $this->startWizard($this->csvUpload($csv), ['supplier_name' => 'Sup']);
        $this->confirm($token)->assertRedirect(route('admin.hvac.import.guided.result', $token));
        $catalog = HvacImportCatalog::firstOrFail();
        $this->assertSame(2, HvacProduct::where('is_active', true)->count());

        $token2 = $this->startWizard($this->csvUpload($csv), ['supplier_name' => 'Sup']);
        $this->confirm($token2, [
            'mode' => 'create_only', 'catalog_choice' => 'existing', 'catalog_id' => $catalog->id,
            'catalog_name' => null, 'deactivate_missing' => 1,
        ])->assertRedirect(route('admin.hvac.import.guided.result', $token2));

        $result = $this->state($token2)['result'];
        $this->assertSame(0, $result['deactivated']);
        $this->assertSame(0, $result['missing']);
        $this->assertSame(2, HvacProduct::where('is_active', true)->count());
    }

    public function test_row_with_a_validation_error_is_still_present_in_the_file_and_never_deactivated(): void
    {
        $csv = $this->standardCsv([['A1', 'Prod 1', 'M', '10'], ['A2', 'Prod 2', 'M', '20'], ['A3', 'Prod 3', 'M', '30']]);
        $token = $this->startWizard($this->csvUpload($csv), ['supplier_name' => 'Sup']);
        $this->confirm($token);
        $catalog = HvacImportCatalog::firstOrFail();

        // A2 has a broken price (row error), A3 is genuinely gone.
        $csv2 = $this->standardCsv([['A1', 'Prod 1', 'M', '10'], ['A2', 'Prod 2', 'M', 'n.v.t.']]);
        $token2 = $this->startWizard($this->csvUpload($csv2), ['supplier_name' => 'Sup']);
        $this->review($token2)->assertSessionHasNoErrors();

        // Confirm screen: only A3 counts as "niet in dit bestand".
        $confirmPage = $this->withSession($this->adminSession())->get(route('admin.hvac.import.guided.step', $token2));
        $confirmPage->assertOk();
        $this->assertSame(1, $confirmPage->viewData('catalogs')->firstWhere('id', $catalog->id)->missing_count);

        $this->confirm($token2, [
            'catalog_choice' => 'existing', 'catalog_id' => $catalog->id, 'catalog_name' => null, 'deactivate_missing' => 1,
        ])->assertRedirect(route('admin.hvac.import.guided.result', $token2));

        $result = $this->state($token2)['result'];
        $this->assertSame(1, $result['deactivated']);
        $this->assertSame(1, $result['missing']);
        $this->assertTrue(HvacProduct::where('sku', 'A2')->firstOrFail()->is_active, 'a row that failed validation is still in the file');
        $this->assertFalse(HvacProduct::where('sku', 'A3')->firstOrFail()->is_active);
    }

    // ── 4. Profile recognition is supplier-scoped ────────────────────────────

    public function test_profile_of_another_supplier_is_only_suggested_not_applied(): void
    {
        HvacMappingProfile::create([
            'name' => 'Leverancier A — automatisch', 'supplier_name' => 'Leverancier A',
            'column_map' => ['Artikelnummer' => 'sku', 'Omschrijving' => 'name', 'Merk' => 'brand', 'Prijs' => 'price'],
            'source_headers' => ['Artikelnummer', 'Omschrijving', 'Merk', 'Prijs'],
            'price_semantics' => ['column_header' => 'Prijs', 'meaning' => 'net_purchase'],
            'is_active' => true, 'decimal_format' => 'auto',
        ]);

        $csv = $this->standardCsv([['B1', 'Product B', 'MERKB', '999']], ['Artikelnummer', 'Omschrijving', 'Merk', 'Prijs']);
        $token = $this->startWizard($this->csvUpload($csv, 'prijslijst-B.csv'), ['supplier_name' => 'Leverancier B']);

        $state = $this->state($token);
        $this->assertSame('Leverancier B', $state['supplier_name']);
        $this->assertNull($state['profile_id']);
        $this->assertNull($state['price_meaning'], 'price semantics of supplier A must not apply to supplier B');
        $this->assertStringContainsString('Leverancier A', (string) $state['profile_warning']);

        // The price question is still open → review blocks until answered.
        $this->review($token)->assertSessionHasErrors('review');

        $this->review($token, ['price_meaning' => 'unknown'])->assertSessionHasNoErrors();
        $this->confirm($token)->assertRedirect(route('admin.hvac.import.guided.result', $token));

        $product = HvacProduct::firstOrFail();
        $this->assertNull($product->purchase_price_excl_vat);
        $this->assertNull($product->default_sale_price_excl_vat);
        $this->assertSame('unknown', $product->metadata['import']['price']['meaning']);
    }

    public function test_profile_of_the_same_supplier_in_different_case_is_still_applied(): void
    {
        HvacMappingProfile::create([
            'name' => 'Leverancier A — automatisch', 'supplier_name' => 'Leverancier A',
            'column_map' => ['Artikelnummer' => 'sku', 'Omschrijving' => 'name', 'Merk' => 'brand', 'Prijs' => 'price'],
            'source_headers' => ['Artikelnummer', 'Omschrijving', 'Merk', 'Prijs'],
            'price_semantics' => ['column_header' => 'Prijs', 'meaning' => 'net_purchase'],
            'is_active' => true, 'decimal_format' => 'auto',
        ]);

        $csv = $this->standardCsv([['A1', 'Product A', 'MERKA', '10']], ['Artikelnummer', 'Omschrijving', 'Merk', 'Prijs']);
        $token = $this->startWizard($this->csvUpload($csv), ['supplier_name' => '  leverancier a ']);

        $state = $this->state($token);
        $this->assertNotNull($state['profile_id']);
        $this->assertSame('net_purchase', $state['price_meaning']);
    }

    // ── 5. Error report formula injection ────────────────────────────────────

    public function test_error_report_neutralises_formula_prefixes_in_every_cell(): void
    {
        $report = HvacCsvImporter::errorReport([[
            'line'   => 2,
            'errors' => ["Onbekend producttype 'abc;=HYPERLINK(\"http://evil\",\"klik\")'."],
            'data'   => ['sku' => '=1+1', 'model' => "M\r\n=2+2"],
        ], [
            'line'   => 3,
            'errors' => ["Kolom 'name' is verplicht."],
            'data'   => ['sku' => '-5', 'model' => '@cmd'],
        ]]);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $report);
        rewind($stream);

        $records = [];
        while (($cells = fgetcsv($stream, 0, ';', '"', '\\')) !== false) {
            $records[] = $cells;
            foreach ($cells as $cell) {
                $this->assertDoesNotMatchRegularExpression('/^[=+\-@\t\r]/', (string) $cell, 'cell may start a spreadsheet formula: ' . $cell);
            }
        }
        fclose($stream);

        $this->assertCount(3, $records, 'header + two rows; quoted newlines must not split records');
        $this->assertSame(['lijn', 'fouten', 'sku', 'model'], $records[0]);
        $this->assertSame("'=1+1", $records[1][2]);
        $this->assertStringContainsString('HYPERLINK', $records[1][1]);
    }

    // ── 6. Classic parsers honour quoted newlines ────────────────────────────

    public function test_classic_product_parser_keeps_a_quoted_newline_inside_one_row(): void
    {
        $csv = implode(';', HvacCsvImporter::COLUMNS) . "\n"
            . "Sup;Brand;SKU1;M1;\"Naam met\nnieuwe regel\";wall_bracket\n"
            . "Sup;Brand;SKU2;M2;Tweede;wall_bracket\n";

        $parsed = app(HvacCsvImporter::class)->parse($csv);

        $this->assertCount(2, $parsed['rows']);
        $this->assertSame("Naam met\nnieuwe regel", $parsed['rows'][0]['data']['name']);
        $this->assertSame([], $parsed['rows'][0]['errors']);
        $this->assertSame('SKU2', $parsed['rows'][1]['data']['sku']);
    }

    public function test_classic_compatibility_parser_keeps_a_quoted_newline_inside_one_row(): void
    {
        $csv = "parent_sku;compatible_sku;compatibility_type;notes\n"
            . "P1;C1;indoor_outdoor;\"regel 1\nregel 2\"\n";

        $parsed = app(HvacCompatibilityCsvImporter::class)->parse($csv);

        $this->assertCount(1, $parsed['rows']);
        $this->assertSame("regel 1\nregel 2", $parsed['rows'][0]['data']['notes']);
    }

    // ── 7. Classic XLSX truncation is reported ───────────────────────────────

    public function test_classic_xlsx_import_reports_the_row_limit_instead_of_silently_truncating(): void
    {
        config(['hvac.import.max_rows' => 1000]);

        $rows = [array_slice(HvacCsvImporter::COLUMNS, 0, 6)];
        for ($i = 2; $i <= 1010; $i++) {
            $rows[] = ['Sup', 'B', "S{$i}", 'm', 'n', 'wall_bracket'];
        }

        $response = $this->withSession($this->adminSession())->post(route('admin.hvac.import.preview'), [
            'file' => $this->xlsxUpload(['B' => $rows], 'groot.xlsx'), 'mode' => 'create_and_update',
        ]);

        $response->assertRedirect()->assertSessionHasErrors('file');
        $this->assertStringContainsString('1.000', session('errors')->first('file'));
        $this->assertSame(0, HvacProduct::count());
    }

    // ── 8. Corrupt XLSX on the classic import ────────────────────────────────

    public function test_classic_preview_with_corrupt_sheet_xml_redirects_with_an_error(): void
    {
        $response = $this->withSession($this->adminSession())->post(route('admin.hvac.import.preview'), [
            'file' => $this->rawXlsxUpload('not xml <<<'), 'mode' => 'create_and_update',
        ]);

        $response->assertStatus(302)->assertSessionHasErrors('file');
    }

    public function test_classic_compat_preview_with_corrupt_sheet_xml_redirects_with_an_error(): void
    {
        $response = $this->withSession($this->adminSession())->post(route('admin.hvac.import.compat.preview'), [
            'file' => $this->rawXlsxUpload('<worksheet><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>x'),
        ]);

        $response->assertStatus(302)->assertSessionHasErrors('compat_file');
    }

    // ── 9. Guided confirm: database failure is handled ───────────────────────

    public function test_guided_confirm_database_failure_rolls_back_and_shows_an_error(): void
    {
        $token = $this->startWizard($this->csvUpload($this->standardCsv([['A1', 'P', 'M', '10']])), ['supplier_name' => 'Sup']);
        $this->review($token)->assertSessionHasNoErrors();

        HvacImportRun::creating(function () {
            throw new \RuntimeException('simulated database failure');
        });

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.confirm', $token), [
                'mode' => 'create_and_update', 'catalog_choice' => 'new', 'catalog_name' => 'Lijst',
            ]);

        $response->assertStatus(302)->assertSessionHasErrors('confirm');
        $this->assertStringContainsString('niets opgeslagen', session('errors')->first('confirm'));

        $this->assertSame(0, HvacProduct::count());
        $this->assertSame(0, HvacImportCatalog::count());
        $this->assertSame(0, HvacImportRun::count());
        $this->assertSame('confirm', $this->state($token)['step'], 'the admin can retry from the confirm step');
        $this->assertTrue(Storage::disk('local')->exists("hvac-imports/{$token}.csv"));
    }

    // ── 10. Row validation bounds ────────────────────────────────────────────

    public function test_row_validation_rejects_values_that_overflow_the_database_columns(): void
    {
        $raw = [
            'supplier' => 'Sup', 'brand' => 'B',
            'sku' => str_repeat('S', 300), 'model' => str_repeat('M', 300), 'name' => str_repeat('N', 300),
            'product_type' => 'indoor_unit',
            'cooling_capacity_kw' => '123456.78',
            'purchase_price_excl_vat' => '1e12',
            'stock_quantity' => '99999999999999999999',
            'lead_time_days' => '70000',
            'max_connected_indoor_units' => '300',
            'breaker_a' => '99999',
            'seer' => '999', 'scop' => '999', 'sound_level_db' => '99999',
        ];

        $rows = app(HvacCsvImporter::class)->validateRows([['line' => 2, 'raw' => $raw]]);

        $this->assertGreaterThanOrEqual(6, count($rows[0]['errors']), json_encode($rows[0]['errors']));
        $errors = implode(' ', $rows[0]['errors']);
        foreach (['sku', 'cooling_capacity_kw', 'stock_quantity', 'lead_time_days', 'max_connected_indoor_units', 'purchase_price_excl_vat'] as $field) {
            $this->assertStringContainsString("'{$field}'", $errors);
        }
    }

    public function test_row_validation_still_accepts_realistic_values(): void
    {
        $raw = [
            'supplier' => 'Sup', 'brand' => 'B', 'sku' => 'S-100', 'model' => 'M', 'name' => 'N',
            'product_type' => 'indoor_unit', 'cooling_capacity_kw' => '3,5', 'purchase_price_excl_vat' => '1234,56',
            'stock_quantity' => '12', 'lead_time_days' => '14', 'max_connected_indoor_units' => '5',
            'breaker_a' => '16', 'seer' => '7.2', 'scop' => '4.1', 'sound_level_db' => '21',
        ];

        $rows = app(HvacCsvImporter::class)->validateRows([['line' => 2, 'raw' => $raw]]);

        $this->assertSame([], $rows[0]['errors']);
    }

    // ── 11. Supplier identity ────────────────────────────────────────────────

    public function test_wizard_reuses_an_existing_supplier_regardless_of_case(): void
    {
        $existing = HvacSupplier::create(['name' => 'Airco NV', 'is_active' => true]);

        $token = $this->startWizard($this->csvUpload($this->standardCsv([['A1', 'P', 'M', '10']])), ['supplier_name' => 'airco nv']);
        $this->confirm($token)->assertRedirect(route('admin.hvac.import.guided.result', $token));

        $this->assertSame(1, HvacSupplier::count());
        $this->assertSame($existing->id, HvacImportCatalog::firstOrFail()->hvac_supplier_id);
        $this->assertSame($existing->id, HvacProduct::firstOrFail()->hvac_supplier_id);
    }

    public function test_supplier_store_rejects_a_duplicate_name_case_insensitively(): void
    {
        $this->withSession($this->adminSession())->post(route('admin.hvac.suppliers.store'), ['name' => 'Airco NV'])
            ->assertSessionHasNoErrors();

        $this->withSession($this->adminSession())->post(route('admin.hvac.suppliers.store'), ['name' => '  airco nv '])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, HvacSupplier::count());
    }

    // ── 12. Back → change delimiter resets the derived state ─────────────────

    public function test_changing_the_delimiter_after_going_back_recomputes_header_and_mapping(): void
    {
        $token = $this->startWizard($this->csvUpload($this->standardCsv([['A1', 'Prod', 'M', '10']])), ['supplier_name' => 'Sup']);
        $before = $this->state($token);
        $this->assertSame(';', $before['delimiter']);
        $this->assertNotEmpty($before['column_map']);

        $this->withSession($this->adminSession())->post(route('admin.hvac.import.guided.back', $token), ['step' => 'delimiter']);
        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.delimiter', $token), ['delimiter' => 'comma'])
            ->assertRedirect();

        // A comma split of a semicolon file is one unknown column: the stale
        // header row and mapping are gone and the wizard asks again instead
        // of silently carrying the old mapping into the review step.
        $after = $this->state($token);
        $this->assertSame(',', $after['delimiter']);
        $this->assertNotSame($before['column_map'], $after['column_map'], 'mapping must be recomputed for the new delimiter');
        $this->assertNull($after['column_map']);
        $this->assertNull($after['header_row']);
        $this->assertNull($after['profile_id']);
        $this->assertSame('header', $after['step']);
    }

    // ── 13. Sheet names containing a comma ───────────────────────────────────

    public function test_sheet_name_containing_a_comma_can_be_selected(): void
    {
        $rows = [['Artikelnummer', 'Omschrijving', 'Merk'], ['A1', 'P', 'M']];
        $token = $this->startWizard($this->xlsxUpload(['Prijzen, 2026' => $rows, 'Info' => [['x']]]), ['supplier_name' => 'S']);
        $this->assertSame('sheet', $this->state($token)['step']);

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.sheet', $token), ['sheet' => 'Prijzen, 2026'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Prijzen, 2026', $this->state($token)['sheet']);

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.sheet', $token), ['sheet' => 'Bestaat niet'])
            ->assertSessionHasErrors('sheet');
    }

    // ── 14. Archived product lists cannot be targeted ────────────────────────

    public function test_confirm_cannot_target_an_archived_catalog(): void
    {
        $archived = HvacImportCatalog::create(['name' => 'Oud', 'status' => HvacImportCatalog::STATUS_ARCHIVED, 'source_type' => 'guided']);
        $token = $this->startWizard($this->csvUpload($this->standardCsv([['A1', 'P', 'M', '10']])), ['supplier_name' => 'Sup']);

        $this->confirm($token, ['catalog_choice' => 'existing', 'catalog_id' => $archived->id, 'catalog_name' => null])
            ->assertSessionHasErrors('catalog_id');

        $this->assertSame(HvacImportCatalog::STATUS_ARCHIVED, $archived->fresh()->status);
        $this->assertSame(0, HvacProduct::count());
    }

    // ── 15. Brands with an empty Latin slug ──────────────────────────────────

    public function test_non_latin_brand_names_do_not_collapse_into_one_brand(): void
    {
        $rows = [];
        foreach ([['日立', 'S1'], ['三菱', 'S2']] as [$brand, $sku]) {
            $rows[] = ['line' => 1, 'raw' => ['supplier' => 'Sup', 'brand' => $brand, 'sku' => $sku, 'model' => 'm', 'name' => 'n', 'product_type' => 'wall_bracket']];
        }
        $importer = app(HvacCsvImporter::class);
        $importer->import($importer->validateRows($rows), 'create_and_update');

        $this->assertSame(2, HvacBrand::count());
        $this->assertSame(['三菱', '日立'], HvacBrand::orderBy('name')->pluck('name')->all());
        foreach (HvacBrand::all() as $brand) {
            $this->assertNotSame('', $brand->slug);
        }

        // The brand form uses the same fallback and finds the existing brand again.
        $this->withSession($this->adminSession())->post(route('admin.hvac.brands.store'), ['name' => '日立']);
        $this->assertSame(2, HvacBrand::count());
    }

    // ── 16. Classic confirm double submit ────────────────────────────────────

    public function test_classic_confirm_second_submit_reports_already_imported_and_does_not_run_twice(): void
    {
        $preview = $this->withSession($this->adminSession())->post(route('admin.hvac.import.preview'), [
            'file' => $this->csvUpload(HvacCsvImporter::template(), 'sjabloon.csv'), 'mode' => 'create_and_update',
        ]);
        $preview->assertOk();
        $token = $preview->viewData('token');

        $this->withSession($this->adminSession())->post(route('admin.hvac.import.confirm'), ['token' => $token])
            ->assertSessionHas('success', 'hvac_import_done');
        $this->assertSame(2, HvacProduct::count());
        $this->assertSame(1, HvacImportRun::count());

        $second = $this->withSession($this->adminSession())->post(route('admin.hvac.import.confirm'), ['token' => $token]);
        $second->assertRedirect(route('admin.hvac.import.index'))->assertSessionHasErrors('file');
        $this->assertStringContainsString('al uitgevoerd', session('errors')->first('file'));
        $this->assertStringNotContainsString('verlopen', session('errors')->first('file'));
        $this->assertSame(1, HvacImportRun::count());
    }
}
