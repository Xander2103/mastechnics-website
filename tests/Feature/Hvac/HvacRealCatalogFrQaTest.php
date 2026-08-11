<?php

namespace Tests\Feature\Hvac;

use App\Models\HvacImportCatalog;
use App\Models\HvacProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Opt-in QA harness: drives the wizard with the REAL CatalogFR.csv (50,991
 * rows, tab-delimited, cp1252) when it is present on this machine. Skipped
 * everywhere else — the sanitized fixture tests cover CI.
 */
class HvacRealCatalogFrQaTest extends TestCase
{
    use RefreshDatabase;

    private const REAL_FILE = 'C:\\Users\\duisb\\Downloads\\CatalogFR.csv';

    protected function setUp(): void
    {
        parent::setUp();
        if (! is_file(self::REAL_FILE)) {
            $this->markTestSkipped('Real CatalogFR.csv not present on this machine.');
        }
        Storage::fake('local');
    }

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com'];
    }

    public function test_martin_walkthrough_with_the_real_file(): void
    {
        // Stap 1 — Bestand: upload + "Bestand analyseren".
        $file = new UploadedFile(self::REAL_FILE, 'CatalogFR.csv', 'text/csv', null, true);
        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.upload'), [
                'file' => $file, 'supplier_name' => 'TestSupplier BV',
            ]);
        $response->assertRedirect();
        preg_match('/guided\/([A-Za-z0-9]{40})/', (string) $response->headers->get('Location'), $m);
        $token = $m[1];

        // Stap 2 — Producten: no delimiter/sheet/header question appeared;
        // the category screen lists real groups with real counts.
        $step = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.guided.step', $token));
        $step->assertOk()
            ->assertViewIs('admin.hvac.imports.guided.categories')
            ->assertSee('Climatiseurs')
            ->assertViewHas('values', function ($values) {
                return ($values['Climatiseurs'] ?? 0) === 123 && array_sum($values) === 50991;
            });

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.categories', $token), [
                'categories' => ['Climatiseurs'],
            ])->assertRedirect();

        // Stap 3 — Controle: exactly the 123 selected products, price question open.
        $review = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.guided.step', $token));
        $review->assertOk()
            ->assertViewIs('admin.hvac.imports.guided.review')
            ->assertViewHas('totalRows', 123)
            ->assertSee('BrutPrice');

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.review', $token), [
                'price_meaning' => 'gross',
                'type_fallback' => 'installation_accessory',
            ])->assertRedirect();

        // Stap 4 — Importeren.
        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.guided.step', $token))
            ->assertOk()
            ->assertSee('Klaar om te importeren');
        $this->assertSame(0, HvacProduct::count());

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.confirm', $token), [
                'mode' => 'create_and_update', 'catalog_choice' => 'new',
                'catalog_name' => 'TestSupplier — CatalogFR 2026',
            ])->assertRedirect(route('admin.hvac.import.guided.result', $token));

        // Stap 5 — Resultaat: only Climatiseurs reached the database.
        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.guided.result', $token))
            ->assertOk()
            ->assertSee('Import voltooid');

        // 123 selected; 11 are indoor/outdoor units whose label carries no
        // capacity anywhere — those are correctly rejected with a visible
        // per-row reason (units without capacity would poison the engine).
        $this->assertSame(112, HvacProduct::count());
        $this->assertSame(112, HvacImportCatalog::firstOrFail()->products()->count());
        $this->assertSame(0, HvacProduct::whereNotNull('purchase_price_excl_vat')->count(), 'gross prices never land in price columns');
        $this->assertGreaterThan(0, HvacProduct::where('product_type', 'indoor_unit')->count());
        $this->assertGreaterThan(0, HvacProduct::where('product_type', 'outdoor_unit')->count());
    }
}
