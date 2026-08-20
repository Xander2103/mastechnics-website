<?php

namespace Tests\Feature\Hvac;

use App\Models\HvacImportCatalog;
use App\Models\HvacSupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HvacSupplierViewTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com'];
    }

    public function test_supplier_index_lists_catalogs_with_counts_and_last_import(): void
    {
        $supplier = HvacSupplier::create(['name' => 'Cairox', 'is_active' => true]);

        $old = HvacImportCatalog::create([
            'name' => 'Cairox HVAC 2026', 'hvac_supplier_id' => $supplier->id,
            'source_type' => 'guided', 'product_count' => 86,
            'status' => HvacImportCatalog::STATUS_ACTIVE, 'imported_at' => now()->subDays(9),
        ]);
        HvacImportCatalog::create([
            'name' => 'Cairox HVAC 2027', 'hvac_supplier_id' => $supplier->id,
            'source_type' => 'guided', 'product_count' => 90,
            'status' => HvacImportCatalog::STATUS_ACTIVE, 'imported_at' => now()->subDay(),
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.suppliers.index'));

        $response->assertOk()
            ->assertSee('Cairox HVAC 2026')
            ->assertSee('Cairox HVAC 2027')
            ->assertSee(route('admin.hvac.catalogs.show', $old), false)
            ->assertSee(now()->subDay()->format('d/m/Y'));
    }

    public function test_archived_catalogs_do_not_count_as_active_lists(): void
    {
        $supplier = HvacSupplier::create(['name' => 'Fujitsu Import', 'is_active' => true]);

        HvacImportCatalog::create([
            'name' => 'Airstage 2026-2027', 'hvac_supplier_id' => $supplier->id,
            'source_type' => 'guided', 'product_count' => 123,
            'status' => HvacImportCatalog::STATUS_ACTIVE, 'imported_at' => now(),
        ]);
        HvacImportCatalog::create([
            'name' => 'Airstage 2024 (oud)', 'hvac_supplier_id' => $supplier->id,
            'source_type' => 'guided', 'product_count' => 100,
            'status' => HvacImportCatalog::STATUS_ARCHIVED, 'imported_at' => now()->subYear(),
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.suppliers.index'));

        $response->assertOk();
        $suppliers = $response->viewData('suppliers');
        $this->assertSame(1, $suppliers->firstWhere('name', 'Fujitsu Import')->active_catalogs_count);
    }

    public function test_supplier_index_shows_browse_tabs(): void
    {
        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.suppliers.index'))
            ->assertOk()
            ->assertSee('Productlijsten')
            ->assertSee('Alle producten');
    }

    public function test_supplier_index_requires_admin(): void
    {
        $this->get(route('admin.hvac.suppliers.index'))->assertRedirect();
    }
}
