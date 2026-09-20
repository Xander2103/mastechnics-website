<?php

namespace Tests\Feature\Hvac;

use App\Models\CustomerRequest;
use App\Models\HvacRuleSet;
use App\Services\Hvac\HvacRuleSetResolver;
use Database\Seeders\HvacDemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Sprint 23 — the "Snelle inschatting" page: an indicative, read-only tool
 * with no path to products, approval, quotes or mail.
 */
class HvacQuickEstimateTest extends TestCase
{
    use RefreshDatabase;

    private const WRITE_TABLES = [
        'hvac_calculations', 'hvac_recommendations', 'hvac_recommendation_items',
        'hvac_manual_overrides', 'hvac_rule_validations', 'quotes', 'customer_requests',
    ];

    private function admin()
    {
        return $this->withSession($this->adminSession());
    }

    private function url(array $query = []): string
    {
        return route('admin.hvac.rules.quick-estimate', $query);
    }

    public function test_reference_room_shows_the_full_calculation(): void
    {
        $response = $this->admin()->get($this->url([
            'length_m' => '5', 'width_m' => '4', 'height_m' => '2,5', 'situation' => 'well_insulated',
        ]));

        $response->assertOk();
        $response->assertSee('Indicatief koelvermogen');
        $response->assertSee('1,5 kW');
        $response->assertSee('Volume: 5 m × 4 m × 2,5 m = 50 m³');
        $response->assertSee('50 m³ × 30 W/m³ = 1.500 W');
        $response->assertSee('Niet-bindende indicatie van de klasse');
        $response->assertSee('Er wordt geen toestel gekozen.');
        $response->assertSee('Grote glaspartijen, interne warmtelasten');
    }

    public function test_all_four_situations_are_offered_with_their_values(): void
    {
        $response = $this->admin()->get($this->url());

        $response->assertOk();
        foreach ([
            'Goed geïsoleerd' => '30 W/m³',
            'Zuidgericht' => '35 W/m³',
            'Veel zon en slecht geïsoleerd' => '40 W/m³',
            'Veel zon, slechte isolatie en onder dak' => '45 W/m³',
        ] as $label => $value) {
            $response->assertSee($label);
            $response->assertSee($value);
        }
        // No result before anything is submitted.
        $response->assertDontSee('Indicatief koelvermogen');
    }

    public function test_invalid_and_missing_input_gives_clear_messages_and_no_result(): void
    {
        $response = $this->admin()->get($this->url(['length_m' => '-5', 'width_m' => 'breed', 'height_m' => '']));

        $response->assertOk();
        $response->assertSee('Lengte moet tussen 0,5 m en 50 m liggen.');
        $response->assertSee('Breedte moet een getal zijn, bijvoorbeeld 4 of 2,5.');
        $response->assertSee('Hoogte is verplicht.');
        $response->assertSee('Kies de situatie van de ruimte.');
        $response->assertSee('aria-invalid="true"', false);
        $response->assertDontSee('Indicatief koelvermogen');
    }

    public function test_array_input_does_not_crash_the_page(): void
    {
        $this->admin()->get($this->url() . '?length_m[]=5&width_m=4&height_m=2.5&situation[]=x')
            ->assertOk()
            ->assertDontSee('Indicatief koelvermogen');
    }

    public function test_estimate_never_creates_products_approvals_quotes_or_mail(): void
    {
        Mail::fake();
        $this->seed(HvacDemoCatalogSeeder::class);
        CustomerRequest::create([
            'locale' => 'nl', 'service_slug' => 'airco', 'request_type' => 'installation',
            'service_category' => 'airco_offerte', 'customer_name' => 'Test',
            'customer_email' => 't@e.com', 'description' => '', 'status' => 'new',
        ]);
        app(HvacRuleSetResolver::class)->active();

        $before = array_map(fn ($t) => DB::table($t)->count(), self::WRITE_TABLES);
        $productsBefore = DB::table('hvac_products')->count();

        $response = $this->admin()->get($this->url([
            'length_m' => '5', 'width_m' => '4', 'height_m' => '2.5', 'situation' => 'south_facing',
        ]));

        $response->assertOk()->assertSee('1,75 kW');
        // Even with a filled catalog, no product is named or proposed.
        $response->assertDontSee('TEST-');
        $response->assertDontSee('Optie goedkeuren');
        $response->assertDontSee('Omzetten naar offerte');

        $this->assertSame($before, array_map(fn ($t) => DB::table($t)->count(), self::WRITE_TABLES));
        $this->assertSame($productsBefore, DB::table('hvac_products')->count());
        $this->assertSame(1, HvacRuleSet::count());
        Mail::assertNothingSent();
    }

    public function test_the_tool_has_no_write_endpoint(): void
    {
        $this->admin()->post($this->url(), ['length_m' => '5'])->assertStatus(405);
    }

    public function test_unconfirmed_values_are_flagged_on_the_result(): void
    {
        $query = ['length_m' => '5', 'width_m' => '4', 'height_m' => '2.5', 'situation' => 'well_insulated'];

        $this->admin()->get($this->url($query))->assertSee('Vuistregels nog niet bevestigd');

        foreach (array_keys(config('hvac.default_rule_set.configuration.quick_estimate.w_per_m3')) as $situation) {
            $this->admin()->post(route('admin.hvac.rules.validate'), [
                'rule_key' => "quick_estimate.w_per_m3.{$situation}", 'confirm' => '1',
            ]);
        }

        $this->admin()->get($this->url($query))
            ->assertDontSee('Vuistregels nog niet bevestigd')
            // Confirmed rules of thumb still never make the class binding.
            ->assertSee('Dit is uitsluitend een indicatie');
    }

    public function test_changed_value_applies_only_after_activation(): void
    {
        $query = ['length_m' => '5', 'width_m' => '4', 'height_m' => '2.5', 'situation' => 'well_insulated'];
        app(HvacRuleSetResolver::class)->active();

        $this->admin()->post(route('admin.hvac.rules.draft'));
        $draft = HvacRuleSet::where('status', 'draft')->firstOrFail();
        $this->admin()->patch(route('admin.hvac.rules.value', $draft), [
            'rule_key' => 'quick_estimate.w_per_m3.well_insulated', 'value' => '32',
        ])->assertSessionHas('success', 'hvac_rule_value_updated');

        // Versioned: the concept changes nothing yet.
        $this->admin()->get($this->url($query))->assertSee('1,5 kW');

        $this->admin()->post(route('admin.hvac.rules.activate', $draft), ['confirm' => '1']);

        $this->admin()->get($this->url($query))
            ->assertSee('1,6 kW')
            ->assertSee('50 m³ × 32 W/m³ = 1.600 W');
    }

    public function test_existing_rule_sets_receive_the_quick_values_through_the_migration(): void
    {
        // An install from before Sprint 23: a persisted set without the key.
        $configuration = config('hvac.default_rule_set.configuration');
        unset($configuration['quick_estimate']);
        $active = HvacRuleSet::create([
            'name' => 'Standaard voorcalculatie', 'version' => 1, 'status' => 'active',
            'configuration' => $configuration,
        ]);
        $archived = HvacRuleSet::create([
            'name' => 'Oud', 'version' => 1, 'status' => 'archived', 'configuration' => $configuration,
        ]);

        $this->admin()->get($this->url())->assertSee('Niet beschikbaar');

        $migration = require database_path('migrations/2026_09_20_000001_add_quick_estimate_to_hvac_rule_sets.php');
        $migration->up();

        $fresh = $active->fresh()->configuration;
        $this->assertSame(30, $fresh['quick_estimate']['w_per_m3']['well_insulated']);
        // Every pre-existing rule is byte-for-byte what it was.
        unset($fresh['quick_estimate']);
        $this->assertEquals($configuration, $fresh);
        // History is left alone.
        $this->assertArrayNotHasKey('quick_estimate', $archived->fresh()->configuration);

        $this->admin()->get($this->url())->assertDontSee('Niet beschikbaar');
    }
}
