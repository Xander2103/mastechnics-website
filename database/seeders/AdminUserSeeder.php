<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        AdminUser::updateOrCreate(
            [
                'email' => config('admin.email'),
            ],
            [
                'name' => config('admin.name'),
                'password' => Hash::make(config('admin.password')),
            ]
        );
    }
}