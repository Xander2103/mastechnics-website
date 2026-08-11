<?php

namespace Tests\Feature\Hvac;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The HVAC import upload limit is configurable (HVAC_IMPORT_MAX_MB, default
 * 25 MB) and shared by the product and compatibility importers — never a
 * hardcoded 4096 KB.
 */
class HvacImportLimitTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return ['admin_user_email' => 'admin@test.com'];
    }

    private function validProductCsv(int $padToBytes = 0): UploadedFile
    {
        $csv = "supplier;brand;sku;model;name;product_type;notes\n"
            . "TEST Leverancier;TESTMERK;TEST-1;TEST model;TEST product;wall_bracket;ok\n";

        if ($padToBytes > strlen($csv)) {
            // One huge comment line keeps parsing fast while inflating size.
            $csv .= 'TEST Leverancier;TESTMERK;TEST-2;TEST model 2;TEST product 2;wall_bracket;'
                . str_repeat('x', $padToBytes - strlen($csv)) . "\n";
        }

        return UploadedFile::fake()->createWithContent('producten.csv', $csv);
    }

    public function test_file_below_limit_is_accepted(): void
    {
        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.preview'), [
                'file' => $this->validProductCsv(),
                'mode' => 'create_and_update',
            ]);

        $response->assertOk();
        $response->assertSessionHasNoErrors();
    }

    public function test_file_over_configured_limit_is_rejected(): void
    {
        config(['hvac.import.max_upload_mb' => 1]);

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.preview'), [
                'file' => UploadedFile::fake()->create('producten.csv', 2048, 'text/csv'),
                'mode' => 'create_and_update',
            ]);

        $response->assertSessionHasErrors('file');
        $this->assertStringContainsString('1 MB', session('errors')->first('file'));
    }

    public function test_limit_is_not_the_old_hardcoded_4096_kb(): void
    {
        // Default 25 MB: a ~4.5 MB file (over the old 4096 KB limit) passes.
        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.preview'), [
                'file' => $this->validProductCsv((int) (4.5 * 1024 * 1024)),
                'mode' => 'create_and_update',
            ]);

        $response->assertOk();
        $response->assertSessionHasNoErrors();
    }

    public function test_default_limit_is_25_mb(): void
    {
        $this->assertSame(25, (int) config('hvac.import.max_upload_mb'));
    }

    public function test_compatibility_import_uses_the_same_configured_limit(): void
    {
        config(['hvac.import.max_upload_mb' => 1]);

        $response = $this->withSession($this->adminSession())
            ->post(route('admin.hvac.import.compat.preview'), [
                'file' => UploadedFile::fake()->create('compat.csv', 2048, 'text/csv'),
            ]);

        $response->assertSessionHasErrors('file');
        $this->assertStringContainsString('1 MB', session('errors')->first('file'));
    }

    public function test_index_page_shows_the_configured_maximum_size(): void
    {
        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.index'))
            ->assertOk()
            ->assertSee('max. 25 MB');

        config(['hvac.import.max_upload_mb' => 10]);

        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.index'))
            ->assertOk()
            ->assertSee('max. 10 MB');
    }

    public function test_server_side_post_limit_shows_a_friendly_message(): void
    {
        // When PHP/nginx reject the request before Laravel validation, the
        // exception handler redirects back with this flag (no session yet at
        // that point in the middleware stack).
        $this->withSession($this->adminSession())
            ->get(route('admin.hvac.import.index', ['upload_too_large' => 1]))
            ->assertOk()
            ->assertSee('groter dan wat de server aanvaardt');
    }
}
