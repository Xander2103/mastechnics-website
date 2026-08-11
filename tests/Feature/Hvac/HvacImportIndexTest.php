<?php

namespace Tests\Feature\Hvac;

use App\Models\HvacMappingProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the import landing page (/admin/hvac/import).
 *
 * Root cause of the 2026-08-11 HTTP 500: the Blade template queried
 * hvac_mapping_profiles directly inside an @php block, so any database
 * problem with that table (locally: the migration had not been run)
 * crashed the whole page render. Data must come from the controller.
 */
class HvacImportIndexTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com'];
    }

    public function test_import_index_renders_with_mapping_profiles_from_controller(): void
    {
        HvacMappingProfile::create([
            'name' => 'Prijslijst standaard', 'supplier_name' => 'TestSupplier',
            'header_row' => 3, 'column_map' => ['sku' => 1], 'is_active' => true,
        ]);
        HvacMappingProfile::create([
            'name' => 'Oud formaat', 'supplier_name' => 'TestSupplier',
            'header_row' => 1, 'column_map' => ['sku' => 0], 'is_active' => false,
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.index'));

        $response->assertOk()
            ->assertViewIs('admin.hvac.imports.index')
            ->assertViewHas('mappingProfiles', fn ($profiles) => $profiles->count() === 2)
            ->assertSee('Prijslijst standaard')
            ->assertSee('Oud formaat');
    }

    public function test_import_index_renders_without_any_mapping_profiles(): void
    {
        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.index'))
            ->assertOk()
            ->assertViewHas('mappingProfiles', fn ($profiles) => $profiles->isEmpty());
    }

    public function test_import_index_blade_does_not_query_the_database_directly(): void
    {
        $blade = file_get_contents(resource_path('views/admin/hvac/imports/index.blade.php'));

        $this->assertStringNotContainsString('HvacMappingProfile::', $blade,
            'The import index Blade must not query models directly; pass data from HvacImportController::index().');
        $this->assertStringNotContainsString('\App\Models\\', $blade,
            'The import index Blade must not reference models directly; pass data from HvacImportController::index().');
    }

    public function test_guest_is_redirected_from_import_index(): void
    {
        $this->get(route('admin.hvac.import.index'))
            ->assertRedirect(route('admin.login'));
    }
}
