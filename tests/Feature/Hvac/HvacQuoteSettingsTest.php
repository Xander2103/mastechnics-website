<?php

namespace Tests\Feature\Hvac;

use App\Models\CustomerRequest;
use App\Models\HvacBrand;
use App\Models\HvacProduct;
use App\Models\HvacRecommendation;
use App\Models\HvacRuleChange;
use App\Models\HvacRuleSet;
use App\Models\Quote;
use App\Services\Hvac\HvacRecommendationReadiness;
use App\Services\Hvac\HvacRuleCatalog;
use App\Services\Hvac\HvacRuleSetResolver;
use App\Services\Hvac\HvacSettingsGuide;
use App\Services\Hvac\HvacSettingsOverview;
use Database\Seeders\HvacDemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Sprint 23 — "Offerte-instellingen": guided checklist, draft editing,
 * activation summary. The rule architecture and the approval gate underneath
 * must behave exactly as before.
 */
class HvacQuoteSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function active(): HvacRuleSet
    {
        return app(HvacRuleSetResolver::class)->active();
    }

    private function admin()
    {
        return $this->withSession($this->adminSession());
    }

    private function makeDraft(): HvacRuleSet
    {
        $this->active();
        $this->admin()->post(route('admin.hvac.rules.draft'));

        return HvacRuleSet::where('status', 'draft')->firstOrFail();
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

    private function seedRealLookingCatalog(): void
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

    // ── Authorization ────────────────────────────────────────────────────────

    public function test_every_settings_page_requires_an_admin_session(): void
    {
        $this->active();

        foreach ([
            route('admin.hvac.rules.index'),
            route('admin.hvac.rules.section', 'verkoopprijzen'),
            route('admin.hvac.rules.advanced'),
            route('admin.hvac.rules.example'),
            route('admin.hvac.rules.quick-estimate'),
        ] as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
        }
    }

    public function test_every_settings_action_requires_an_admin_session(): void
    {
        $draft = $this->makeDraft();
        $this->flushSession();

        $this->post(route('admin.hvac.rules.validate'), ['rule_key' => 'labor.hourly_rate_excl_vat', 'confirm' => '1'])
            ->assertRedirect(route('admin.login'));
        $this->patch(route('admin.hvac.rules.value', $draft), ['rule_key' => 'labor.hourly_rate_excl_vat', 'value' => '80'])
            ->assertRedirect(route('admin.login'));
        $this->post(route('admin.hvac.rules.activate', $draft), ['confirm' => '1'])
            ->assertRedirect(route('admin.login'));
        $this->post(route('admin.hvac.rules.discard', $draft))
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseCount('hvac_rule_validations', 0);
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertSame(65.0, (float) $draft->fresh()->configuration['labor']['hourly_rate_excl_vat']);
    }

    // ── Dashboard ────────────────────────────────────────────────────────────

    public function test_dashboard_explains_progress_in_plain_language(): void
    {
        $response = $this->admin()->get(route('admin.hvac.rules.index'));

        $response->assertOk();
        $response->assertSee('Offerte-instellingen');
        $response->assertSee('Hier stelt u in hoe Mastechnics aanvragen analyseert en offertes voorbereidt.');
        $response->assertSee('0 van 12');
        $response->assertSee('moet u nog 12 belangrijke instellingen controleren');
        foreach (['Koelvermogen', 'Toestellen', 'Installatie', 'Werkuren', 'Verkoopprijzen'] as $title) {
            $response->assertSee($title);
        }
        $response->assertSee('Bestaande offertes veranderen hierdoor niet.');
        $response->assertSee('Geavanceerde instellingen');

        // Unexplained technical vocabulary stays off Martin's main screen.
        foreach (['placeholder', 'Placeholder', 'rule key', 'snapshot', 'critical validation', 'KRITIEK'] as $jargon) {
            $response->assertDontSee($jargon);
        }
    }

    public function test_empty_catalog_makes_recommendations_unavailable(): void
    {
        $response = $this->admin()->get(route('admin.hvac.rules.index'));

        $response->assertSee('Niet beschikbaar');
        $response->assertSee('Productcatalogus is leeg');
    }

    public function test_next_action_moves_to_the_catalog_once_everything_is_confirmed(): void
    {
        $active = $this->active();
        foreach (HvacRuleCatalog::criticalKeys() as $key) {
            if (HvacRuleCatalog::value($active->configuration, $key) !== null) {
                $this->admin()->post(route('admin.hvac.rules.validate'), ['rule_key' => $key, 'confirm' => '1']);
            }
        }

        $response = $this->admin()->get(route('admin.hvac.rules.index'));
        $response->assertSee('12 van 12');
        $response->assertSee('Voeg nu uw productcatalogus toe');
    }

    // ── Guided checklist ─────────────────────────────────────────────────────

    public function test_hourly_rate_is_explained_with_a_worked_example(): void
    {
        $response = $this->admin()->get(route('admin.hvac.rules.section', 'verkoopprijzen'));

        $response->assertOk();
        $response->assertSee('Uurtarief installatie');
        $response->assertSee('€ 65,00 per uur, exclusief btw');
        $response->assertSee('Dit bedrag wordt gebruikt om de geraamde werkuren om te zetten naar arbeidskosten in een offerte.');
        $response->assertSee('6 uur × € 65,00 = € 390,00 exclusief btw.');
        $response->assertSee('Gevolg van een verkeerde instelling');
        $response->assertSee('Ik bevestig dat € 65,00 per uur (excl. btw) het juiste tarief is.');
        $response->assertSee('Nog instellen');
    }

    public function test_markup_is_never_presented_as_gross_margin(): void
    {
        $response = $this->admin()->get(route('admin.hvac.rules.section', 'verkoopprijzen'));

        $response->assertSee('Opslag op toestellen zonder verkoopprijs');
        $response->assertSee('verkoopprijs € 1.350,00');
        $response->assertSee('25,9%');
        $response->assertSee('Een opslag is geen marge');
    }

    public function test_every_critical_rule_has_a_specific_guide(): void
    {
        foreach (['default_rule_set', 'cooling_load_v2_rule_set'] as $set) {
            $config = config("hvac.{$set}.configuration");
            foreach (HvacRuleCatalog::entries() as $entry) {
                $value = HvacRuleCatalog::value($config, $entry['key']);
                if (! $entry['critical'] || $value === null) {
                    continue;
                }

                $guide = HvacSettingsGuide::guide($entry, $value, $config);
                $this->assertNotSame($entry['explanation'], $guide['meaning'], "{$entry['key']} has no plain-language meaning");
                $this->assertNotEmpty($guide['example'], "{$entry['key']} has no worked example");
                $this->assertNotEmpty($guide['consequence']);
            }
        }
    }

    public function test_a_numerically_valid_value_is_never_approved_automatically(): void
    {
        $active = $this->active();

        $entries = app(HvacSettingsOverview::class)->entries($active);
        $this->assertTrue($entries->every(fn ($e) => $e['status'] !== 'validated'));
        $this->assertFalse(app(HvacRecommendationReadiness::class)->criticalRulesValidated($active->id));

        // Viewing pages confirms nothing.
        $this->admin()->get(route('admin.hvac.rules.index'));
        $this->admin()->get(route('admin.hvac.rules.section', 'verkoopprijzen'));
        $this->admin()->get(route('admin.hvac.rules.example'));
        $this->assertDatabaseCount('hvac_rule_validations', 0);
    }

    public function test_confirmation_records_who_when_value_and_version(): void
    {
        $active = $this->active();

        $this->admin()->post(route('admin.hvac.rules.validate'), [
            'rule_key' => 'labor.hourly_rate_excl_vat',
            'confirm'  => '1',
            'rule_set' => $active->id,
        ])->assertSessionHas('success', 'hvac_rule_validated');

        $validation = $active->validations()->where('rule_key', 'labor.hourly_rate_excl_vat')->firstOrFail();
        $this->assertSame('admin@test.com', $validation->validated_by);
        $this->assertNotNull($validation->validated_at);
        $this->assertSame('65', $validation->validated_value);
        $this->assertSame($active->id, (int) $validation->hvac_rule_set_id);

        $this->admin()->get(route('admin.hvac.rules.section', 'verkoopprijzen'))
            ->assertSee('Bevestigd door admin@test.com')
            ->assertSee('Goedgekeurd');
    }

    public function test_confirmation_cannot_target_an_archived_version(): void
    {
        $draft = $this->makeDraft();
        $draft->update(['status' => 'archived']);

        $this->admin()->post(route('admin.hvac.rules.validate'), [
            'rule_key' => 'labor.hourly_rate_excl_vat', 'confirm' => '1', 'rule_set' => $draft->id,
        ])->assertNotFound();

        $this->assertDatabaseCount('hvac_rule_validations', 0);
    }

    // ── Concept: editing values ──────────────────────────────────────────────

    public function test_values_cannot_be_changed_on_the_active_settings(): void
    {
        $active = $this->active();

        $this->admin()->patch(route('admin.hvac.rules.value', $active), [
            'rule_key' => 'labor.hourly_rate_excl_vat', 'value' => '80',
        ])->assertSessionHasErrors('value');

        $this->assertSame(65.0, (float) $active->fresh()->configuration['labor']['hourly_rate_excl_vat']);
        $this->assertDatabaseCount('hvac_rule_changes', 0);
    }

    public function test_value_change_in_a_concept_is_audited_and_leaves_active_settings_alone(): void
    {
        $active = $this->active();
        $draft = $this->makeDraft();

        $this->admin()->patch(route('admin.hvac.rules.value', $draft), [
            'rule_key' => 'labor.hourly_rate_excl_vat', 'value' => '72,50',
        ])->assertSessionHas('success', 'hvac_rule_value_updated');

        $this->assertSame(72.5, (float) $draft->fresh()->configuration['labor']['hourly_rate_excl_vat']);
        $this->assertSame(65.0, (float) $active->fresh()->configuration['labor']['hourly_rate_excl_vat']);

        $change = HvacRuleChange::firstOrFail();
        $this->assertSame('labor.hourly_rate_excl_vat', $change->rule_key);
        $this->assertSame('65', $change->old_value);
        $this->assertSame('72.5', $change->new_value);
        $this->assertSame('admin@test.com', $change->changed_by);
        $this->assertSame($draft->id, (int) $change->hvac_rule_set_id);
    }

    public function test_out_of_range_text_and_table_values_are_refused(): void
    {
        $draft = $this->makeDraft();

        foreach ([
            ['labor.hourly_rate_excl_vat', '0'],        // below the minimum
            ['labor.hourly_rate_excl_vat', '9999'],     // above the maximum
            ['labor.hourly_rate_excl_vat', 'veel'],     // not a number
            ['labor.hourly_rate_excl_vat', '-65'],      // negative
            ['pricing.default_vat_rate', '19'],         // not an allowed VAT rate
            ['capacity_classes', '3'],                  // a table
            ['max_class_kw', '9'],                      // linked value
            ['labor.second_technician_from_indoor_units', '2,5'], // must be whole
        ] as [$key, $value]) {
            $this->admin()->patch(route('admin.hvac.rules.value', $draft), ['rule_key' => $key, 'value' => $value])
                ->assertSessionHasErrors('value');
        }

        $this->assertEquals($this->active()->configuration, $draft->fresh()->configuration);
        $this->assertDatabaseCount('hvac_rule_changes', 0);
    }

    public function test_changed_value_must_be_confirmed_again_and_can_be_confirmed_inside_the_concept(): void
    {
        $active = $this->active();
        $this->admin()->post(route('admin.hvac.rules.validate'), ['rule_key' => 'labor.hourly_rate_excl_vat', 'confirm' => '1']);
        $draft = $this->makeDraft();

        $overview = app(HvacSettingsOverview::class);
        $this->assertSame('validated', $overview->entries($draft)->firstWhere('key', 'labor.hourly_rate_excl_vat')['status']);

        $this->admin()->patch(route('admin.hvac.rules.value', $draft), ['rule_key' => 'labor.hourly_rate_excl_vat', 'value' => '80']);

        $entry = $overview->entries($draft->fresh())->firstWhere('key', 'labor.hourly_rate_excl_vat');
        $this->assertNotSame('validated', $entry['status']);
        $this->assertTrue($entry['value_changed']);
        $this->assertSame('Controleren', $entry['friendly']['label']);

        $this->admin()->post(route('admin.hvac.rules.validate'), [
            'rule_key' => 'labor.hourly_rate_excl_vat', 'confirm' => '1', 'rule_set' => $draft->id,
        ])->assertSessionHas('success', 'hvac_rule_validated');

        $this->assertSame('validated', $overview->entries($draft->fresh())->firstWhere('key', 'labor.hourly_rate_excl_vat')['status']);
        // The confirmation on the active version still belongs to the old value.
        $this->assertSame('65', $active->validations()->where('rule_key', 'labor.hourly_rate_excl_vat')->first()->validated_value);
    }

    public function test_only_one_concept_per_settings_family(): void
    {
        $draft = $this->makeDraft();

        $this->admin()->post(route('admin.hvac.rules.draft'), ['section' => 'werkuren'])
            ->assertRedirect(route('admin.hvac.rules.section', ['section' => 'werkuren', 'concept' => $draft->id]))
            ->assertSessionHas('success', 'hvac_rule_draft_exists');

        $this->assertSame(1, HvacRuleSet::where('status', 'draft')->count());
    }

    public function test_concept_can_be_cancelled_without_deleting_anything(): void
    {
        $draft = $this->makeDraft();

        $this->admin()->post(route('admin.hvac.rules.discard', $draft))
            ->assertSessionHas('success', 'hvac_rule_draft_discarded');

        $this->assertSame('archived', $draft->fresh()->status);
        $this->assertSame('active', $this->active()->status);
        $this->assertSame(1, (int) $this->active()->version);
    }

    // ── Activation ───────────────────────────────────────────────────────────

    public function test_activation_summary_lists_changes_missing_confirmations_and_consequences(): void
    {
        $draft = $this->makeDraft();
        $this->admin()->patch(route('admin.hvac.rules.value', $draft), ['rule_key' => 'labor.hourly_rate_excl_vat', 'value' => '80']);

        $response = $this->admin()->get(route('admin.hvac.rules.index'));

        $response->assertSee('Concept — versie 2');
        $response->assertSee('Wat verandert er?');
        $response->assertSee('Uurtarief installatie');
        $response->assertSee('€ 65,00 per uur, exclusief btw');
        $response->assertSee('€ 80,00 per uur, exclusief btw');
        $response->assertSee('Nog te controleren');
        $response->assertSee('Gevolgen van activeren');
        $response->assertSee('Bestaande berekeningen en offertes bewaren hun eigen instellingen en veranderen niet.');
        $response->assertSee('Concept activeren');
    }

    public function test_nothing_is_ever_activated_automatically(): void
    {
        $draft = $this->makeDraft();
        $this->admin()->patch(route('admin.hvac.rules.value', $draft), ['rule_key' => 'labor.hourly_rate_excl_vat', 'value' => '80']);
        foreach (HvacRuleCatalog::criticalKeys() as $key) {
            if (HvacRuleCatalog::value($draft->configuration, $key) !== null) {
                $this->admin()->post(route('admin.hvac.rules.validate'), ['rule_key' => $key, 'confirm' => '1', 'rule_set' => $draft->id]);
            }
        }
        $this->admin()->get(route('admin.hvac.rules.index'))->assertSee('Klaar om te activeren');

        // Fully confirmed and viewed — still a concept until explicitly activated.
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertSame(1, (int) $this->active()->version);

        $this->admin()->post(route('admin.hvac.rules.activate', $draft))->assertSessionHasErrors('confirm');
        $this->assertSame('draft', $draft->fresh()->status);

        $this->admin()->post(route('admin.hvac.rules.activate', $draft), ['confirm' => '1'])
            ->assertSessionHas('success', 'hvac_rule_set_activated');
        $this->assertSame(2, (int) $this->active()->version);
    }

    public function test_new_calculations_use_the_new_version_and_history_keeps_its_own(): void
    {
        Mail::fake();
        $this->seed(HvacDemoCatalogSeeder::class);
        $request = $this->makeAircoRequest();

        $this->admin()->post(route('admin.requests.hvac.calculate', $request));
        $old = $request->hvacCalculations()->firstOrFail();
        $oldResult = $old->result;
        $oldLabor = HvacRecommendation::where('hvac_calculation_id', $old->id)->firstOrFail()->labor_total_excl_vat;

        $draft = $this->makeDraft();
        $this->admin()->patch(route('admin.hvac.rules.value', $draft), ['rule_key' => 'labor.hourly_rate_excl_vat', 'value' => '130']);
        $this->admin()->post(route('admin.hvac.rules.activate', $draft), ['confirm' => '1']);

        // History: untouched, still version 1 with the € 65 snapshot.
        $this->assertEquals($oldResult, $old->fresh()->result);
        $this->assertSame(1, (int) $old->fresh()->result['rule_set']['version']);
        $this->assertSame(65.0, (float) $old->fresh()->result['rule_set']['configuration']['labor']['hourly_rate_excl_vat']);
        $this->assertEquals($oldLabor, HvacRecommendation::where('hvac_calculation_id', $old->id)->firstOrFail()->labor_total_excl_vat);

        // A new calculation records and uses version 2.
        $this->admin()->post(route('admin.requests.hvac.calculate', $request));
        $new = $request->hvacCalculations()->where('status', 'calculated')->latest('id')->firstOrFail();
        $this->assertSame(2, (int) $new->result['rule_set']['version']);
        $this->assertSame($draft->id, (int) $new->hvac_rule_set_id);
        $newLabor = HvacRecommendation::where('hvac_calculation_id', $new->id)->firstOrFail()->labor_total_excl_vat;
        $this->assertEquals(round((float) $oldLabor * 2, 2), round((float) $newLabor, 2));

        Mail::assertNothingSent();
    }

    public function test_existing_quote_is_untouched_by_a_settings_change(): void
    {
        $request = $this->makeAircoRequest();
        $quote = Quote::create([
            'customer_request_id' => $request->id, 'quote_status' => 'draft', 'title' => 'Bestaande offerte',
            'subtotal_excl_vat' => 1000, 'vat_amount' => 210, 'total_incl_vat' => 1210,
        ]);
        $before = $quote->fresh()->getAttributes();

        $draft = $this->makeDraft();
        $this->admin()->patch(route('admin.hvac.rules.value', $draft), ['rule_key' => 'labor.hourly_rate_excl_vat', 'value' => '130']);
        $this->admin()->patch(route('admin.hvac.rules.value', $draft), ['rule_key' => 'pricing.default_vat_rate', 'value' => '6']);
        $this->admin()->post(route('admin.hvac.rules.activate', $draft), ['confirm' => '1']);

        $this->assertSame($before, $quote->fresh()->getAttributes());
    }

    // ── The approval gate is unchanged ───────────────────────────────────────

    public function test_critical_confirmation_stays_mandatory_for_real_products(): void
    {
        Mail::fake();
        $this->seedRealLookingCatalog();
        $request = $this->makeAircoRequest();
        $this->admin()->post(route('admin.requests.hvac.calculate', $request));
        $recommendation = HvacRecommendation::whereNotIn('status', ['superseded'])->firstOrFail();

        $this->admin()->post(route('admin.requests.hvac.approve', [$request, $recommendation]))
            ->assertSessionHasErrors('hvac_approve');
        $this->assertSame('draft', $recommendation->fresh()->status);

        // Confirming the (non-critical) quick-estimate values changes nothing.
        foreach (array_keys(config('hvac.default_rule_set.configuration.quick_estimate.w_per_m3')) as $situation) {
            $this->admin()->post(route('admin.hvac.rules.validate'), [
                'rule_key' => "quick_estimate.w_per_m3.{$situation}", 'confirm' => '1',
            ])->assertSessionHas('success', 'hvac_rule_validated');
        }
        $this->admin()->post(route('admin.requests.hvac.approve', [$request, $recommendation]))
            ->assertSessionHasErrors('hvac_approve');

        $this->assertDatabaseCount('quotes', 0);
        Mail::assertNothingSent();
    }

    public function test_quick_estimate_values_are_not_critical(): void
    {
        $quickKeys = array_filter(HvacRuleCatalog::keys(), fn ($key) => str_starts_with($key, 'quick_estimate.'));

        $this->assertCount(4, $quickKeys);
        $this->assertSame([], array_intersect($quickKeys, HvacRuleCatalog::criticalKeys()));
    }

    // ── Example page ─────────────────────────────────────────────────────────

    public function test_example_is_marked_fictitious_and_writes_nothing(): void
    {
        Mail::fake();
        $request = $this->makeAircoRequest();
        $tables = ['hvac_calculations', 'hvac_recommendations', 'hvac_recommendation_items', 'quotes', 'hvac_products', 'hvac_rule_validations', 'hvac_rule_changes', 'hvac_manual_overrides'];
        $this->active();
        $before = array_map(fn ($t) => \DB::table($t)->count(), $tables);
        $requestBefore = $request->fresh()->getAttributes();

        $response = $this->admin()->get(route('admin.hvac.rules.example'));

        $response->assertOk();
        $response->assertSee('Demonstratie — fictieve gegevens');
        $response->assertSee('50 m³ × 30 W/m³ = 1.500 W');
        $response->assertSee('1,5 kW');
        $response->assertSee('Technische controle');
        $response->assertSee('Offerte goedkeuren');
        $response->assertSee('(fictief voorbeeld)');

        $this->assertSame($before, array_map(fn ($t) => \DB::table($t)->count(), $tables));
        $this->assertSame($requestBefore, $request->fresh()->getAttributes());
        $this->assertSame(1, HvacRuleSet::count());
        Mail::assertNothingSent();
    }

    public function test_no_fictitious_or_test_products_reach_the_catalog(): void
    {
        // A freshly migrated database (what production gets): no migration of
        // this sprint may seed a product, and the demo pages must not either.
        $this->assertSame(0, HvacProduct::count());
        $this->active();
        $this->admin()->get(route('admin.hvac.rules.example'));
        $this->admin()->get(route('admin.hvac.rules.section', 'toestellen'));

        $this->assertSame(0, HvacProduct::count());
    }

    public function test_demo_catalog_seeder_still_refuses_production(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->expectException(\RuntimeException::class);
            (new HvacDemoCatalogSeeder())->run();
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    // ── Sections ─────────────────────────────────────────────────────────────

    public function test_toestellen_shows_real_catalog_counts_per_class(): void
    {
        $this->seedRealLookingCatalog();

        $catalog = app(HvacSettingsOverview::class)->catalog($this->active()->configuration);

        $byClass = collect($catalog['classes'])->keyBy(fn ($row) => (string) $row['class_kw']);
        $this->assertSame(1, $byClass['3.5']['products']); // 3.5 kW set serves loads in (2.2, 3.2]
        $this->assertSame(0, $byClass['2.5']['products']); // 3.5 kW is above 2.5 × 1.30 = 3.25
        $this->assertSame(1, $catalog['cooling_products']);

        $this->admin()->get(route('admin.hvac.rules.section', 'toestellen'))
            ->assertOk()
            ->assertSee('Onbekende compatibiliteit is géén bevestigde compatibiliteit')
            ->assertSee('Wanneer kiest u zelf een toestel?');
    }

    public function test_all_sections_render_and_unknown_section_is_404(): void
    {
        foreach (array_keys(HvacSettingsGuide::SECTIONS) as $section) {
            $this->admin()->get(route('admin.hvac.rules.section', $section))->assertOk();
        }

        $this->admin()->get(route('admin.hvac.rules.section', 'bestaat-niet'))->assertNotFound();
    }

    public function test_concept_view_offers_editing_and_active_view_does_not(): void
    {
        $this->active();
        $this->admin()->get(route('admin.hvac.rules.section', 'verkoopprijzen'))
            ->assertDontSee('Waarde opslaan in concept');

        $draft = $this->makeDraft();
        $this->admin()->get(route('admin.hvac.rules.section', ['section' => 'verkoopprijzen', 'concept' => $draft->id]))
            ->assertOk()
            ->assertSee('U bewerkt het concept (versie 2)')
            ->assertSee('Waarde opslaan in concept');

        $this->admin()->get(route('admin.hvac.rules.section', ['section' => 'verkoopprijzen', 'concept' => 9999]))
            ->assertNotFound();
    }

    public function test_v2_rule_set_renders_its_own_guided_rules(): void
    {
        $this->active();
        $this->artisan('hvac:seed-v2-rule-set');
        $v2 = HvacRuleSet::where('version', 2)->firstOrFail();

        $this->admin()->get(route('admin.hvac.rules.section', ['section' => 'koelvermogen', 'concept' => $v2->id]))
            ->assertOk()
            ->assertSee('Warmtedoorgang bij goede isolatie')
            ->assertSee('Veiligheidsmarge op de koellast')
            ->assertSee('Snelle inschatting');

        // The dashboard warns that this concept switches the calculation model.
        $this->admin()->get(route('admin.hvac.rules.index'))
            ->assertSee('Belgische residentiële koellast');
    }
}
