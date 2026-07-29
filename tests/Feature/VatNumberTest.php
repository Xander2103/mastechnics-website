<?php

namespace Tests\Feature;

use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VatNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PageSeeder::class);
    }

    public function test_config_exposes_vat_number(): void
    {
        $this->assertSame('BE 0760.768.228', config('site.vat_number'));
    }

    public function test_footer_shows_vat_number_on_homepage(): void
    {
        $this->get(route('pages.home', ['locale' => 'nl']))
            ->assertOk()
            ->assertSee('BE 0760.768.228');
    }

    public function test_contact_page_shows_vat_number_in_all_locales(): void
    {
        $this->get(route('pages.show', ['locale' => 'nl', 'slug' => 'contact']))
            ->assertOk()
            ->assertSee('BE 0760.768.228')
            ->assertSee('BTW-nummer');

        $this->get(route('pages.show', ['locale' => 'fr', 'slug' => 'contact']))
            ->assertOk()
            ->assertSee('BE 0760.768.228')
            ->assertSee('Numéro de TVA');

        $this->get(route('pages.show', ['locale' => 'en', 'slug' => 'contact']))
            ->assertOk()
            ->assertSee('BE 0760.768.228')
            ->assertSee('VAT number');
    }
}
