<?php

namespace Tests\Feature\Hvac;

use App\Models\CustomerRequest;
use App\Models\HvacBrand;
use App\Models\HvacProduct;
use App\Models\HvacRecommendation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HvacAdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com'];
    }

    private function makeAircoRequest(array $answerOverrides = [], array $attrs = []): CustomerRequest
    {
        $answers = array_merge([
            'rooms' => [[
                'type' => 'slaapkamer', 'width' => 4, 'length' => 5, 'height' => 2.5,
                'roof_type' => 'none', 'windows' => 'large', 'orientation' => 'south',
            ]],
            'insulation_level' => 'good',
            'customer_type'    => 'residential',
        ], $answerOverrides);

        return CustomerRequest::create(array_merge([
            'locale' => 'nl', 'service_slug' => 'airco', 'request_type' => 'installation',
            'service_category' => 'airco_offerte',
            'customer_name' => 'Test Klant', 'customer_email' => 'test@example.com',
            'description' => '', 'status' => 'new',
            'metadata' => ['answers' => $answers],
        ], $attrs));
    }

    private function seedCatalog(): void
    {
        $brand = HvacBrand::create(['name' => 'TestBrand', 'slug' => 'testbrand']);
        HvacProduct::create([
            'hvac_brand_id' => $brand->id, 'sku' => 'TB-SET', 'model' => 'TestBrand Set 35',
            'name' => 'TestBrand single split set', 'product_type' => 'single_split_set',
            'cooling_capacity_kw' => 3.5, 'maximum_pipe_length_m' => 20, 'maximum_height_difference_m' => 10,
            'default_sale_price_excl_vat' => 1500, 'purchase_price_excl_vat' => 1000,
            'stock_quantity' => 5, 'is_active' => true,
        ]);
        foreach (['wall_bracket', 'vibration_damper', 'pipe', 'trunking', 'electrical_accessory', 'drain_hose', 'condensate_pump'] as $i => $type) {
            HvacProduct::create([
                'hvac_brand_id' => $brand->id, 'sku' => "TB-ACC-{$i}", 'model' => "Accessoire {$type}",
                'name' => "Accessoire {$type}", 'product_type' => $type,
                'default_sale_price_excl_vat' => 10, 'purchase_price_excl_vat' => 5, 'is_active' => true,
            ]);
        }
    }

    private function calculate(CustomerRequest $request): void
    {
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.calculate', $request))
            ->assertRedirect(route('admin.requests.show', $request));
    }

    // ── Visibility & auth ─────────────────────────────────────────────────────

    public function test_non_airco_request_has_no_hvac_panel(): void
    {
        $request = $this->makeAircoRequest([], ['service_category' => 'waterverzachter']);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertDontSee('Automatische airco-voorcalculatie');
    }

    public function test_airco_request_shows_panel_with_disclaimer(): void
    {
        $request = $this->makeAircoRequest();

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('Automatische airco-voorcalculatie')
            ->assertSee('Voorcalculatie uitvoeren')
            ->assertSee('Controleer vermogen, compatibiliteit, leidinglengtes');
    }

    public function test_calculate_requires_admin_auth(): void
    {
        $request = $this->makeAircoRequest();

        $this->post(route('admin.requests.hvac.calculate', $request))
            ->assertRedirect(route('admin.login'));
    }

    public function test_calculate_rejects_non_airco_requests(): void
    {
        $request = $this->makeAircoRequest([], ['service_category' => 'sanitair']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.calculate', $request))
            ->assertNotFound();
    }

    // ── Calculation rendering ─────────────────────────────────────────────────

    public function test_calculation_renders_factors_not_raw_json(): void
    {
        $request = $this->makeAircoRequest();
        $this->calculate($request);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request));

        $response->assertOk();
        $response->assertSee('Berekening');
        $response->assertSee('2.599');     // final watts, formatted
        $response->assertSee('3,5');       // target class
        $response->assertSee('Slaapkamer 1');
        $response->assertSee('aanname');   // assumption markers visible
        $response->assertDontSee('"final_watts"');
        $response->assertDontSee('"rule_set"');
    }

    public function test_blocked_calculation_shows_blockers(): void
    {
        $request = $this->makeAircoRequest([
            'rooms' => [[
                'type' => 'woonkamer', 'width' => 5, 'length' => 6,
                'attic_or_flat_roof' => 'yes', 'large_windows' => 'no',
            ]],
        ]);
        $this->calculate($request);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('Berekening geblokkeerd')
            ->assertSee('hoogte ontbreekt');
    }

    public function test_warnings_are_visible(): void
    {
        $request = $this->makeAircoRequest();
        $this->calculate($request);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('Waarschuwingen')
            ->assertSee('aangenomen per kamertype');
    }

    // ── Approval flow ─────────────────────────────────────────────────────────

    public function test_draft_recommendation_can_be_approved_once(): void
    {
        $this->seedCatalog();
        $request = $this->makeAircoRequest();
        $this->calculate($request);

        $recommendation = HvacRecommendation::firstOrFail();
        $this->assertSame('draft', $recommendation->status);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.approve', [$request, $recommendation]))
            ->assertSessionHas('success', 'hvac_recommendation_approved');

        $fresh = $recommendation->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame('admin@test.com', $fresh->approved_by);
        $this->assertNotNull($fresh->approved_at);

        // Duplicate approval is a no-op with a clear message.
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.approve', [$request, $recommendation]))
            ->assertSessionHas('success', 'hvac_already_approved');
    }

    public function test_manual_review_requires_acknowledgement_before_approval(): void
    {
        // Equipment without accessory catalog → manual_review.
        $brand = HvacBrand::create(['name' => 'TestBrand', 'slug' => 'testbrand']);
        HvacProduct::create([
            'hvac_brand_id' => $brand->id, 'sku' => 'TB-SET', 'model' => 'TestBrand Set 35',
            'name' => 'TestBrand single split set', 'product_type' => 'single_split_set',
            'cooling_capacity_kw' => 3.5, 'maximum_pipe_length_m' => 20, 'maximum_height_difference_m' => 10,
            'default_sale_price_excl_vat' => 1500, 'is_active' => true,
        ]);
        $request = $this->makeAircoRequest();
        $this->calculate($request);

        $recommendation = HvacRecommendation::firstOrFail();
        $this->assertSame('manual_review', $recommendation->status);

        // Approving directly is refused.
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.approve', [$request, $recommendation]))
            ->assertSessionHas('success', 'hvac_not_approvable');

        // Acknowledgement without reason is rejected.
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.acknowledge', [$request, $recommendation]), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        // With a reason, it becomes draft and can be approved.
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.acknowledge', [$request, $recommendation]), [
                'reason' => 'Prijzen worden handmatig aangevuld op de offerte.',
            ])->assertSessionHas('success', 'hvac_warnings_acknowledged');

        $this->assertSame('draft', $recommendation->fresh()->status);
        $this->assertDatabaseHas('hvac_manual_overrides', [
            'field' => "recommendation:{$recommendation->id}:warnings_acknowledged",
        ]);
    }

    public function test_recommendation_can_be_rejected(): void
    {
        $this->seedCatalog();
        $request = $this->makeAircoRequest();
        $this->calculate($request);
        $recommendation = HvacRecommendation::firstOrFail();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.reject', [$request, $recommendation]));

        $this->assertSame('rejected', $recommendation->fresh()->status);
    }

    // ── Security ──────────────────────────────────────────────────────────────

    public function test_cross_request_access_is_blocked(): void
    {
        $this->seedCatalog();
        $requestA = $this->makeAircoRequest();
        $this->calculate($requestA);
        $recommendation = HvacRecommendation::firstOrFail();

        $requestB = $this->makeAircoRequest([], ['customer_email' => 'ander@example.com']);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.approve', [$requestB, $recommendation]))
            ->assertNotFound();

        $item = $recommendation->items()->first();
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.items.override', [$requestB, $item]), [
                'sale_unit_price' => 1, 'reason' => 'poging tot manipulatie',
            ])->assertNotFound();

        $this->assertSame('draft', $recommendation->fresh()->status);
    }

    public function test_item_override_via_http_requires_reason(): void
    {
        $this->seedCatalog();
        $request = $this->makeAircoRequest();
        $this->calculate($request);
        $recommendation = HvacRecommendation::firstOrFail();
        $item = $recommendation->items()->where('item_type', 'equipment')->first();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.items.override', [$request, $item]), [
                'sale_unit_price' => '1400',
            ])->assertSessionHasErrors('reason');

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.items.override', [$request, $item]), [
                'sale_unit_price' => '1400',
                'reason'          => 'Commerciële korting toegestaan',
            ])->assertSessionHas('success', 'hvac_override_applied');

        $this->assertEquals(1400.0, $item->fresh()->sale_unit_price);
    }
}
