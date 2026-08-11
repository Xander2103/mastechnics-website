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
 * End-to-end tests for the guided mapping import wizard: arbitrary supplier
 * layouts (title rows, shuffled columns, Dutch headers), explicit
 * confirmation, reusable profiles and profile-mismatch safety.
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
     * row 3, shuffled column order.
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

    private function mappingPayload(): array
    {
        return [
            // Column order in the fixture: Omschrijving, Artikelnummer,
            // Netto prijs, Koelvermogen kW, Modelcode.
            'mapping' => [
                0 => 'name',
                1 => 'sku',
                2 => 'purchase_price_excl_vat',
                3 => 'cooling_capacity_kw',
                4 => 'model',
            ],
            'decimal_format' => 'auto',
        ];
    }

    private function startWizard(array $extra = []): string
    {
        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.upload'), array_merge([
                'file' => $this->supplierXlsx(),
                'mode' => 'create_and_update',
            ], $extra));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertMatchesRegularExpression('/guided\/[A-Za-z0-9]{40}$/', (string) $location);

        return basename((string) $location);
    }

    /**
     * The supplier's product_type is not in the file — the wizard requires
     * mapping every REQUIRED field, so this fixture maps supplier/brand/
     * product_type via... it cannot. Instead the required-field guard is the
     * subject of its own test; the happy path uses a file that carries them.
     */
    private function fullSupplierXlsx(): UploadedFile
    {
        return $this->supplierXlsx(['rows' => [
            ['Prijslijst TestBrand 2026'],
            [],
            ['Leverancier', 'Merk', 'Omschrijving', 'Artikelnummer', 'Netto prijs', 'Koelvermogen kW', 'Modelcode', 'Producttype'],
            ['TEST Leverancier', 'TESTMERK', 'TEST single split 2.5', 'TB-25', '780,00', '2.5', 'TB 25', 'single_split_set'],
            ['TEST Leverancier', 'TESTMERK', 'TEST single split 3.5', 'TB-35', '890,00', '3.5', 'TB 35', 'single_split_set'],
        ]]);
    }

    private function fullMappingPayload(): array
    {
        return [
            'mapping' => [
                0 => 'supplier',
                1 => 'brand',
                2 => 'name',
                3 => 'sku',
                4 => 'purchase_price_excl_vat',
                5 => 'cooling_capacity_kw',
                6 => 'model',
                7 => 'product_type',
            ],
            'decimal_format' => 'auto',
        ];
    }

    public function test_wizard_detects_header_row_and_imports_after_confirmation(): void
    {
        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.upload'), [
                'file' => $this->fullSupplierXlsx(),
                'mode' => 'create_and_update',
            ]);
        $response->assertRedirect();
        $token = basename((string) $response->headers->get('Location'));

        // Single visible sheet → wizard starts at the header step, with row 3
        // detected as the header.
        $step = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.guided.step', $token));
        $step->assertOk();
        $step->assertSee('Waar staan de kolomkoppen?');
        $step->assertSee('Rij <strong>3</strong>', false);

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.header', $token), ['header_row' => 3])
            ->assertRedirect(route('admin.hvac.import.guided.step', $token));

        // Mapping step suggests our fields for the Dutch headers.
        $mapping = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.guided.step', $token));
        $mapping->assertOk();
        $mapping->assertSee('Koppel de kolommen');
        $mapping->assertSee('Artikelnummer');

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.mapping', $token), $this->fullMappingPayload())
            ->assertRedirect(route('admin.hvac.import.guided.step', $token));

        // Preview shows normalized rows; nothing is written yet.
        $preview = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.guided.step', $token));
        $preview->assertOk();
        $preview->assertSee('Controleer en bevestig');
        $preview->assertSee('TB-25');
        $this->assertSame(0, HvacProduct::count());

        // Explicit confirmation performs the import.
        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.confirm', $token))
            ->assertRedirect(route('admin.hvac.import.index'));

        $this->assertSame(2, HvacProduct::count());
        $product = HvacProduct::where('sku', 'TB-25')->first();
        $this->assertNotNull($product);
        $this->assertSame('TEST single split 2.5', $product->name);
        $this->assertEquals(2.5, (float) $product->cooling_capacity_kw);
        $this->assertEquals(780.0, (float) $product->purchase_price_excl_vat);
        $this->assertSame('TEST Leverancier', HvacSupplier::first()->name);

        // The temp file is gone after confirmation.
        $this->assertSame([], Storage::disk('local')->files('hvac-imports'));
    }

    public function test_missing_required_mapping_is_rejected(): void
    {
        $token = $this->startWizard();

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.header', $token), ['header_row' => 3]);

        // The 5-column fixture cannot map supplier/brand/product_type.
        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.mapping', $token), $this->mappingPayload());

        $response->assertSessionHasErrors('mapping');
        $this->assertStringContainsString('supplier', session('errors')->first('mapping'));
    }

    public function test_duplicate_field_mapping_is_rejected(): void
    {
        $token = $this->startWizard();

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.header', $token), ['header_row' => 3]);

        $payload = $this->mappingPayload();
        $payload['mapping'][2] = 'sku'; // two columns → sku

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.mapping', $token), $payload);

        $response->assertSessionHasErrors('mapping');
        $this->assertStringContainsString('één kolom', session('errors')->first('mapping'));
    }

    public function test_mapping_can_be_saved_as_profile_and_reused(): void
    {
        // First import: map manually and save the profile.
        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.upload'), [
                'file' => $this->fullSupplierXlsx(),
                'mode' => 'create_and_update',
            ]);
        $token = basename((string) $response->headers->get('Location'));

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.header', $token), ['header_row' => 3]);

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.mapping', $token), $this->fullMappingPayload() + [
                'save_profile'     => 1,
                'profile_name'     => 'TestBrand prijslijst',
                'profile_supplier' => 'TEST Leverancier',
                'profile_sheet_pattern' => 'prijslijst',
            ]);

        $profile = HvacMappingProfile::where('name', 'TestBrand prijslijst')->first();
        $this->assertNotNull($profile);
        $this->assertSame(3, $profile->header_row);
        $this->assertSame('sku', $profile->column_map['Artikelnummer']);
        $this->assertTrue($profile->is_active);

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.confirm', $token));
        $this->assertSame(2, HvacProduct::count());

        // Second import with the profile: jumps straight to the preview.
        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.upload'), [
                'file'       => $this->fullSupplierXlsx(),
                'mode'       => 'create_and_update',
                'profile_id' => $profile->id,
            ]);
        $token2 = basename((string) $response->headers->get('Location'));

        $preview = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.guided.step', $token2));
        $preview->assertOk();
        $preview->assertSee('Controleer en bevestig');
        $preview->assertSee('TestBrand prijslijst');

        // Still requires explicit confirmation.
        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.confirm', $token2));
        $this->assertSame(2, HvacProduct::count()); // updated, not duplicated
    }

    public function test_profile_mismatch_warns_and_falls_back_to_manual_mapping(): void
    {
        $profile = HvacMappingProfile::create([
            'name'          => 'Oud profiel',
            'supplier_name' => 'TEST Leverancier',
            'header_row'    => 3,
            'column_map'    => ['Bestaat niet meer' => 'sku', 'Ook weg' => 'name'],
            'decimal_format' => 'auto',
            'is_active'     => true,
        ]);

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.upload'), [
                'file'       => $this->fullSupplierXlsx(),
                'mode'       => 'create_and_update',
                'profile_id' => $profile->id,
            ]);
        $token = basename((string) $response->headers->get('Location'));

        $step = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.guided.step', $token));

        // Not the preview: back at the manual header step, with a warning.
        $step->assertOk();
        $step->assertSee('komt niet meer overeen met profiel');
        $step->assertSee('Waar staan de kolomkoppen?');
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

    public function test_wizard_requires_admin(): void
    {
        $this->post(route('admin.hvac.import.guided.upload'), [
            'file' => $this->supplierXlsx(),
            'mode' => 'create_and_update',
        ])->assertRedirect(); // to admin login, not into the wizard

        $this->assertSame([], Storage::disk('local')->files('hvac-imports'));
    }

    public function test_classic_template_import_accepts_xlsx(): void
    {
        // Template-shaped workbook (headers on row 1) through the CLASSIC
        // import flow.
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
