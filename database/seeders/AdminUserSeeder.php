<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        // Never plant a known default password, in any environment: a typo
        // in APP_ENV must not be the only thing between production and
        // "admin@example.com / password". Use `php artisan admin:create`
        // or set ADMIN_EMAIL + ADMIN_PASSWORD for the seeder.
        if (empty($email) || empty($password)) {
            throw new \RuntimeException(
                'Refusing to seed an admin user without ADMIN_EMAIL and ADMIN_PASSWORD set in the environment (or run php artisan admin:create).'
            );
        }

        AdminUser::updateOrCreate(
            [
                'email' => Str::lower(trim((string) $email)),
            ],
            [
                'name' => env('ADMIN_NAME', 'Admin'),
                'password' => Hash::make($password),
            ]
        );
    }
}
