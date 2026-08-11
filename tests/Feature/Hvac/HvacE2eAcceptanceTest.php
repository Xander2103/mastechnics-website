<?php

namespace Tests\Feature\Hvac;

use App\Models\CustomerRequest;
use App\Models\HvacBrand;
use App\Models\HvacImportCatalog;
use App\Models\HvacProduct;
use App\Models\HvacProductCompatibility;
use App\Models\HvacRecommendation;
use App\Models\HvacSupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Real-world acceptance scenarios (A–H) plus regression tests for the
 * hardening findings of the 2026-08-11 audit. Fictional TestBrand data only.
 */
class HvacE2eAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com'];
    }

    private function brand(): HvacBrand
    {
        return HvacBrand::firstOrCreate(['slug' => 'testbrand'], ['name' => 'TestBrand', 'is_active' => true]);
    }

    private function makeProduct(array $attrs): HvacProduct
    {
        return HvacProduct::create(array_merge([
            'hvac_brand_id' => $this->brand()->id,
            'is_active'     => true,
        ], $attrs));
    }

    private function seedAccessories(): void
    {
        foreach (['wall_bracket', 'vibration_damper', 'pipe', 'trunking', 'electrical_accessory', 'drain_hose', 'condensate_pump'] as $i => $type) {
            $this->makeProduct([
                'sku' => "TB-ACC-{$i}", 'model' => "Accessoire {$type}", 'name' => "Accessoire {$type}",
                'product_type' => $type,
                'default_sale_price_excl_vat' => 10, 'purchase_price_excl_vat' => 5,
            ]);
        }
    }

    private function seedSingleSplitSet(array $attrs = []): HvacProduct
    {
        return $this->makeProduct(array_merge([
            'sku' => 'TB-SET', 'model' => 'TestBrand Set 35', 'name' => 'TestBrand single split set',
            'product_type' => 'single_split_set', 'cooling_capacity_kw' => 3.5,
            'maximum_pipe_length_m' => 20, 'maximum_height_difference_m' => 10,
            'default_sale_price_excl_vat' => 1500, 'purchase_price_excl_vat' => 1000,
            'stock_quantity' => 5,
        ], $attrs));
    }

    private function makeAircoRequest(array $answerOverrides = [], array $attrs = []): CustomerRequest
    {
        $answers = array_merge([
            'rooms' => [[
                'type' => 'woonkamer', 'width' => 4, 'length' => 5, 'height' => 2.5,
                'roof_type' => 'none', 'windows' => 'large', 'orientation' => 'west',
            ]],
            'insulation_level' => 'good',
            'customer_type'    => 'residential',
        ], $answerOverrides);

        return CustomerRequest::create(array_merge([
            'locale' => 'nl', 'service_slug' => 'airco', 'request_type' => 'installation',
            'service_category' => 'airco_offerte',
            'customer_name' => 'Test Klant', 'customer_email' => 'klant@example.com',
            'description' => '', 'status' => 'new',
            'metadata' => ['answers' => $answers],
        ], $attrs));
    }

    private function calculate(CustomerRequest $request): void
    {
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.calculate', $request))
            ->assertRedirect(route('admin.requests.show', $request));
    }

    // ── Scenario A: simple single split, complete pricing ────────────────────

    public function test_scenario_a_single_split_full_flow_to_draft_quote(): void
    {
        $this->seedSingleSplitSet();
        $this->seedAccessories();
        $request = $this->makeAircoRequest();
        $this->calculate($request);

        $recommendation = HvacRecommendation::firstOrFail();
        $this->assertSame('draft', $recommendation->status);
        $this->assertNotNull($recommendation->total_incl_vat);
        $this->assertGreaterThan(0, $recommendation->margin_amount);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.approve', [$request, $recommendation]));
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.convert', [$request, $recommendation->fresh()]));

        $this->assertSame('converted', $recommendation->fresh()->status);
        $this->assertDatabaseCount('quotes', 1);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    // ── Scenario B: 2-room multi split with link-level connected capacity ────

    public function test_scenario_b_multi_split_uses_manufacturer_link_capacity_window(): void
    {
        $this->seedAccessories();
        $indoorA = $this->makeProduct([
            'sku' => 'TB-IN-25', 'model' => 'TB Indoor 25', 'name' => 'TB binnenunit 2.5',
            'product_type' => 'indoor_unit', 'cooling_capacity_kw' => 2.6,
            'default_sale_price_excl_vat' => 600, 'purchase_price_excl_vat' => 400,
        ]);
        $indoorB = $this->makeProduct([
            'sku' => 'TB-IN-35', 'model' => 'TB Indoor 35', 'name' => 'TB binnenunit 3.5',
            'product_type' => 'indoor_unit', 'cooling_capacity_kw' => 3.5,
            'default_sale_price_excl_vat' => 700, 'purchase_price_excl_vat' => 450,
        ]);
        // Outdoor WITHOUT own capacity-window columns: the window lives on the
        // imported manufacturer compatibility rows (the audit's dead columns).
        $outdoor = $this->makeProduct([
            'sku' => 'TB-OUT-2', 'model' => 'TB Multi 62', 'name' => 'TB multi buitenunit 6.2',
            'product_type' => 'multi_split_outdoor', 'cooling_capacity_kw' => 6.2,
            'maximum_connected_indoor_units' => 3,
            'maximum_pipe_length_m' => 60, 'maximum_pipe_length_per_unit_m' => 25, 'maximum_height_difference_m' => 12,
            'default_sale_price_excl_vat' => 2200, 'purchase_price_excl_vat' => 1500,
        ]);
        foreach ([$indoorA, $indoorB] as $indoor) {
            HvacProductCompatibility::create([
                'parent_product_id' => $outdoor->id, 'compatible_product_id' => $indoor->id,
                'compatibility_type' => 'multi_split_indoor', 'is_active' => true,
                'minimum_connected_capacity_kw' => 4.0, 'maximum_connected_capacity_kw' => 8.0,
                'maximum_units' => 2,
            ]);
        }

        $request = $this->makeAircoRequest([
            'rooms' => [
                ['type' => 'woonkamer', 'width' => 4, 'length' => 5, 'height' => 2.5, 'roof_type' => 'none', 'windows' => 'large', 'orientation' => 'west'],
                ['type' => 'slaapkamer', 'width' => 4, 'length' => 4.5, 'height' => 2.5, 'roof_type' => 'attic', 'windows' => 'normal', 'orientation' => 'south'],
            ],
        ]);
        $this->calculate($request);

        $recommendation = HvacRecommendation::firstOrFail();
        // Valid candidate found thanks to the link-level window (sum ~6.1 kW
        // in 4.0–8.0) — before the fix this was always downgraded to
        // manual_review because the outdoor's own window columns are NULL.
        $this->assertSame('draft', $recommendation->status);
        // Equipment lines: two indoor units + one outdoor unit.
        $this->assertSame(3, $recommendation->items()->where('item_type', 'equipment')->count());
    }

    // ── Scenario C: missing technical input → warnings, never silent ─────────

    public function test_scenario_c_missing_input_yields_visible_assumptions_not_silent_approval(): void
    {
        $this->seedSingleSplitSet();
        $this->seedAccessories();
        $request = $this->makeAircoRequest(['insulation_level' => null]);
        $this->calculate($request);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request));
        $response->assertOk();

        // Never silent: either the calculation blocks, or it carries visible
        // assumption warnings — and nothing is auto-approved.
        $calculation = \App\Models\HvacCalculation::firstOrFail();
        if ($calculation->status === 'blocked') {
            $response->assertSee('geblokkeerd');
        } else {
            $this->assertNotEmpty($calculation->warnings, 'assumptions must surface as warnings');
            $response->assertSee('aangenomen');
        }
        $this->assertSame(0, HvacRecommendation::where('status', 'approved')->count());
    }

    // ── Scenario D: no compatible outdoor unit ───────────────────────────────

    public function test_scenario_d_indoor_without_compatibility_forces_manual_review(): void
    {
        $this->seedAccessories();
        $this->makeProduct([
            'sku' => 'TB-IN-35', 'model' => 'TB Indoor 35', 'name' => 'TB binnenunit 3.5',
            'product_type' => 'indoor_unit', 'cooling_capacity_kw' => 3.5,
            'default_sale_price_excl_vat' => 700, 'purchase_price_excl_vat' => 450,
        ]);
        $request = $this->makeAircoRequest();
        $this->calculate($request);

        $recommendation = HvacRecommendation::first();
        $this->assertTrue(
            $recommendation === null || $recommendation->status === 'manual_review',
            'no compatibility data may never yield an approvable recommendation'
        );
    }

    // ── Scenario E: missing price cannot become quote-ready ──────────────────

    public function test_scenario_e_missing_price_blocks_approval_until_manual_price(): void
    {
        $this->seedSingleSplitSet();
        // Accessories WITHOUT condensate pump price → mandatory line missing.
        foreach (['wall_bracket', 'vibration_damper', 'pipe', 'trunking', 'electrical_accessory'] as $i => $type) {
            $this->makeProduct([
                'sku' => "TB-ACC-{$i}", 'model' => "Accessoire {$type}", 'name' => "Accessoire {$type}",
                'product_type' => $type, 'default_sale_price_excl_vat' => 10, 'purchase_price_excl_vat' => 5,
            ]);
        }
        $request = $this->makeAircoRequest();
        $this->calculate($request);

        $recommendation = HvacRecommendation::firstOrFail();
        $this->assertSame('manual_review', $recommendation->status);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.approve', [$request, $recommendation]))
            ->assertSessionHas('success', 'hvac_not_approvable');

        // Acknowledge, then fix the missing price manually — the readiness
        // gate must clear (audit finding: price_source dead-end).
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.acknowledge', [$request, $recommendation]), [
                'reason' => 'Prijs drainage handmatig aangevuld na controle.',
            ]);
        $missingItem = $recommendation->items()
            ->get()
            ->first(fn ($i) => ($i->metadata['price_source'] ?? '') === 'missing' && ($i->metadata['mandatory'] ?? true));
        $this->assertNotNull($missingItem);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.items.override', [$request, $missingItem]), [
                'sale_unit_price' => 85, 'reason' => 'Prijs leverancier telefonisch bevestigd.',
            ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.approve', [$request, $recommendation->fresh()]))
            ->assertSessionHas('success', 'hvac_recommendation_approved');
    }

    // ── Scenario F: archived-catalog-only products leave circulation ─────────

    public function test_scenario_f_archived_catalog_only_product_is_not_selectable(): void
    {
        $this->seedAccessories();
        $set = $this->seedSingleSplitSet();

        $catalog = HvacImportCatalog::create([
            'name' => 'Oude lijst 2025', 'source_type' => 'guided',
            'status' => HvacImportCatalog::STATUS_ARCHIVED, 'imported_at' => now()->subYear(),
        ]);
        $catalog->products()->attach($set->id);

        $request = $this->makeAircoRequest();
        $this->calculate($request);

        // The only matching unit lives exclusively in an archived list → no recommendation.
        $this->assertNull(HvacRecommendation::first());

        // Historical visibility remains: the archived list still shows it.
        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.catalogs.show', $catalog))
            ->assertOk()->assertSee('TB-SET');

        // Linked to a second, ACTIVE list → selectable again.
        HvacImportCatalog::create(['name' => 'Nieuwe lijst 2026', 'source_type' => 'guided'])
            ->products()->attach($set->id);
        $this->calculate($request);
        $this->assertNotNull(HvacRecommendation::first());
    }

    // ── Scenario G: override after site visit is audited ─────────────────────

    public function test_scenario_g_room_load_override_preserves_original_and_requires_reason(): void
    {
        $this->seedSingleSplitSet();
        $this->seedAccessories();
        $request = $this->makeAircoRequest();
        $this->calculate($request);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.rooms.override-load', $request), [
                'room_index' => 0, 'watts' => 3200,
            ])->assertSessionHasErrors('reason');

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.rooms.override-load', $request), [
                'room_index' => 0, 'watts' => 3200,
                'reason' => 'Plaatsbezoek: extra glaspartij aan westzijde.',
            ])->assertSessionHas('success');

        $calculation = \App\Models\HvacCalculation::firstOrFail();
        $room = $calculation->result['rooms'][0];
        $this->assertArrayHasKey('final_kw_override', $room['load']);
        $this->assertNotNull($room['load']['final_kw'], 'original value preserved');
        $this->assertDatabaseHas('hvac_manual_overrides', [
            'reason' => 'Plaatsbezoek: extra glaspartij aan westzijde.',
        ]);
    }

    // ── Scenario H: existing quote is never overwritten ──────────────────────

    public function test_scenario_h_existing_quote_blocks_conversion(): void
    {
        $this->seedSingleSplitSet();
        $this->seedAccessories();
        $request = $this->makeAircoRequest();
        $this->calculate($request);
        $recommendation = HvacRecommendation::firstOrFail();
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.approve', [$request, $recommendation]));

        \App\Models\Quote::create([
            'customer_request_id' => $request->id, 'quote_number' => 'Q-2026-999',
            'quote_status' => 'draft', 'amount_excl_vat' => 1, 'amount_vat' => 0.21, 'amount_incl_vat' => 1.21,
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.convert', [$request, $recommendation->fresh()]));

        $this->assertDatabaseCount('quotes', 1);
        $this->assertSame('Q-2026-999', \App\Models\Quote::firstOrFail()->quote_number);
        $this->assertSame('approved', $recommendation->fresh()->status);
    }

    // ── Audit regressions ────────────────────────────────────────────────────

    public function test_post_approval_mutation_is_blocked(): void
    {
        $this->seedSingleSplitSet();
        $this->seedAccessories();
        $request = $this->makeAircoRequest();
        $this->calculate($request);
        $recommendation = HvacRecommendation::firstOrFail();
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.approve', [$request, $recommendation]));

        $item = $recommendation->items()->where('item_type', 'equipment')->firstOrFail();
        $originalPrice = $item->sale_unit_price;
        $originalVat = $recommendation->vat_rate;

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.items.override', [$request, $item]), [
                'sale_unit_price' => 1, 'reason' => 'poging na goedkeuring',
            ])->assertSessionHas('success', 'hvac_not_editable');

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.vat', [$request, $recommendation]), [
                'vat_rate' => 6, 'reason' => 'poging na goedkeuring',
            ])->assertSessionHas('success', 'hvac_not_editable');

        $this->assertEquals($originalPrice, $item->fresh()->sale_unit_price);
        $this->assertEquals($originalVat, $recommendation->fresh()->vat_rate);
    }

    public function test_stale_approval_of_superseded_calculation_cannot_convert(): void
    {
        $this->seedSingleSplitSet();
        $this->seedAccessories();
        $request = $this->makeAircoRequest();
        $this->calculate($request);
        $recommendation = HvacRecommendation::firstOrFail();
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.approve', [$request, $recommendation]));

        // Recalculate: the approved recommendation's calculation is superseded.
        $this->calculate($request);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.convert', [$request, $recommendation->fresh()]));

        $this->assertDatabaseCount('quotes', 0);
        $this->assertSame('approved', $recommendation->fresh()->status, 'not converted');
    }

    public function test_candidate_with_one_priceless_product_is_never_valid(): void
    {
        // Audit: order-dependent $missing reset. Indoor with NO prices at all,
        // outdoor with only a purchase price.
        $this->seedAccessories();
        $indoor = $this->makeProduct([
            'sku' => 'TB-IN-X', 'model' => 'TB Indoor X', 'name' => 'TB binnenunit X',
            'product_type' => 'indoor_unit', 'cooling_capacity_kw' => 3.5,
        ]);
        $outdoor = $this->makeProduct([
            'sku' => 'TB-OUT-X', 'model' => 'TB Outdoor X', 'name' => 'TB buitenunit X',
            'product_type' => 'outdoor_unit', 'cooling_capacity_kw' => 3.5,
            'maximum_pipe_length_m' => 20, 'maximum_height_difference_m' => 10,
            'purchase_price_excl_vat' => 900,
        ]);
        HvacProductCompatibility::create([
            'parent_product_id' => $outdoor->id, 'compatible_product_id' => $indoor->id,
            'compatibility_type' => 'indoor_outdoor', 'is_active' => true,
        ]);

        $request = $this->makeAircoRequest();
        $this->calculate($request);

        $recommendation = HvacRecommendation::first();
        $this->assertTrue(
            $recommendation === null || $recommendation->status === 'manual_review',
            'a candidate with an unpriced product may never be auto-valid'
        );
    }

    public function test_inactive_compatibility_rows_are_ignored(): void
    {
        $this->seedAccessories();
        $indoor = $this->makeProduct([
            'sku' => 'TB-IN-Y', 'model' => 'TB Indoor Y', 'name' => 'TB binnenunit Y',
            'product_type' => 'indoor_unit', 'cooling_capacity_kw' => 3.5,
            'default_sale_price_excl_vat' => 700, 'purchase_price_excl_vat' => 450,
        ]);
        $outdoor = $this->makeProduct([
            'sku' => 'TB-OUT-Y', 'model' => 'TB Outdoor Y', 'name' => 'TB buitenunit Y',
            'product_type' => 'outdoor_unit', 'cooling_capacity_kw' => 3.5,
            'maximum_pipe_length_m' => 20, 'maximum_height_difference_m' => 10,
            'default_sale_price_excl_vat' => 1200, 'purchase_price_excl_vat' => 800,
        ]);
        HvacProductCompatibility::create([
            'parent_product_id' => $outdoor->id, 'compatible_product_id' => $indoor->id,
            'compatibility_type' => 'indoor_outdoor', 'is_active' => false,
        ]);

        $request = $this->makeAircoRequest();
        $this->calculate($request);

        $recommendation = HvacRecommendation::first();
        $this->assertTrue(
            $recommendation === null || $recommendation->status === 'manual_review',
            'an inactive compatibility row must never produce an auto-valid pair'
        );
    }

    public function test_needs_review_and_three_phase_products_warn_in_the_panel(): void
    {
        $this->seedAccessories();
        $this->seedSingleSplitSet([
            'supported_voltage' => '400V tri',
            'metadata' => ['import' => ['needs_review' => true]],
        ]);
        $request = $this->makeAircoRequest();
        $this->calculate($request);

        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('nog te controleren')
            ->assertSee('controleer de elektrische voeding');
    }

    public function test_negative_margin_is_flagged_in_red_not_green(): void
    {
        $this->seedSingleSplitSet();
        $this->seedAccessories();
        $request = $this->makeAircoRequest();
        $this->calculate($request);
        $recommendation = HvacRecommendation::firstOrFail();
        $item = $recommendation->items()->where('item_type', 'equipment')->firstOrFail();

        // Sell far below purchase (1000) — margin goes negative.
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.items.override', [$request, $item]), [
                'sale_unit_price' => 100, 'reason' => 'Test negatieve marge scenario.',
            ]);

        $this->assertLessThan(0, $recommendation->fresh()->margin_amount);
        $this->withSession($this->adminSession())
            ->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('NEGATIEF');
    }

    public function test_quote_editor_rejects_absurd_vat_and_quantities(): void
    {
        $request = $this->makeAircoRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.store', $request), [
                'items' => [['description' => 'X', 'quantity' => 1000000000, 'unit_price_excl_vat' => 99999999, 'vat_rate' => 9999]],
            ])->assertSessionHasErrors(['items.0.quantity', 'items.0.unit_price_excl_vat', 'items.0.vat_rate']);
    }

    public function test_quote_pdf_follows_customer_locale(): void
    {
        $request = $this->makeAircoRequest([], ['locale' => 'fr']);
        $quote = \App\Models\Quote::create([
            'customer_request_id' => $request->id, 'quote_number' => 'Q-2026-777',
            'quote_status' => 'draft', 'amount_excl_vat' => 100, 'amount_vat' => 21, 'amount_incl_vat' => 121,
        ]);

        $html = view('admin.quotes.pdf', [
            'quote' => $quote->fresh(), 'customerRequest' => $request,
        ])->render();

        $this->assertStringContainsString('Devis', $html);
        $this->assertStringContainsString('Sous-total HTVA', $html);
        $this->assertStringNotContainsString('Offerteregels', $html);
        $this->assertStringNotContainsString('purchase', mb_strtolower($html));
    }

    public function test_supplier_identity_is_case_insensitive_on_import(): void
    {
        $importer = new \App\Services\Hvac\HvacCsvImporter();
        $row = fn (string $supplier, string $sku) => [
            'line' => 2, 'action' => 'create', 'errors' => [],
            'data' => [
                'supplier' => $supplier, 'brand' => 'TestBrand', 'sku' => $sku, 'model' => $sku,
                'name' => 'Product', 'product_type' => 'installation_accessory',
                'cooling_capacity_kw' => null, 'heating_capacity_kw' => null, 'minimum_capacity_kw' => null,
                'maximum_capacity_kw' => null, 'purchase_price_excl_vat' => null, 'sale_price_excl_vat' => null,
                'stock_quantity' => null, 'lead_time_days' => null, 'breaker_a' => null,
                'max_pipe_length_m' => null, 'max_pipe_length_per_unit_m' => null, 'max_height_difference_m' => null,
                'max_connected_indoor_units' => null, 'sound_level_db' => null, 'seer' => null, 'scop' => null,
                'wifi_included' => null, 'active' => null, 'voltage' => null, 'phase' => null, 'cable' => null,
                'liquid_pipe_diameter' => null, 'gas_pipe_diameter' => null, 'notes' => null,
            ],
        ];

        $importer->import([$row('Airco NV', 'S-1')], 'create_and_update');
        (new \App\Services\Hvac\HvacCsvImporter())->import([$row('AIRCO NV', 'S-2')], 'create_and_update');

        $this->assertSame(1, HvacSupplier::count(), 'case variants must resolve to one supplier');
    }
}
