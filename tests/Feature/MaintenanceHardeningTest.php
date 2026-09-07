<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Page;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression tests for operational safety: seeders that can be re-run on a
 * live database, no default admin password in any environment, and lookup
 * indexes that SQLite does not create on its own.
 */
class MaintenanceHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_seeder_can_run_twice_without_duplicating_or_crashing(): void
    {
        $this->seed(PageSeeder::class);
        $pages = Page::count();
        $translations = \App\Models\PageTranslation::count();

        // Simulate meta rewritten by a later migration: it must survive.
        $home = Page::where('code', 'home')->firstOrFail();
        $home->translations()->where('locale', 'nl')->update(['meta_title' => 'Aangepast door migratie']);

        $this->seed(PageSeeder::class);

        $this->assertSame($pages, Page::count());
        $this->assertSame($translations, \App\Models\PageTranslation::count());
        $this->assertSame('Aangepast door migratie', $home->translations()->where('locale', 'nl')->value('meta_title'));
    }

    public function test_page_content_seeder_refuses_to_run_in_production(): void
    {
        $this->seed(PageSeeder::class);
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);

        (new \Database\Seeders\PageContentSeeder())->run();
    }

    public function test_admin_seeder_refuses_to_plant_a_default_password_outside_production(): void
    {
        foreach (['ADMIN_EMAIL', 'ADMIN_PASSWORD'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        $this->app->detectEnvironment(fn () => 'local');

        $this->expectException(\RuntimeException::class);

        (new AdminUserSeeder())->run();

        $this->assertSame(0, AdminUser::count());
    }

    public function test_admin_seeder_stores_the_email_lower_case(): void
    {
        // env() reads $_ENV/$_SERVER before getenv(); a developer's local
        // .env may define these, so all three sources are set and restored.
        $previous = [];
        foreach (['ADMIN_EMAIL' => 'Martin@Test.com', 'ADMIN_PASSWORD' => 'EenVeiligWachtwoord123'] as $key => $value) {
            $previous[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }

        try {
            (new AdminUserSeeder())->run();
        } finally {
            foreach ($previous as $key => [$env, $server, $getenv]) {
                $env === null ? $_ENV[$key] = null : $_ENV[$key] = $env;
                $server === null ? $_SERVER[$key] = null : $_SERVER[$key] = $server;
                $getenv === false ? putenv($key) : putenv("{$key}={$getenv}");
                if ($env === null) {
                    unset($_ENV[$key]);
                }
                if ($server === null) {
                    unset($_SERVER[$key]);
                }
            }
        }

        $this->assertSame('martin@test.com', AdminUser::first()->email);
    }

    public function test_lookup_indexes_exist_on_foreign_key_and_filter_columns(): void
    {
        $expected = [
            'customer_requests' => ['status', 'created_at', 'service_category'],
            'customer_request_attachments' => ['customer_request_id'],
            'mail_logs' => ['customer_request_id'],
            'quotes' => ['quote_status'],
            'hvac_recommendation_items' => ['hvac_recommendation_id', 'hvac_product_id'],
            'hvac_import_catalog_product' => ['hvac_product_id'],
        ];

        foreach ($expected as $table => $columns) {
            $indexed = array_map(fn (array $index) => $index['columns'], Schema::getIndexes($table));

            foreach ($columns as $column) {
                $this->assertContains([$column], $indexed, "Missing index on {$table}.{$column}");
            }
        }
    }
}
