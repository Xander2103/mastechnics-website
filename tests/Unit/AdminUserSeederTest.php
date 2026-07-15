<?php

namespace Tests\Unit;

use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure this test is deterministic regardless of a developer's local
        // .env (gitignored, not committed) legitimately setting ADMIN_EMAIL /
        // ADMIN_PASSWORD for manual admin-login testing. env() reads these
        // live, independent of the environment() override below.
        foreach (['ADMIN_EMAIL', 'ADMIN_PASSWORD'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    public function test_refuses_to_run_in_production_without_credentials(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);

        (new AdminUserSeeder())->run();
    }
}
