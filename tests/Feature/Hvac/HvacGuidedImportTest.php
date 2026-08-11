<?php

namespace Tests\Feature\Hvac;

use App\Models\HvacMappingProfile;
use App\Models\HvacProduct;
use App\Models\HvacSupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Wizard v2 tests with supplier-style XLSX files (title rows, Dutch headers):
 * automatic header/mapping detection, business-question validation, explicit
 * confirmation, profile memory and safety guards.
 */
class HvacGuidedImportTest extends TestCase
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

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com'];
    }

    /**
     * Supplier-style workbook: a title row, an empty row, Dutch headers on
     * row 3, data below.
     */
    private function supplierXlsx(array $overrides = []): UploadedFile
    {
        $rows = $overrides['rows'] ?? [
            ['Prijslijst TestBrand 2026'],
            [],
            ['Omschrijving', 'Artikelnummer', 'Netto prijs', 'Koelvermogen kW', 'Modelcode'],
            ['TEST single split 2.5', 'TB-25', '780,00', '2.5', 'TB 25'],
            ['TEST single split 3.5', 'TB-35', '890,00', '3.5', 'TB 35'],
        ];

        $path = tempnam(sys_get_temp_dir(), 'hvac-guided-') . '.xlsx';
        $this->tempFiles[] = $path;

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Prijslijst" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>');

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $r => $cells) {
            $rowNum = $r + 1;
            $sheet .= "<row r=\"{$rowNum}\">";
            foreach ($cells as $c => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $letters = '';
                $n = $c;
                do {
                    $letters = chr(65 + ($n % 26)) . $letters;
                    $n = intdiv($n, 26) - 1;
                } while ($n >= 0);
                $sheet .= "<c r=\"{$letters}{$rowNum}\" t=\"inlineStr\"><is><t>"
                    . htmlspecialchars((string) $value, ENT_XML1) . '</t></is></c>';
            }
            $sheet .= '</row>';
        }
        $sheet .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return new UploadedFile($path, 'prijslijst-testbrand.xlsx', null, null, true);
    }

    private function startWizard(array $extra = [], ?UploadedFile $file = null): string
    {
        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.upload'), array_merge([
                'file' => $file ?? $this->supplierXlsx(),
            ], $extra));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertMatchesRegularExpression('/guided\/[A-Za-z0-9]{40}$/', $location);

        return basename($location);
    }

    private function stepUrl(string $token): string
    {
        return route('admin.hvac.import.guided.step', $token);
    }

    public function test_title_rows_and_dutch_headers_are_handled_automatically(): void
    {
        $token = $this->startWizard(['supplier_name' => 'TEST Leverancier']);

        // Header on row 3 was detected confidently, Dutch headers were mapped
        // by alias — the wizard lands directly on the review step.
        $response = $this->withSession($this->adminSession())->get($this->stepUrl($token));

        $response->assertOk()
            ->assertViewIs('admin.hvac.imports.guided.review')
            ->assertSee('Gegevens controleren')
            ->assertSee('Artikelnummer')
            ->assertViewHas('totalRows', 2);
    }

    public function test_review_asks_for_missing_brand_and_blocks_until_answered(): void
    {
        $token = $this->startWizard(['supplier_name' => 'TEST Leverancier']);

        // The 5-column price list has no brand column → one open question.
        $this->withSession($this->adminSession())->get($this->stepUrl($token))
            ->assertSee('Merk van deze producten');

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.review', $token), [])
            ->assertSessionHasErrors('review');

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.review', $token), ['brand_name' => 'TESTMERK'])
            ->assertRedirect($this->stepUrl($token))
            ->assertSessionHasNoErrors();
    }

    public function test_duplicate_field_mapping_is_rejected(): void
    {
        $token = $this->startWizard(['supplier_name' => 'TEST Leverancier']);

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.review', $token), [
                'brand_name' => 'TESTMERK',
                'mapping'    => [0 => 'sku', 1 => 'sku'],
            ]);

        $response->assertSessionHasErrors('mapping');
        $this->assertStringContainsString('één kolom', session('errors')->first('mapping'));
    }

    public function test_full_import_flow_with_explicit_confirmation(): void
    {
        $token = $this->startWizard(['supplier_name' => 'TEST Leverancier']);

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.review', $token), ['brand_name' => 'TESTMERK']);

        // Confirm screen shows counts; still nothing written.
        $this->withSession($this->adminSession())->get($this->stepUrl($token))
            ->assertOk()
            ->assertSee('Klaar om te importeren')
            ->assertViewHas('createCount', 2);
        $this->assertSame(0, HvacProduct::count());

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.confirm', $token), [
                'mode'           => 'create_and_update',
                'catalog_choice' => 'new',
                'catalog_name'   => 'TestBrand prijslijst 2026',
            ])
            ->assertRedirect(route('admin.hvac.import.guided.result', $token));

        $this->assertSame(2, HvacProduct::count());
        $product = HvacProduct::where('sku', 'TB-25')->first();
        $this->assertSame('TEST single split 2.5', $product->name);
        $this->assertEquals(2.5, (float) $product->cooling_capacity_kw);
        $this->assertEquals(780.0, (float) $product->purchase_price_excl_vat);
        $this->assertSame('single_split_set', $product->product_type, 'type inferred from "single split" in the name');
        $this->assertSame('TEST Leverancier', HvacSupplier::first()->name);

        // The temp file is gone after confirmation.
        $this->assertSame([], Storage::disk('local')->files('hvac-imports'));
    }

    public function test_profile_saved_from_result_is_applied_on_next_upload(): void
    {
        $token = $this->startWizard(['supplier_name' => 'TEST Leverancier']);
        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.review', $token), ['brand_name' => 'TESTMERK']);
        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.confirm', $token), [
                'mode' => 'create_and_update', 'catalog_choice' => 'new', 'catalog_name' => 'Lijst 1',
            ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.profile', $token))
            ->assertRedirect(route('admin.hvac.import.index'));

        $profile = HvacMappingProfile::firstOrFail();
        $this->assertSame('TEST Leverancier — automatisch', $profile->name);
        $this->assertSame('sku', $profile->column_map['Artikelnummer']);
        $this->assertNotEmpty($profile->source_headers);

        // Next upload of the same layout: recognized automatically.
        $token2 = $this->startWizard();
        $this->withSession($this->adminSession())->get($this->stepUrl($token2))
            ->assertOk()
            ->assertViewIs('admin.hvac.imports.guided.review')
            ->assertSee('We herkennen dit bestand');
    }

    public function test_profile_for_a_different_layout_is_not_applied(): void
    {
        HvacMappingProfile::create([
            'name' => 'Ander formaat', 'supplier_name' => 'Andere leverancier',
            'header_row' => 1, 'column_map' => ['Bestaat niet' => 'sku'],
            'decimal_format' => 'auto', 'source_headers' => ['Bestaat niet', 'Ook weg'],
            'is_active' => true,
        ]);

        $token = $this->startWizard(['supplier_name' => 'TEST Leverancier']);

        // No recognition banner, wizard proceeds with its own analysis.
        $this->withSession($this->adminSession())->get($this->stepUrl($token))
            ->assertOk()
            ->assertDontSee('We herkennen dit bestand');
        $this->assertSame(0, HvacProduct::count());
    }

    public function test_cancel_deletes_the_uploaded_file(): void
    {
        $token = $this->startWizard();
        $this->assertNotSame([], Storage::disk('local')->files('hvac-imports'));

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.cancel', $token))
            ->assertRedirect(route('admin.hvac.import.index'));

        $this->assertSame([], Storage::disk('local')->files('hvac-imports'));
    }

    public function test_expired_token_is_rejected_gracefully(): void
    {
        $token = str_repeat('a', 40);

        $this->withSession($this->adminSession())
            ->get($this->stepUrl($token))
            ->assertRedirect(route('admin.hvac.import.index'))
            ->assertSessionHasErrors('guided_file');
    }

    public function test_wizard_requires_admin(): void
    {
        $this->post(route('admin.hvac.import.guided.upload'), [
            'file' => $this->supplierXlsx(),
        ])->assertRedirect(route('admin.login'));

        $this->assertSame([], Storage::disk('local')->files('hvac-imports'));
    }

    public function test_classic_template_import_accepts_xlsx(): void
    {
        $file = $this->supplierXlsx(['rows' => [
            ['supplier', 'brand', 'sku', 'model', 'name', 'product_type'],
            ['TEST Leverancier', 'TESTMERK', 'TB-99', 'TB 99', 'TEST accessoire', 'wall_bracket'],
        ]]);

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.preview'), [
                'file' => $file,
                'mode' => 'create_and_update',
            ]);

        $response->assertOk();
        $response->assertSee('TB-99');
    }
}
