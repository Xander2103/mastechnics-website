<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('admin.users', []) as $adminUser) {
            AdminUser::updateOrCreate(
                [
                    'email' => $adminUser['email'],
                ],
                [
                    'name' => $adminUser['name'],
                    'password' => Hash::make($adminUser['password']),
                ]
            );
        }
    }
}