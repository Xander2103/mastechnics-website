<?php

namespace Tests\Feature\Hvac;

use App\Models\HvacImportCatalog;
use App\Models\HvacImportRun;
use App\Models\HvacMappingProfile;
use App\Models\HvacProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CatalogFrFixture;
use Tests\TestCase;

/**
 * End-to-end wizard test with the CatalogFR.csv structure: tab-delimited,
 * cp1252, mixed HVAC and sanitary categories. The Martin scenario: upload →
 * pick "Climatiseurs" → answer the price question → import → save profile →
 * next upload is recognized automatically.
 */
class HvacCatalogFrImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com'];
    }

    private function uploadFixture(): string
    {
        $file = UploadedFile::fake()->createWithContent('CatalogFR.csv', CatalogFrFixture::contents());

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.upload'), [
                'file'          => $file,
                'supplier_name' => 'TestSupplier BV',
            ]);

        $response->assertRedirect();
        preg_match('/guided\/([A-Za-z0-9]{40})/', $response->headers->get('Location'), $m);
        $this->assertNotEmpty($m[1] ?? null, 'upload must redirect into the wizard');

        return $m[1];
    }

    private function stepUrl(string $token): string
    {
        return route('admin.hvac.import.guided.step', $token);
    }

    public function test_tab_delimited_file_skips_technical_questions_and_asks_categories(): void
    {
        $token = $this->uploadFixture();

        $response = $this->withSession($this->adminSession())->get($this->stepUrl($token));

        // Delimiter, worksheet and header row were all detected automatically:
        // the FIRST thing Martin sees is the business question.
        $response->assertOk()
            ->assertViewIs('admin.hvac.imports.guided.categories')
            ->assertSee('Welke producten wilt u importeren?')
            ->assertSee(CatalogFrFixture::GROUP_CLIMATE)
            ->assertSee(CatalogFrFixture::GROUP_PUMPS);
    }

    public function test_climate_categories_are_preselected_but_nothing_is_excluded(): void
    {
        $token = $this->uploadFixture();

        $response = $this->withSession($this->adminSession())->get($this->stepUrl($token));

        $response->assertViewHas('preselected', fn ($p) => $p === [CatalogFrFixture::GROUP_CLIMATE])
            ->assertViewHas('values', fn ($v) => array_sum($v) === CatalogFrFixture::TOTAL_COUNT);
    }

    private function chooseClimatiseurs(string $token): void
    {
        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.categories', $token), [
                'categories' => [CatalogFrFixture::GROUP_CLIMATE],
            ])
            ->assertRedirect($this->stepUrl($token));
    }

    public function test_category_selection_filters_rows_reaching_the_review_step(): void
    {
        $token = $this->uploadFixture();
        $this->chooseClimatiseurs($token);

        $response = $this->withSession($this->adminSession())->get($this->stepUrl($token));

        $response->assertOk()
            ->assertViewIs('admin.hvac.imports.guided.review')
            ->assertViewHas('totalRows', CatalogFrFixture::CLIMATE_COUNT)
            ->assertSee('Gegevens controleren');
    }

    public function test_review_asks_price_meaning_but_not_already_recognized_fields(): void
    {
        $token = $this->uploadFixture();
        $this->chooseClimatiseurs($token);

        $response = $this->withSession($this->adminSession())->get($this->stepUrl($token));

        $response->assertSee('Wat betekent')
            ->assertSee('BrutPrice')
            ->assertSee('Geavanceerde koppelingen');

        // Supplier and brand are already known (form + ProviderName column).
        $response->assertViewHas('questions', function ($questions) {
            return isset($questions['price'])
                && ! isset($questions['supplier'])
                && ! isset($questions['brand']);
        });
    }

    private function answerReview(string $token, array $overrides = []): void
    {
        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.review', $token), $overrides + [
                'price_meaning' => 'gross',
                'type_fallback' => 'installation_accessory',
            ])
            ->assertRedirect($this->stepUrl($token));
    }

    public function test_price_question_must_be_answered_before_import(): void
    {
        $token = $this->uploadFixture();
        $this->chooseClimatiseurs($token);

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.review', $token), [])
            ->assertSessionHasErrors('review');
    }

    public function test_confirm_step_shows_counts_and_no_products_are_written_before_confirm(): void
    {
        $token = $this->uploadFixture();
        $this->chooseClimatiseurs($token);
        $this->answerReview($token);

        $response = $this->withSession($this->adminSession())->get($this->stepUrl($token));

        $response->assertOk()
            ->assertViewIs('admin.hvac.imports.guided.confirm')
            ->assertSee('Klaar om te importeren')
            ->assertSee('Naam van deze productlijst')
            ->assertViewHas('createCount', CatalogFrFixture::CLIMATE_COUNT);

        $this->assertSame(0, HvacProduct::count(), 'no DB writes before explicit confirmation');
        $this->assertSame(0, HvacImportCatalog::count());
    }

    private function confirmImport(string $token, array $overrides = []): void
    {
        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.confirm', $token), $overrides + [
                'mode'           => 'create_and_update',
                'catalog_choice' => 'new',
                'catalog_name'   => 'TestSupplier — CatalogFR 2026',
            ])
            ->assertRedirect(route('admin.hvac.import.guided.result', $token));
    }

    public function test_confirm_imports_only_selected_category_with_catalog_and_run(): void
    {
        $token = $this->uploadFixture();
        $this->chooseClimatiseurs($token);
        $this->answerReview($token);
        $this->confirmImport($token);

        // Only the 5 Climatiseurs rows — pumps and shower bars never imported.
        $this->assertSame(CatalogFrFixture::CLIMATE_COUNT, HvacProduct::count());
        $this->assertSame(0, HvacProduct::where('name', 'like', '%kelderpomp%')->count());

        // Product types: inferred for units, fallback for the remote control.
        $this->assertSame(2, HvacProduct::where('product_type', 'indoor_unit')->count());
        $this->assertSame(2, HvacProduct::where('product_type', 'outdoor_unit')->count());
        $this->assertSame(1, HvacProduct::where('product_type', 'installation_accessory')->count());

        // Gross price: never in the price columns, provenance keeps it.
        $indoor = HvacProduct::where('product_type', 'indoor_unit')->first();
        $this->assertNull($indoor->purchase_price_excl_vat);
        $this->assertNull($indoor->default_sale_price_excl_vat);
        $this->assertSame('gross', $indoor->metadata['import']['price']['meaning']);
        $this->assertTrue($indoor->metadata['import']['needs_review']);

        // Derived capacity from "2,5 kW" in the label.
        $this->assertEqualsWithDelta(2.5, HvacProduct::where('sku', '118970')->first()->cooling_capacity_kw, 0.001);

        // Catalog + pivot + run history.
        $catalog = HvacImportCatalog::firstOrFail();
        $this->assertSame('TestSupplier — CatalogFR 2026', $catalog->name);
        $this->assertSame(CatalogFrFixture::CLIMATE_COUNT, $catalog->product_count);
        $this->assertSame(CatalogFrFixture::CLIMATE_COUNT, $catalog->products()->count());
        $this->assertNotNull($catalog->products()->first()->pivot->source_row);

        $run = HvacImportRun::firstOrFail();
        $this->assertSame(CatalogFrFixture::CLIMATE_COUNT, $run->created_count);
        $this->assertSame('CatalogFR.csv', $run->source_filename);
    }

    public function test_result_page_offers_profile_save_and_saved_profile_is_recognized(): void
    {
        $token = $this->uploadFixture();
        $this->chooseClimatiseurs($token);
        $this->answerReview($token);
        $this->confirmImport($token);

        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.guided.result', $token))
            ->assertOk()
            ->assertSee('Import voltooid')
            ->assertSee('producten toegevoegd')
            ->assertSee('Deze instellingen onthouden');

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.profile', $token))
            ->assertRedirect(route('admin.hvac.import.index'));

        $profile = HvacMappingProfile::firstOrFail();
        $this->assertSame("\t", $profile->delimiter);
        $this->assertSame([CatalogFrFixture::GROUP_CLIMATE], $profile->category_filter['selected']);
        $this->assertSame('gross', $profile->price_semantics['meaning']);
        $this->assertSame('installation_accessory', $profile->type_fallback);

        // Second upload of the same file: recognized, all questions answered,
        // Martin lands straight on the review step.
        $token2 = $this->uploadFixture();
        $response = $this->withSession($this->adminSession())->get($this->stepUrl($token2));

        $response->assertOk()
            ->assertViewIs('admin.hvac.imports.guided.review')
            ->assertSee('We herkennen dit bestand');
    }

    public function test_updating_existing_catalog_records_second_run_and_preserves_missing_products(): void
    {
        // First import: everything.
        $token = $this->uploadFixture();
        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.categories', $token), [
                'categories' => [CatalogFrFixture::GROUP_CLIMATE, CatalogFrFixture::GROUP_PUMPS, CatalogFrFixture::GROUP_SHOWER],
            ]);
        $this->answerReview($token);
        $this->confirmImport($token);
        $catalog = HvacImportCatalog::firstOrFail();
        $firstCount = HvacProduct::count();

        // Second import into the SAME catalog, now only Climatiseurs.
        $token2 = $this->uploadFixture();
        $this->chooseClimatiseurs($token2);
        $this->answerReview($token2);
        $this->confirmImport($token2, [
            'catalog_choice' => 'existing',
            'catalog_id'     => $catalog->id,
            'catalog_name'   => null,
        ]);

        // Missing products are NEVER deleted or deactivated without the flag.
        $this->assertSame($firstCount, HvacProduct::count());
        $this->assertSame(0, HvacProduct::where('is_active', false)->count());
        $this->assertSame(2, $catalog->runs()->count());
    }

    public function test_deactivate_missing_flag_only_touches_products_without_another_active_catalog(): void
    {
        $token = $this->uploadFixture();
        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.categories', $token), [
                'categories' => [CatalogFrFixture::GROUP_CLIMATE, CatalogFrFixture::GROUP_PUMPS],
            ]);
        $this->answerReview($token);
        $this->confirmImport($token);
        $catalog = HvacImportCatalog::firstOrFail();

        $token2 = $this->uploadFixture();
        $this->chooseClimatiseurs($token2);
        $this->answerReview($token2);
        $this->confirmImport($token2, [
            'catalog_choice'     => 'existing',
            'catalog_id'         => $catalog->id,
            'catalog_name'       => null,
            'deactivate_missing' => 1,
        ]);

        // The two pump products are missing from the second file → inactive.
        $this->assertSame(2, HvacProduct::where('is_active', false)->count());
        $this->assertSame(0, HvacProduct::where('is_active', false)->where('product_type', 'indoor_unit')->count());
        // Never deleted.
        $this->assertSame(7, HvacProduct::count());
    }

    public function test_guest_cannot_use_the_wizard(): void
    {
        $file = UploadedFile::fake()->createWithContent('CatalogFR.csv', CatalogFrFixture::contents());

        $this->post(route('admin.hvac.import.guided.upload'), ['file' => $file])
            ->assertRedirect(route('admin.login'));
    }

    public function test_garbage_file_gets_friendly_error_not_500(): void
    {
        $file = UploadedFile::fake()->createWithContent('kapot.xlsx', "\x00\x01\x02 dit is geen xlsx");

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.guided.upload'), ['file' => $file])
            ->assertRedirect(route('admin.hvac.import.index'))
            ->assertSessionHasErrors('guided_file');
    }
}
