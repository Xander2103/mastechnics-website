<?php

namespace Tests\Feature;

use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestWizardRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PageSeeder::class);
    }

    public function test_request_wizard_renders_top_and_bottom_navigation(): void
    {
        $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'aanvraag']))
            ->assertOk()
            ->assertSee('id="wizardNavBarTop"', false)
            ->assertSee('id="wizardTerugTop"', false)
            ->assertSee('id="wizardVerderTop"', false)
            ->assertSee('id="wizardTerug"', false)
            ->assertSee('id="wizardVerder"', false);
    }

    public function test_request_wizard_renders_service_category_options(): void
    {
        $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'aanvraag']))
            ->assertOk()
            ->assertSee('name="service_category"', false)
            ->assertSee('class="option-card', false);
    }

    public function test_service_category_cards_show_titles_but_not_descriptions(): void
    {
        $response = $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'aanvraag']));

        $response->assertOk();

        // Titles (and their radio values) must still render and submit correctly.
        $response->assertSee('value="airco_offerte"', false);
        $response->assertSee('Ik wil een airco laten plaatsen');
        $response->assertSee('Mijn verwarming heeft onderhoud nodig');
        $response->assertSee('Ik heb een lek of dringend probleem');

        // The description copy that used to render under each title must be gone.
        $response->assertDontSee('option-card-desc', false);
        $response->assertDontSee('Voor een nieuwe airco-installatie of offerte.');
        $response->assertDontSee('Bij waterlek, verlies van druk of dringende panne.');
    }

    public function test_service_category_descriptions_still_removed_in_french_and_english(): void
    {
        $this->get(route('pages.show', ['locale' => 'fr', 'slug' => 'demande']))
            ->assertOk()
            ->assertDontSee('option-card-desc', false)
            ->assertDontSee('Pour une nouvelle installation de climatisation ou un devis.');

        $this->get(route('pages.show', ['locale' => 'en', 'slug' => 'request']))
            ->assertOk()
            ->assertDontSee('option-card-desc', false)
            ->assertDontSee('For a new air conditioning installation or quote.');
    }
}
