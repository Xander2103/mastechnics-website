<?php

namespace Tests\Feature\Hvac;

use App\Models\HvacBrand;
use App\Models\HvacProduct;
use App\Models\HvacProductCompatibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HvacCatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com'];
    }

    private function brand(): HvacBrand
    {
        return HvacBrand::firstOrCreate(['slug' => 'testbrand'], ['name' => 'TestBrand', 'is_active' => true]);
    }

    private function productPayload(array $overrides = []): array
    {
        return array_merge([
            'hvac_brand_id' => $this->brand()->id,
            'sku'           => 'TB-100',
            'model'         => 'TestBrand 100',
            'name'          => 'TestBrand indoor 100',
            'product_type'  => 'indoor_unit',
            'cooling_capacity_kw' => '2.5',
            'is_active'     => '1',
        ], $overrides);
    }

    public function test_catalog_requires_admin_auth(): void
    {
        $this->get(route('admin.hvac.products.index'))
            ->assertRedirect(route('admin.login'));

        $this->post(route('admin.hvac.products.store'), $this->productPayload())
            ->assertRedirect(route('admin.login'));
    }

    public function test_products_index_renders_empty_catalog(): void
    {
        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.products.index'))
            ->assertOk()
            ->assertSee('HVAC-producten')
            ->assertSee('Geen producten gevonden');
    }

    public function test_product_can_be_created_and_updated(): void
    {
        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.products.store'), $this->productPayload());

        $response->assertSessionHasNoErrors();
        $product = HvacProduct::first();
        $this->assertSame('TB-100', $product->sku);

        $this->withSession($this->adminSession())
            ->patch(route('admin.hvac.products.update', $product), $this->productPayload([
                'name' => 'TestBrand indoor 100 v2',
            ]))->assertSessionHasNoErrors();

        $this->assertSame('TestBrand indoor 100 v2', $product->fresh()->name);
    }

    public function test_invalid_product_type_is_rejected(): void
    {
        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.products.store'), $this->productPayload([
                'product_type' => 'nuclear_reactor',
            ]))->assertSessionHasErrors('product_type');
    }

    public function test_product_deactivation_instead_of_deletion(): void
    {
        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.products.store'), $this->productPayload());
        $product = HvacProduct::first();

        $this->withSession($this->adminSession())
            ->patch(route('admin.hvac.products.toggle', $product));

        $this->assertFalse($product->fresh()->is_active);
        $this->assertDatabaseCount('hvac_products', 1);
    }

    public function test_duplicate_creates_inactive_copy_with_new_sku(): void
    {
        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.products.store'), $this->productPayload());
        $product = HvacProduct::first();

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.products.duplicate', $product));

        $copy = HvacProduct::where('sku', 'TB-100-KOPIE')->first();
        $this->assertNotNull($copy);
        $this->assertFalse($copy->is_active);
        $this->assertSame($product->model, $copy->model);
    }

    public function test_brand_and_supplier_can_be_created(): void
    {
        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.brands.store'), ['name' => 'AnderMerk'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('hvac_brands', ['slug' => 'andermerk']);

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.suppliers.store'), ['name' => 'Leverancier BV'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('hvac_suppliers', ['name' => 'Leverancier BV']);
    }

    public function test_compatibility_rule_can_be_added_and_deactivated(): void
    {
        $this->withSession($this->adminSession())->post(route('admin.hvac.products.store'), $this->productPayload());
        $this->withSession($this->adminSession())->post(route('admin.hvac.products.store'), $this->productPayload([
            'sku' => 'TB-200', 'product_type' => 'outdoor_unit',
        ]));

        [$indoor, $outdoor] = HvacProduct::orderBy('id')->get();

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.compatibilities.store'), [
                'parent_product_id'     => $outdoor->id,
                'compatible_product_id' => $indoor->id,
                'compatibility_type'    => 'indoor_outdoor',
            ])->assertSessionHasNoErrors();

        $rule = HvacProductCompatibility::first();
        $this->assertTrue($rule->is_active);

        $this->withSession($this->adminSession())
            ->patch(route('admin.hvac.compatibilities.toggle', $rule));

        $this->assertFalse($rule->fresh()->is_active);
    }

    public function test_self_compatibility_is_rejected(): void
    {
        $this->withSession($this->adminSession())->post(route('admin.hvac.products.store'), $this->productPayload());
        $product = HvacProduct::first();

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.compatibilities.store'), [
                'parent_product_id'     => $product->id,
                'compatible_product_id' => $product->id,
                'compatibility_type'    => 'indoor_outdoor',
            ])->assertSessionHasErrors('compatible_product_id');
    }
}
