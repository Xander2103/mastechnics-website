<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (app()->environment('production') && (empty($email) || empty($password))) {
            throw new \RuntimeException(
                'Refusing to seed an admin user in production without ADMIN_EMAIL and ADMIN_PASSWORD set in the environment.'
            );
        }

        AdminUser::updateOrCreate(
            [
                'email' => $email ?: 'admin@example.com',
            ],
            [
                'name' => env('ADMIN_NAME', 'Admin'),
                'password' => Hash::make($password ?: 'password'),
            ]
        );
    }
}
