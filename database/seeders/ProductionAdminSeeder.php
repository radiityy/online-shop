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
        $adminName = (string) env('ADMIN_NAME', 'NEVERENDING Admin');
        $adminEmail = Str::lower(trim((string) env('ADMIN_EMAIL')));
        $adminPassword = (string) env('ADMIN_PASSWORD');

        if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'ADMIN_EMAIL harus diisi dengan email valid di file .env. '
                . 'Jika sudah diisi, jalankan "php artisan config:clear" lalu ulangi.'
            );
        }

        if (Str::length($adminPassword) < 12) {
            throw new RuntimeException(
                'ADMIN_PASSWORD harus diisi minimal 12 karakter di file .env. '
                . 'Jika sudah diisi, jalankan "php artisan config:clear" lalu ulangi.'
            );
        }

        $admin = User::query()->where('email', $adminEmail)->first();

        if ($admin && $admin->role !== User::ROLE_ADMIN) {
            throw new RuntimeException(
                'Email ini sudah dipakai oleh akun non-admin. '
                . 'Gunakan email lain untuk admin, atau hapus akun tersebut secara manual terlebih dulu.'
            );
        }

        $admin ??= new User();

        // forceFill karena role tidak ada di $fillable.
        $admin->forceFill([
            'name' => $adminName,
            'email' => $adminEmail,
            'password' => Hash::make($adminPassword),
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => $admin->email_verified_at ?? now(),
            'remember_token' => Str::random(60),
        ])->save();
    }
}