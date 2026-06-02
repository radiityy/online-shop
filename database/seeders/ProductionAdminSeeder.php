<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class ProductionAdminSeeder extends Seeder
{
    public function run(): void
    {
        $adminName = env('ADMIN_NAME', 'NEVERENDING Admin');
        $adminEmail = env('ADMIN_EMAIL');
        $adminPassword = env('ADMIN_PASSWORD');

        if (! $adminEmail || ! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('ADMIN_EMAIL harus diisi dengan email valid di file .env.');
        }

        if (! $adminPassword || Str::length($adminPassword) < 8) {
            throw new RuntimeException('ADMIN_PASSWORD harus diisi minimal 8 karakter di file .env.');
        }

        User::query()->updateOrCreate(
            [
                'email' => $adminEmail,
            ],
            [
                'name' => $adminName,
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
                'email_verified_at' => now(),
            ],
        );
    }
}