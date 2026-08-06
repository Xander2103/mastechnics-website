<?php

namespace Tests\Feature\Hvac;

use App\Models\CustomerRequest;
use App\Models\HvacBrand;
use App\Models\HvacProduct;
use App\Models\HvacRecommendation;
use App\Models\HvacRuleSet;
use App\Services\Hvac\HvacCompatibilityCsvImporter;
use App\Services\Hvac\HvacCsvImporter;
use App\Services\Hvac\HvacRuleCatalog;
use App\Services\Hvac\HvacRuleSetResolver;
use Database\Seeders\HvacDemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class HvacProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com'];
    }

    private function makeAircoRequest(): CustomerRequest
    {
        return CustomerRequest::create([
            'locale' => 'nl', 'service_slug' => 'airco', 'request_type' => 'installation',
            'service_category' => 'airco_offerte',
            'customer_name' => 'Test', 'customer_email' => 't@e.com',
            'description' => '', 'status' => 'new',
            'metadata' => ['answers' => [
                'rooms' => [[
                    'type' => 'slaapkamer', 'width' => 4, 'length' => 5, 'height' => 2.5,
                    'roof_type' => 'none', 'windows' => 'large', 'orientation' => 'south',
                ]],
                'insulation_level' => 'good',
                'customer_type'    => 'residential',
            ]],
        ]);
    }

    private function seedRealishCatalog(): void
    {
        $brand = HvacBrand::create(['name' => 'RealBrand', 'slug' => 'realbrand']);
        HvacProduct::create([
            'hvac_brand_id' => $brand->id, 'sku' => 'RB-SET-35', 'model' => 'RealBrand Set 35',
            'name' => 'RealBrand single split 3.5 kW', 'product_type' => 'single_split_set',
            'cooling_capacity_kw' => 3.5, 'maximum_pipe_length_m' => 20, 'maximum_height_difference_m' => 10,
            'default_sale_price_excl_vat' => 1500, 'purchase_price_excl_vat' => 1000,
            'stock_quantity' => 5, 'is_active' => true,
        ]);
        foreach (['wall_bracket', 'vibration_damper', 'pipe', 'trunking', 'electrical_accessory', 'drain_hose', 'condensate_pump', 'refrigerant'] as $i => $type) {
            HvacProduct::create([
                'hvac_brand_id' => $brand->id, 'sku' => "RB-ACC-{$i}", 'model' => "RealBrand accessoire {$type}",
                'name' => "RealBrand accessoire {$type}", 'product_type' => $type,
                'default_sale_price_excl_vat' => 10, 'purchase_price_excl_vat' => 5, 'is_active' => true,
            ]);
        }
    }

    private function validateAllCriticalRules(): void
    {
        $ruleSet = (new HvacRuleSetResolver())->active();
        foreach (HvacRuleCatalog::criticalKeys() as $key) {
            $ruleSet->validations()->create([
                'rule_key'     => $key,
                'status'       => 'validated',
                'validated_by' => 'martin@test.com',
                'validated_at' => now(),
            ]);
        }
    }

    // ── Templates ─────────────────────────────────────────────────────────────

    public function test_product_template_contains_all_production_columns(): void
    {
        $response = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.template'));

        $response->assertOk();
        $header = strtok($response->getContent(), "\r\n");
        foreach (['seer', 'scop', 'wifi_included', 'active', 'notes', 'max_connected_indoor_units'] as $column) {
            $this->assertStringContainsString($column, $header);
        }
        $this->assertStringContainsString('TEST', $response->getContent());
    }

    public function test_compatibility_template_is_downloadable(): void
    {
        $response = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.compat.template'));

        $response->assertOk();
        $this->assertStringContainsString('parent_sku;compatible_sku;compatibility_type', $response->getContent());
    }

    // ── Compatibility import ──────────────────────────────────────────────────

    public function test_compatibility_import_flow_creates_rules(): void
    {
        $this->seed(HvacDemoCatalogSeeder::class);
        \App\Models\HvacProductCompatibility::query()->delete();

        $csv = "parent_sku;compatible_sku;compatibility_type;maximum_units\r\nTEST-MULTI-50;TEST-BIN-25;multi_split_indoor;3\r\n";
        $file = UploadedFile::fake()->createWithContent('compat.csv', $csv);

        $preview = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.compat.preview'), ['file' => $file]);
        $preview->assertOk();
        $this->assertDatabaseCount('hvac_product_compatibilities', 0);

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.compat.confirm'), ['token' => (string) $preview->viewData('token')])
            ->assertRedirect(route('admin.hvac.import.index'));

        $this->assertDatabaseHas('hvac_product_compatibilities', [
            'compatibility_type' => 'multi_split_indoor',
            'maximum_units'      => 3,
        ]);
    }

    public function test_compatibility_import_rejects_unknown_self_and_duplicate_rows(): void
    {
        $this->seed(HvacDemoCatalogSeeder::class);
        $importer = new HvacCompatibilityCsvImporter();

        $csv = "parent_sku;compatible_sku;compatibility_type;maximum_units\r\n"
            . "ONBEKEND-1;TEST-BIN-25;multi_split_indoor;\r\n"          // unknown SKU
            . "TEST-MULTI-50;TEST-MULTI-50;multi_split_indoor;\r\n"     // self-compat
            . "TEST-MULTI-50;TEST-BIN-25;multi_split_indoor;99\r\n"     // invalid max units
            . "TEST-MULTI-50;TEST-BIN-25;raketwetenschap;\r\n"          // unknown type
            . "TEST-MULTI-50;TEST-BIN-25;multi_split_indoor;3\r\n"
            . "TEST-MULTI-50;TEST-BIN-25;multi_split_indoor;3\r\n";     // duplicate row

        $parsed = $importer->parse($csv);

        $this->assertNotEmpty($parsed['rows'][0]['errors']);
        $this->assertNotEmpty($parsed['rows'][1]['errors']);
        $this->assertNotEmpty($parsed['rows'][2]['errors']);
        $this->assertNotEmpty($parsed['rows'][3]['errors']);
        $this->assertSame([], $parsed['rows'][4]['errors']);
        $this->assertNotEmpty($parsed['rows'][5]['errors']); // duplicate of row 5
    }

    // ── Strengthened product import validation ────────────────────────────────

    public function test_product_import_rejects_invalid_boolean_missing_capacity_and_odd_voltage(): void
    {
        $importer = new HvacCsvImporter();
        $header = 'supplier;brand;sku;model;name;product_type;cooling_capacity_kw;voltage;wifi_included';

        $parsed = $importer->parse(implode("\r\n", [
            $header,
            'Lev;Merk;S-1;M;N;single_split_set;;230V mono;ja',      // missing capacity for a set
            'Lev;Merk;S-2;M;N;single_split_set;2.5;banaan;ja',       // unknown voltage
            'Lev;Merk;S-3;M;N;single_split_set;2.5;230V mono;misschien', // invalid boolean
            'Lev;Merk;S-4;M;N;single_split_set;2.5;230V mono;ja',    // valid
        ]) . "\r\n");

        $this->assertNotEmpty($parsed['rows'][0]['errors']);
        $this->assertNotEmpty($parsed['rows'][1]['errors']);
        $this->assertNotEmpty($parsed['rows'][2]['errors']);
        $this->assertSame([], $parsed['rows'][3]['errors']);
    }

    // ── Rule validation workflow ──────────────────────────────────────────────

    public function test_rules_page_lists_rules_and_supports_validation(): void
    {
        $response = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.rules.index'));

        $response->assertOk();
        $response->assertSee('HVAC-berekeningsregels');
        $response->assertSee('Basislast isolatie: goed');
        $response->assertSee('KRITIEK');
        $response->assertSee('Placeholder');

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.rules.validate'), [
                'rule_key' => 'insulation_w_per_m2.good',
                'note'     => 'Bevestigd met installateur',
            ])->assertSessionHas('success', 'hvac_rule_validated');

        $this->assertDatabaseHas('hvac_rule_validations', [
            'rule_key'     => 'insulation_w_per_m2.good',
            'validated_by' => 'admin@test.com',
            'note'         => 'Bevestigd met installateur',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.rules.validate'), ['rule_key' => 'niet_bestaand'])
            ->assertSessionHasErrors('rule_key');
    }

    public function test_draft_rule_set_creation_and_confirmed_activation(): void
    {
        (new HvacRuleSetResolver())->active();

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.rules.draft'))
            ->assertSessionHas('success', 'hvac_rule_draft_created');

        $draft = HvacRuleSet::where('status', 'draft')->firstOrFail();
        $this->assertSame(2, (int) $draft->version);

        // Activation without confirmation is refused.
        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.rules.activate', $draft))
            ->assertSessionHasErrors('confirm');
        $this->assertSame('draft', $draft->fresh()->status);

        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.rules.activate', $draft), ['confirm' => '1'])
            ->assertSessionHas('success', 'hvac_rule_set_activated');

        $this->assertSame('active', $draft->fresh()->status);
        $this->assertSame('archived', HvacRuleSet::where('version', 1)->first()->status);
    }

    public function test_rule_set_activation_does_not_alter_historical_calculations(): void
    {
        $this->seed(HvacDemoCatalogSeeder::class);
        $request = $this->makeAircoRequest();
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.calculate', $request));

        $calculation = $request->hvacCalculations()->first();
        $originalWatts = $calculation->result['rooms'][0]['load']['final_watts'];

        $this->withSession($this->adminSession())->post(route('admin.hvac.rules.draft'));
        $draft = HvacRuleSet::where('status', 'draft')->first();
        $this->withSession($this->adminSession())
            ->post(route('admin.hvac.rules.activate', $draft), ['confirm' => '1']);

        $this->assertSame($originalWatts, $calculation->fresh()->result['rooms'][0]['load']['final_watts']);
        $this->assertSame(1, (int) $calculation->fresh()->result['rule_set']['version']);
    }

    // ── Approval gating ───────────────────────────────────────────────────────

    public function test_real_products_cannot_be_approved_until_critical_rules_validated(): void
    {
        $this->seedRealishCatalog();
        $request = $this->makeAircoRequest();
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.calculate', $request));

        $recommendation = HvacRecommendation::whereNotIn('status', ['superseded'])->firstOrFail();
        $this->assertSame('draft', $recommendation->status);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.approve', [$request, $recommendation]))
            ->assertSessionHasErrors('hvac_approve');
        $this->assertSame('draft', $recommendation->fresh()->status);

        $this->validateAllCriticalRules();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.approve', [$request, $recommendation]))
            ->assertSessionHas('success', 'hvac_recommendation_approved');
        $this->assertSame('approved', $recommendation->fresh()->status);
    }

    public function test_test_catalog_recommendations_bypass_only_the_rule_gate(): void
    {
        $this->seed(HvacDemoCatalogSeeder::class);
        $request = $this->makeAircoRequest();
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.calculate', $request));

        $recommendation = HvacRecommendation::whereNotIn('status', ['superseded'])
            ->where('option_type', 'recommended')->firstOrFail();

        // No rules validated, but the TEST catalog may rehearse the flow.
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.approve', [$request, $recommendation]))
            ->assertSessionHas('success', 'hvac_recommendation_approved');
    }

    public function test_conversion_stays_blocked_for_unapproved_real_recommendations(): void
    {
        $this->seedRealishCatalog();
        $request = $this->makeAircoRequest();
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.calculate', $request));
        $recommendation = HvacRecommendation::whereNotIn('status', ['superseded'])->firstOrFail();

        // Approval is blocked (rules), so status stays draft and conversion refuses.
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.convert', [$request, $recommendation]))
            ->assertSessionHasErrors('hvac_convert');
        $this->assertDatabaseCount('quotes', 0);
    }

    // ── Demo catalog ──────────────────────────────────────────────────────────

    public function test_demo_seeder_provides_three_options_and_multi_split_data(): void
    {
        $this->seed(HvacDemoCatalogSeeder::class);
        $request = $this->makeAircoRequest();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.calculate', $request));

        $types = HvacRecommendation::whereNotIn('status', ['superseded'])->pluck('option_type');
        $this->assertContains('recommended', $types);
        $this->assertContains('budget', $types);
        $this->assertContains('premium', $types);
    }

    public function test_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->expectException(\RuntimeException::class);
            (new HvacDemoCatalogSeeder())->run();
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_test_catalog_banner_is_visible(): void
    {
        $this->seed(HvacDemoCatalogSeeder::class);

        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.products.index'))
            ->assertOk()
            ->assertSee('Testcatalogus — niet gebruiken voor echte offertes');
    }

    // ── Quality dashboard ─────────────────────────────────────────────────────

    public function test_quality_dashboard_counts_and_filter(): void
    {
        $brand = HvacBrand::create(['name' => 'RealBrand', 'slug' => 'realbrand']);
        HvacProduct::create([
            'hvac_brand_id' => $brand->id, 'sku' => 'RB-1', 'model' => 'RealBrand compleet',
            'name' => 'RealBrand compleet', 'product_type' => 'single_split_set',
            'cooling_capacity_kw' => 3.5, 'maximum_pipe_length_m' => 20, 'maximum_height_difference_m' => 10,
            'default_sale_price_excl_vat' => 1500, 'stock_quantity' => 5, 'is_active' => true,
        ]);
        HvacProduct::create([
            'hvac_brand_id' => $brand->id, 'sku' => 'RB-2', 'model' => 'RealBrand zonder prijs',
            'name' => 'RealBrand zonder prijs', 'product_type' => 'indoor_unit',
            'cooling_capacity_kw' => 2.5, 'is_active' => true,
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.products.index'));
        $response->assertOk();
        $response->assertSee('Klaar voor aanbeveling');
        $response->assertSee('Zonder prijs');

        // Filter shows only the incomplete product.
        $filtered = $this->withSession($this->adminSession())
            ->get(route('admin.hvac.products.index', ['quality' => 'missing_price']));
        $filtered->assertOk();
        $filtered->assertSee('RB-2');
        $filtered->assertDontSee('RB-1</td>', false);
    }

    // ── Checklist page ────────────────────────────────────────────────────────

    public function test_checklist_page_is_linked_and_renders(): void
    {
        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.checklist'))
            ->assertOk()
            ->assertSee('Acceptatiechecklist')
            ->assertSee('Voer de voorcalculatie uit');

        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.products.index'))
            ->assertOk()
            ->assertSee('Checklist');
    }
}
