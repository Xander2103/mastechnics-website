<?php

namespace Tests\Feature\Hvac;

use App\Models\CustomerRequest;
use App\Models\HvacBrand;
use App\Models\HvacCalculation;
use App\Models\HvacImportCatalog;
use App\Models\HvacProduct;
use App\Models\HvacProductCompatibility;
use App\Models\HvacRecommendation;
use App\Models\HvacRuleSet;
use App\Models\HvacRuleValidation;
use App\Models\Quote;
use App\Services\Hvac\AccessorySelector;
use App\Services\Hvac\HvacQuoteConversionService;
use App\Services\Hvac\HvacRecommendationReadiness;
use App\Services\Hvac\HvacRuleCatalog;
use App\Services\Hvac\HvacRuleSetResolver;
use App\Services\Hvac\ProductSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Regression tests for the engine-hardening audit (discount amplification,
 * post-approval mutation, change-product re-validation, rule-value drift,
 * conversion re-gating, superseded approvals, margin vs discounts, demo
 * quotes, VAT rounding parity, archived outdoor pairing, needs_review
 * accessories and the resolver fallback).
 */
class HvacEngineHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    private function brand(string $name = 'TestBrand', string $slug = 'testbrand'): HvacBrand
    {
        return HvacBrand::firstOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => true]);
    }

    private function product(array $attrs): HvacProduct
    {
        return HvacProduct::create(array_merge([
            'hvac_brand_id' => $this->brand()->id,
            'is_active'     => true,
        ], $attrs));
    }

    private function seedAccessories(string $prefix = 'TB', ?int $brandId = null): void
    {
        foreach (['wall_bracket', 'vibration_damper', 'pipe', 'trunking', 'electrical_accessory', 'drain_hose', 'condensate_pump'] as $i => $type) {
            $this->product([
                'hvac_brand_id' => $brandId ?? $this->brand()->id,
                'sku' => "{$prefix}-ACC-{$i}", 'model' => "{$prefix} acc {$type}", 'name' => "{$prefix} acc {$type}",
                'product_type' => $type,
                'default_sale_price_excl_vat' => 10, 'purchase_price_excl_vat' => 5,
            ]);
        }
    }

    private function seedSet(array $attrs = []): HvacProduct
    {
        return $this->product(array_merge([
            'sku' => 'TB-SET', 'model' => 'TestBrand Set 35', 'name' => 'TestBrand single split set',
            'product_type' => 'single_split_set', 'cooling_capacity_kw' => 3.5,
            'maximum_pipe_length_m' => 20, 'maximum_height_difference_m' => 10,
            'default_sale_price_excl_vat' => 1500, 'purchase_price_excl_vat' => 1000,
            'stock_quantity' => 5,
        ], $attrs));
    }

    private function request(array $answerOverrides = []): CustomerRequest
    {
        $answers = array_merge([
            'rooms' => [[
                'type' => 'woonkamer', 'width' => 4, 'length' => 5, 'height' => 2.5,
                'roof_type' => 'none', 'windows' => 'large', 'orientation' => 'west',
            ]],
            'insulation_level' => 'good',
            'customer_type'    => 'residential',
        ], $answerOverrides);

        return CustomerRequest::create([
            'locale' => 'nl', 'service_slug' => 'airco', 'request_type' => 'installation',
            'service_category' => 'airco_offerte',
            'customer_name' => 'Test Klant', 'customer_email' => 'klant@example.com',
            'description' => '', 'status' => 'new',
            'metadata' => ['answers' => $answers],
        ]);
    }

    private function calculate(CustomerRequest $request): void
    {
        $this->withSession($this->adminSession())->post(route('admin.requests.hvac.calculate', $request));
    }

    private function approve(CustomerRequest $request, HvacRecommendation $rec)
    {
        return $this->withSession($this->adminSession())->post(route('admin.requests.hvac.approve', [$request, $rec]));
    }

    /** Validate every applicable critical rule through the real admin route (stores the validated value). */
    private function validateAllCritical(): void
    {
        $active = app(HvacRuleSetResolver::class)->active();
        foreach (HvacRuleCatalog::criticalKeys() as $key) {
            if (HvacRuleCatalog::value($active->configuration, $key) === null) {
                continue;
            }
            $this->withSession($this->adminSession())->post(route('admin.hvac.rules.validate'), ['rule_key' => $key]);
        }
    }

    /** A non-demo (non-TEST-named) single-split catalog with priced accessories. */
    private function seedRealLookingCatalog(): void
    {
        $acme = $this->brand('Acme Fictief', 'acme');
        $this->seedSet(['hvac_brand_id' => $acme->id, 'sku' => 'AC-SET', 'model' => 'Acme Set 35', 'name' => 'Acme set']);
        $this->seedAccessories('AC', $acme->id);
    }

    // ── 1. Discount line amplification ───────────────────────────────────────

    public function test_discount_quantity_override_is_rejected_and_totals_stay_positive(): void
    {
        $this->seedSet();
        $this->seedAccessories();
        $request = $this->request();
        $this->calculate($request);
        $rec = HvacRecommendation::firstOrFail();

        $this->withSession($this->adminSession())->post(route('admin.requests.hvac.discount', [$request, $rec]), [
            'amount' => 100, 'reason' => 'commerciële korting',
        ])->assertSessionHas('success', 'hvac_override_applied');
        $rec = $rec->fresh();
        $subtotalBefore = $rec->subtotal_excl_vat;
        $totalBefore = $rec->total_incl_vat;
        $discount = $rec->items()->where('item_type', 'discount')->firstOrFail();

        $this->withSession($this->adminSession())->post(route('admin.requests.hvac.items.override', [$request, $discount]), [
            'quantity' => 9999, 'reason' => 'hoeveelheid proberen',
        ])->assertSessionHasErrors('quantity');

        $rec = $rec->fresh();
        $this->assertEquals(1.0, (float) $discount->fresh()->quantity);
        $this->assertEquals($subtotalBefore, $rec->subtotal_excl_vat);
        $this->assertEquals($totalBefore, $rec->total_incl_vat);
        $this->assertGreaterThanOrEqual(0, $rec->subtotal_excl_vat);
        $this->assertGreaterThanOrEqual(0, $rec->total_incl_vat);
    }

    public function test_discount_price_override_larger_than_subtotal_is_rejected(): void
    {
        $this->seedSet();
        $this->seedAccessories();
        $request = $this->request();
        $this->calculate($request);
        $rec = HvacRecommendation::firstOrFail();

        $this->withSession($this->adminSession())->post(route('admin.requests.hvac.discount', [$request, $rec]), [
            'amount' => 100, 'reason' => 'commerciële korting',
        ]);
        $rec = $rec->fresh();
        $discount = $rec->items()->where('item_type', 'discount')->firstOrFail();
        $nonDiscount = (float) $rec->items()->where('item_type', '!=', 'discount')->sum('line_total');

        $this->withSession($this->adminSession())->post(route('admin.requests.hvac.items.override', [$request, $discount]), [
            'sale_unit_price' => $nonDiscount + 1, 'reason' => 'te grote korting',
        ])->assertSessionHasErrors('quantity');

        $this->assertEquals(-100.0, (float) $discount->fresh()->sale_unit_price);
        $this->assertEquals($rec->subtotal_excl_vat, $rec->fresh()->subtotal_excl_vat);
        $this->assertGreaterThanOrEqual(0, $rec->fresh()->subtotal_excl_vat);
    }

    public function test_discount_price_override_is_normalised_to_negative(): void
    {
        $this->seedSet();
        $this->seedAccessories();
        $request = $this->request();
        $this->calculate($request);
        $rec = HvacRecommendation::firstOrFail();

        $this->withSession($this->adminSession())->post(route('admin.requests.hvac.discount', [$request, $rec]), [
            'amount' => 100, 'reason' => 'commerciële korting',
        ]);
        $rec = $rec->fresh();
        $subtotalBefore = $rec->subtotal_excl_vat;
        $discount = $rec->items()->where('item_type', 'discount')->firstOrFail();

        $this->withSession($this->adminSession())->post(route('admin.requests.hvac.items.override', [$request, $discount]), [
            'sale_unit_price' => 150, 'reason' => 'korting verhoogd na overleg',
        ])->assertSessionHas('success', 'hvac_override_applied');

        $discount = $discount->fresh();
        $this->assertEquals(-150.0, (float) $discount->sale_unit_price);
        $this->assertEquals(-150.0, (float) $discount->line_total);
        $this->assertEquals(1.0, (float) $discount->quantity);
        $this->assertEquals(round($subtotalBefore - 50, 2), $rec->fresh()->subtotal_excl_vat);
    }

    // ── 2. Room-load override after approval ─────────────────────────────────

    public function test_room_load_override_is_refused_while_an_option_is_approved(): void
    {
        $this->seedSet();
        $this->seedAccessories();
        $request = $this->request();
        $this->calculate($request);
        $rec = HvacRecommendation::firstOrFail();
        $this->approve($request, $rec)->assertSessionHas('success', 'hvac_recommendation_approved');

        $payload = ['room_index' => 0, 'watts' => 9000, 'reason' => 'plaatsbezoek: veel groter'];

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.rooms.override-load', $request), $payload)
            ->assertSessionHasErrors('room_index');

        $calc = $rec->fresh()->calculation;
        $this->assertArrayNotHasKey('final_watts_override', $calc->result['rooms'][0]['load']);
        $this->assertSame('approved', $rec->fresh()->status);
        $this->assertDatabaseMissing('hvac_manual_overrides', ['field' => 'room:0:final_watts']);

        // The approved option remains convertible as-is (nothing changed).
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.convert', [$request, $rec->fresh()]))
            ->assertSessionHas('success', 'hvac_converted');
        $this->assertSame('converted', $rec->fresh()->status);
    }

    public function test_room_load_override_works_again_after_rejecting_the_approved_option(): void
    {
        $this->seedSet();
        $this->seedAccessories();
        $request = $this->request();
        $this->calculate($request);
        $rec = HvacRecommendation::firstOrFail();
        $this->approve($request, $rec);
        $this->withSession($this->adminSession())->post(route('admin.requests.hvac.reject', [$request, $rec]))
            ->assertSessionHas('success', 'hvac_recommendation_rejected');

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.rooms.override-load', $request), [
                'room_index' => 0, 'watts' => 3000, 'reason' => 'plaatsbezoek: iets groter',
            ])->assertSessionHas('success', 'hvac_override_applied');

        $calc = HvacCalculation::where('status', 'calculated')->firstOrFail();
        $this->assertSame(3000, $calc->result['rooms'][0]['load']['final_watts_override']);
    }

    // ── 3. change-product re-validation ──────────────────────────────────────

    public function test_change_product_to_another_product_type_is_rejected(): void
    {
        $this->seedSet();
        $this->seedAccessories();
        $bracket = HvacProduct::where('product_type', 'wall_bracket')->firstOrFail();
        $request = $this->request();
        $this->calculate($request);
        $rec = HvacRecommendation::firstOrFail();
        $item = $rec->items()->where('item_type', 'equipment')->firstOrFail();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.items.change-product', [$request, $item]), [
                'product_id' => $bracket->id, 'reason' => 'verkeerd type kiezen',
            ])->assertSessionHasErrors('product_id');

        $this->assertNotSame($bracket->id, $item->fresh()->hvac_product_id);
        $this->assertTrue(app(HvacRecommendationReadiness::class)->evaluate($rec->fresh())['technical_ok']);
    }

    public function test_change_product_to_set_with_unknown_limits_blocks_approval(): void
    {
        $this->seedSet();
        $this->seedAccessories();
        $noLimits = $this->product([
            'sku' => 'TB-SET-NOLIM', 'model' => 'TestBrand Set 99', 'name' => 'TestBrand set 99',
            'product_type' => 'single_split_set', 'cooling_capacity_kw' => 9.9,
            'default_sale_price_excl_vat' => 100, 'purchase_price_excl_vat' => 50,
        ]);
        $request = $this->request();
        $this->calculate($request);
        $rec = HvacRecommendation::where('status', 'draft')->firstOrFail();
        $item = $rec->items()->where('item_type', 'equipment')->firstOrFail();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.items.change-product', [$request, $item]), [
                'product_id' => $noLimits->id, 'reason' => 'ander toestel gekozen',
            ])->assertSessionHas('success', 'hvac_override_applied');

        $rec = $rec->fresh();
        $eval = app(HvacRecommendationReadiness::class)->evaluate($rec);
        $this->assertFalse($eval['technical_ok']);
        $this->assertFalse($rec->metadata['candidate']['valid']);
        $this->assertSame('unknown', $rec->metadata['candidate']['checks']['pipe']);
        $this->assertContains('limits_unknown', array_column($rec->metadata['candidate']['warnings'], 'code'));
        $this->assertContains('limits_unknown', array_column($rec->metadata['warnings'], 'code'));

        $this->approve($request, $rec)->assertSessionHasErrors('hvac_approve');
        $this->assertSame('draft', $rec->fresh()->status);
    }

    public function test_change_product_to_set_with_known_limits_keeps_technical_ok(): void
    {
        $this->seedSet();
        $this->seedAccessories();
        $alt = $this->seedSet([
            'sku' => 'TB-SET-ALT', 'model' => 'TestBrand Set 35 Alt', 'default_sale_price_excl_vat' => 1600,
        ]);
        $request = $this->request();
        $this->calculate($request);
        $rec = HvacRecommendation::where('status', 'draft')->orderBy('id')->firstOrFail();
        $item = $rec->items()->where('item_type', 'equipment')->firstOrFail();
        $target = $item->hvac_product_id === $alt->id ? HvacProduct::where('sku', 'TB-SET')->firstOrFail() : $alt;

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.items.change-product', [$request, $item]), [
                'product_id' => $target->id, 'reason' => 'ander toestel gekozen',
            ])->assertSessionHas('success', 'hvac_override_applied');

        $rec = $rec->fresh();
        $this->assertTrue(app(HvacRecommendationReadiness::class)->evaluate($rec)['technical_ok']);
        $this->assertSame('ok', $rec->metadata['candidate']['checks']['pipe']);
        $this->assertSame($target->id, $rec->metadata['candidate']['products'][0]['id']);
    }

    public function test_change_outdoor_unit_re_checks_explicit_compatibility(): void
    {
        $this->seedAccessories();
        $indoor = $this->product([
            'sku' => 'TB-IN', 'model' => 'TestBrand Indoor 35', 'name' => 'TestBrand indoor',
            'product_type' => 'indoor_unit', 'cooling_capacity_kw' => 3.5,
            'default_sale_price_excl_vat' => 700, 'purchase_price_excl_vat' => 400, 'stock_quantity' => 3,
        ]);
        $outdoor = $this->product([
            'sku' => 'TB-OUT', 'model' => 'TestBrand Outdoor 35', 'name' => 'TestBrand outdoor',
            'product_type' => 'outdoor_unit', 'cooling_capacity_kw' => 3.5,
            'maximum_pipe_length_m' => 20, 'maximum_height_difference_m' => 10,
            'default_sale_price_excl_vat' => 900, 'purchase_price_excl_vat' => 500, 'stock_quantity' => 3,
        ]);
        $compatibleOutdoor = $this->product([
            'sku' => 'TB-OUT-2', 'model' => 'TestBrand Outdoor 35 B', 'name' => 'TestBrand outdoor B',
            'product_type' => 'outdoor_unit', 'cooling_capacity_kw' => 3.5,
            'maximum_pipe_length_m' => 20, 'maximum_height_difference_m' => 10,
            'default_sale_price_excl_vat' => 950, 'purchase_price_excl_vat' => 500, 'stock_quantity' => 3,
        ]);
        $unlinkedOutdoor = $this->product([
            'sku' => 'TB-OUT-3', 'model' => 'TestBrand Outdoor 35 C', 'name' => 'TestBrand outdoor C',
            'product_type' => 'outdoor_unit', 'cooling_capacity_kw' => 3.5,
            'maximum_pipe_length_m' => 20, 'maximum_height_difference_m' => 10,
            'default_sale_price_excl_vat' => 950, 'purchase_price_excl_vat' => 500, 'stock_quantity' => 3,
        ]);
        foreach ([$outdoor, $compatibleOutdoor] as $parent) {
            HvacProductCompatibility::create([
                'parent_product_id' => $parent->id, 'compatible_product_id' => $indoor->id,
                'compatibility_type' => 'indoor_outdoor', 'is_active' => true,
            ]);
        }

        $request = $this->request();
        $this->calculate($request);
        $rec = HvacRecommendation::where('status', 'draft')->orderBy('id')->firstOrFail();
        $outdoorItem = $rec->items()->where('item_type', 'equipment')
            ->whereIn('hvac_product_id', [$outdoor->id, $compatibleOutdoor->id])->firstOrFail();
        $other = $outdoorItem->hvac_product_id === $outdoor->id ? $compatibleOutdoor : $outdoor;

        // Swap to the other explicitly-compatible outdoor: still technically ok.
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.items.change-product', [$request, $outdoorItem]), [
                'product_id' => $other->id, 'reason' => 'andere buitenunit',
            ])->assertSessionHas('success', 'hvac_override_applied');
        $this->assertTrue(app(HvacRecommendationReadiness::class)->evaluate($rec->fresh())['technical_ok']);

        // Swap to an outdoor without a compatibility row: not provable anymore.
        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.items.change-product', [$request, $outdoorItem->fresh()]), [
                'product_id' => $unlinkedOutdoor->id, 'reason' => 'niet gekoppelde buitenunit',
            ])->assertSessionHas('success', 'hvac_override_applied');
        $rec = $rec->fresh();
        $this->assertFalse(app(HvacRecommendationReadiness::class)->evaluate($rec)['technical_ok']);
        $this->assertContains('compatibility_missing_after_change', array_column($rec->metadata['candidate']['warnings'], 'code'));
        $this->approve($request, $rec)->assertSessionHasErrors('hvac_approve');
    }

    // ── 4. Draft rule set inherits validations for values that change ────────

    public function test_draft_validations_are_invalidated_when_the_value_changes(): void
    {
        $this->validateAllCritical();
        $active = app(HvacRuleSetResolver::class)->active();
        $this->assertNotNull($active->validations()->where('rule_key', 'labor.hourly_rate_excl_vat')->first()->validated_value);

        $this->withSession($this->adminSession())->post(route('admin.hvac.rules.draft'))
            ->assertSessionHas('success', 'hvac_rule_draft_created');
        $draft = HvacRuleSet::where('status', 'draft')->firstOrFail();
        $this->assertTrue(app(HvacRecommendationReadiness::class)->criticalRulesValidated($draft->id));

        $cfg = $draft->configuration;
        $cfg['labor']['hourly_rate_excl_vat'] = 999.0;
        $draft->update(['configuration' => $cfg]);

        $this->withSession($this->adminSession())->post(route('admin.hvac.rules.activate', $draft), ['confirm' => '1'])
            ->assertSessionHas('success', 'hvac_rule_set_activated');

        $this->assertFalse(app(HvacRecommendationReadiness::class)->criticalRulesValidated($draft->id));

        // The rules index no longer shows the changed rule as validated.
        $html = $this->withSession($this->adminSession())->get(route('admin.hvac.rules.index'))->getContent();
        $this->assertStringContainsString('999', $html);

        // Re-validating the changed rule restores readiness (value re-stored).
        $this->withSession($this->adminSession())->post(route('admin.hvac.rules.validate'), ['rule_key' => 'labor.hourly_rate_excl_vat']);
        $this->assertTrue(app(HvacRecommendationReadiness::class)->criticalRulesValidated($draft->id));
    }

    public function test_legacy_validations_without_stored_value_stay_valid(): void
    {
        $active = app(HvacRuleSetResolver::class)->active();
        foreach (HvacRuleCatalog::criticalKeys() as $key) {
            if (HvacRuleCatalog::value($active->configuration, $key) === null) {
                continue;
            }
            HvacRuleValidation::create([
                'hvac_rule_set_id' => $active->id, 'rule_key' => $key, 'status' => 'validated',
                'validated_by' => 'legacy', 'validated_at' => now(), 'validated_value' => null,
            ]);
        }

        $this->assertTrue(app(HvacRecommendationReadiness::class)->criticalRulesValidated($active->id));
    }

    // ── 5. Conversion is re-gated by readiness ───────────────────────────────

    public function test_conversion_is_blocked_when_a_critical_rule_is_unvalidated_after_approval(): void
    {
        $this->seedRealLookingCatalog();
        $request = $this->request();
        $this->calculate($request);
        $rec = HvacRecommendation::firstOrFail();
        $this->assertFalse(app(HvacRecommendationReadiness::class)->evaluate($rec)['demo']);

        $this->validateAllCritical();
        $this->approve($request, $rec)->assertSessionHas('success', 'hvac_recommendation_approved');

        $this->withSession($this->adminSession())->post(route('admin.hvac.rules.unvalidate'), ['rule_key' => 'labor.hourly_rate_excl_vat'])
            ->assertSessionHas('success', 'hvac_rule_unvalidated');

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.convert', [$request, $rec->fresh()]))
            ->assertSessionHasErrors('hvac_convert');

        $this->assertSame('approved', $rec->fresh()->status);
        $this->assertDatabaseCount('quotes', 0);
    }

    // ── 6. Approval on a superseded calculation ──────────────────────────────

    public function test_blocked_recalculation_supersedes_open_options_and_blocks_approval(): void
    {
        $this->seedSet();
        $this->seedAccessories();
        $request = $this->request();
        $this->calculate($request);
        $rec = HvacRecommendation::firstOrFail();

        $meta = $request->metadata;
        unset($meta['answers']['rooms'][0]['height']);
        $request->update(['metadata' => $meta]);
        $this->calculate($request);

        $this->assertSame('superseded', $rec->fresh()->calculation->status);
        $this->assertSame('superseded', $rec->fresh()->status);

        $this->approve($request, $rec->fresh());
        $this->assertNotSame('approved', $rec->fresh()->status);
    }

    public function test_approval_is_refused_when_the_calculation_is_no_longer_current(): void
    {
        $this->seedSet();
        $this->seedAccessories();
        $request = $this->request();
        $this->calculate($request);
        $rec = HvacRecommendation::firstOrFail();

        // A draft that somehow outlived its calculation must not be approvable.
        $rec->calculation->update(['status' => 'superseded']);

        $this->approve($request, $rec->fresh())->assertSessionHasErrors('hvac_approve');
        $this->assertSame('draft', $rec->fresh()->status);
    }

    // ── 7. Discounts reduce margin ───────────────────────────────────────────

    public function test_discount_is_reflected_in_margin_and_flags_negative_margin(): void
    {
        $this->seedSet();
        $this->seedAccessories();
        $request = $this->request();
        $this->calculate($request);
        $rec = HvacRecommendation::firstOrFail();
        $marginBefore = $rec->margin_amount;
        $this->assertGreaterThan(0, $marginBefore);

        $discount = round($rec->subtotal_excl_vat - 1, 2);
        $this->withSession($this->adminSession())->post(route('admin.requests.hvac.discount', [$request, $rec]), [
            'amount' => $discount, 'reason' => 'korting bijna alles',
        ])->assertSessionHas('success', 'hvac_override_applied');

        $rec = $rec->fresh();
        $this->assertEquals(round($marginBefore - $discount, 2), $rec->margin_amount);
        $this->assertLessThan(0, $rec->margin_amount);
        $this->assertContains('negative_margin', array_column($rec->metadata['warnings'], 'code'));
    }

    // ── 8. Demo (test-catalog) conversion ────────────────────────────────────

    public function test_demo_conversion_marks_the_quote_title(): void
    {
        $this->seedSet();
        $this->seedAccessories();
        $request = $this->request();
        $this->calculate($request);
        $rec = HvacRecommendation::firstOrFail();
        $this->assertTrue(app(HvacRecommendationReadiness::class)->evaluate($rec)['demo']);
        $this->approve($request, $rec);

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.hvac.convert', [$request, $rec->fresh()]))
            ->assertSessionHas('success', 'hvac_converted');

        $quote = Quote::firstOrFail();
        $this->assertStringStartsWith(HvacQuoteConversionService::TEST_CATALOG_TITLE_PREFIX, $quote->title);
        $this->assertStringStartsWith('[TESTCATALOGUS] ', $quote->title);
    }

    public function test_demo_quote_cannot_be_emailed(): void
    {
        $this->seedSet();
        $this->seedAccessories();
        $request = $this->request();
        $this->calculate($request);
        $rec = HvacRecommendation::firstOrFail();
        $this->approve($request, $rec);
        $this->withSession($this->adminSession())->post(route('admin.requests.hvac.convert', [$request, $rec->fresh()]));
        $quote = Quote::firstOrFail();

        $this->withSession($this->adminSession())
            ->post(route('admin.requests.quote.send-email', $request), [
                'to' => 'klant@example.com', 'subject' => 'Uw offerte', 'body' => 'Hierbij de offerte.',
            ])->assertSessionHasErrors('to');

        Mail::assertNothingSent();
        $this->assertSame('draft', $quote->fresh()->quote_status);
    }

    public function test_non_demo_conversion_has_no_test_marker(): void
    {
        $this->seedRealLookingCatalog();
        $request = $this->request();
        $this->calculate($request);
        $rec = HvacRecommendation::firstOrFail();
        $this->validateAllCritical();
        $this->approve($request, $rec)->assertSessionHas('success', 'hvac_recommendation_approved');
        $this->withSession($this->adminSession())->post(route('admin.requests.hvac.convert', [$request, $rec->fresh()]))
            ->assertSessionHas('success', 'hvac_converted');

        $this->assertStringNotContainsString('TESTCATALOGUS', Quote::firstOrFail()->title);
    }

    // ── 9. VAT rounding parity ───────────────────────────────────────────────

    public function test_recommendation_vat_equals_converted_quote_vat(): void
    {
        $this->seedSet();
        $this->seedAccessories();
        $request = $this->request();
        $this->calculate($request);
        $rec = HvacRecommendation::firstOrFail();

        foreach ($rec->items as $item) {
            $this->withSession($this->adminSession())->post(route('admin.requests.hvac.items.override', [$request, $item]), [
                'quantity' => 1, 'sale_unit_price' => 0, 'reason' => 'prijs op nul gezet',
            ]);
        }
        foreach ($rec->items()->orderBy('id')->take(3)->get() as $item) {
            $this->withSession($this->adminSession())->post(route('admin.requests.hvac.items.override', [$request, $item]), [
                'quantity' => 1, 'sale_unit_price' => 10.01, 'reason' => 'prijs aangepast',
            ]);
        }
        $rec = $rec->fresh();
        $this->assertEquals(30.03, $rec->subtotal_excl_vat);
        // 3 × round(10.01 × 0.21, 2) = 3 × 2.10 = 6.30 (not round(30.03 × 0.21) = 6.31)
        $this->assertEquals(6.30, $rec->vat_amount);
        $this->assertEquals(36.33, $rec->total_incl_vat);

        $this->approve($request, $rec);
        $this->withSession($this->adminSession())->post(route('admin.requests.hvac.convert', [$request, $rec->fresh()]));
        $quote = Quote::firstOrFail();

        $this->assertEquals((float) $rec->vat_amount, (float) $quote->amount_vat);
        $this->assertEquals((float) $rec->total_incl_vat, (float) $quote->amount_incl_vat);
    }

    // ── 10. Archived-list outdoor still paired ───────────────────────────────

    public function test_outdoor_unit_only_in_archived_catalog_is_not_paired(): void
    {
        $indoor = $this->product([
            'sku' => 'TB-IN', 'model' => 'TestBrand Indoor 35', 'name' => 'TestBrand indoor',
            'product_type' => 'indoor_unit', 'cooling_capacity_kw' => 3.5,
            'default_sale_price_excl_vat' => 700, 'purchase_price_excl_vat' => 400, 'stock_quantity' => 3,
        ]);
        $outdoor = $this->product([
            'sku' => 'TB-OUT', 'model' => 'TestBrand Outdoor 35', 'name' => 'TestBrand outdoor',
            'product_type' => 'outdoor_unit', 'cooling_capacity_kw' => 3.5,
            'maximum_pipe_length_m' => 20, 'maximum_height_difference_m' => 10,
            'default_sale_price_excl_vat' => 900, 'purchase_price_excl_vat' => 500, 'stock_quantity' => 3,
        ]);
        HvacProductCompatibility::create([
            'parent_product_id' => $outdoor->id, 'compatible_product_id' => $indoor->id,
            'compatibility_type' => 'indoor_outdoor', 'is_active' => true,
        ]);
        $archived = HvacImportCatalog::create([
            'name' => 'Oude lijst 2024', 'source_type' => 'guided', 'status' => HvacImportCatalog::STATUS_ARCHIVED,
        ]);
        $archived->products()->attach($outdoor->id);

        $request = $this->request();
        $calculation = app(\App\Services\Hvac\HvacCalculationService::class)->run($request);
        $selection = app(ProductSelector::class)->selectSystems($calculation);

        $pairs = array_filter($selection['candidates'], fn ($c) => $c['kind'] === 'pair' && count($c['products']) === 2);
        $this->assertSame([], array_values($pairs), 'an outdoor unit living only in an archived list must not be paired');
        $this->assertFalse(collect($selection['candidates'])->contains(fn ($c) => $c['valid']));
    }

    // ── 11. needs_review accessories ─────────────────────────────────────────

    public function test_accessory_flagged_for_import_review_is_skipped(): void
    {
        foreach (['vibration_damper', 'pipe', 'trunking', 'electrical_accessory', 'drain_hose', 'condensate_pump'] as $i => $type) {
            $this->product(['sku' => "X-{$i}", 'model' => $type, 'name' => $type, 'product_type' => $type,
                'default_sale_price_excl_vat' => 10, 'purchase_price_excl_vat' => 5]);
        }
        $this->product([
            'sku' => 'X-BR-REVIEW', 'model' => 'Bracket review', 'name' => 'Bracket review', 'product_type' => 'wall_bracket',
            'default_sale_price_excl_vat' => 1, 'purchase_price_excl_vat' => 0.5,
            'metadata' => ['import' => ['needs_review' => true, 'reasons' => ['price_semantics_unknown']]],
        ]);
        $clean = $this->product([
            'sku' => 'X-BR-OK', 'model' => 'Bracket ok', 'name' => 'Bracket ok', 'product_type' => 'wall_bracket',
            'default_sale_price_excl_vat' => 30, 'purchase_price_excl_vat' => 15,
        ]);
        $rooms = [['pipe' => ['actual_length_m' => 5.0, 'equivalent_length_m' => 10.25], 'load' => ['roof_type' => 'none']]];

        $out = app(AccessorySelector::class)->select(['products' => []], $rooms, config('hvac.default_rule_set.configuration'));

        $bracketLine = collect($out['items'])->firstWhere('key', 'wall_bracket');
        $this->assertSame($clean->id, $bracketLine['product_id']);
    }

    // ── 12. Resolver falls back to an archived set ───────────────────────────

    public function test_resolver_refuses_to_fall_back_to_an_archived_default_set(): void
    {
        $v1 = app(HvacRuleSetResolver::class)->active();
        $v1->update(['status' => 'archived']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Geen actieve regelset');
        app(HvacRuleSetResolver::class)->active();
    }

    public function test_resolver_seeds_the_default_set_only_when_none_exists(): void
    {
        $this->assertSame(0, HvacRuleSet::count());
        $set = app(HvacRuleSetResolver::class)->active();
        $this->assertSame('active', $set->status);
        $this->assertSame($set->id, app(HvacRuleSetResolver::class)->active()->id);
        $this->assertSame(1, HvacRuleSet::count());
    }

    // ── 14. Panel: approve button disabled when not ready ────────────────────

    public function test_panel_disables_approve_button_when_option_is_not_ready(): void
    {
        $this->seedRealLookingCatalog();
        $request = $this->request();
        $this->calculate($request);
        // Non-demo + rules not validated → not ready.
        $this->assertFalse(app(HvacRecommendationReadiness::class)->evaluate(HvacRecommendation::firstOrFail())['ready']);

        $html = $this->withSession($this->adminSession())->get(route('admin.requests.show', $request))->getContent();

        $this->assertSame(
            1,
            preg_match('/<button[^>]*disabled[^>]*>\s*Optie goedkeuren/', $html),
            'the approve button must render disabled when the option is not ready'
        );
    }
}
