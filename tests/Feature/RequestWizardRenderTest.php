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
}
