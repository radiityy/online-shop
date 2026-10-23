<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->with([
                'prompt' => 'select_account',
            ])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $googleId = (string) $googleUser->getId();
            $email = Str::lower((string) $googleUser->getEmail());

            // Hanya terima email yang sudah diverifikasi oleh Google sendiri.
            $emailVerifiedByGoogle = filter_var(
                $googleUser->user['email_verified'] ?? false,
                FILTER_VALIDATE_BOOLEAN,
            );

            if ($googleId === '' || $email === '' || ! $emailVerifiedByGoogle) {
                Log::warning('Login Google ditolak: email kosong atau belum diverifikasi Google.');

                return $this->failed();
            }

            // Cari lewat google_id dulu. Kalau belum ada, baru cari lewat email.
            $user = User::query()->where('google_id', $googleId)->first();

            if (! $user) {
                $user = User::query()->where('email', $email)->first();

                // Akun dengan email ini sudah tertaut ke akun Google yang berbeda
                // (misalnya email didaur ulang). Jangan ditautkan ulang.
                if ($user && $user->google_id !== null && $user->google_id !== $googleId) {
                    Log::warning('Login Google ditolak: google_id tidak cocok.', ['user_id' => $user->id]);

                    return $this->failed();
                }
            }

            // Admin hanya boleh masuk lewat halaman login panel (yang memakai MFA).
            if ($user && $user->role === 'admin') {
                Log::warning('Login Google ditolak untuk akun admin.', ['user_id' => $user->id]);

                return $this->failed();
            }

            if (! $user) {
                $user = new User();
                $user->forceFill([
                    'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'NEVERENDING Customer',
                    'email' => $email,
                    'password' => Hash::make(Str::random(40)),
                    'role' => 'customer',
                    'google_id' => $googleId,
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                ])->save();
            } else {
                $isLinkingByEmail = $user->google_id === null;
                $wasUnverified = $user->email_verified_at === null;

                $attributes = [
                    'google_id' => $googleId,
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ];

                // Akun lokal yang emailnya belum pernah diverifikasi bisa saja dibuat
                // orang lain memakai email korban (pra-pembajakan akun). Sebelum
                // ditautkan ke Google, password lama dan sesi lama dimatikan supaya
                // pembuat akun awal tidak ikut punya akses.
                if ($isLinkingByEmail && $wasUnverified) {
                    $attributes['password'] = Hash::make(Str::random(40));
                    $attributes['remember_token'] = Str::random(60);
                }

                $user->forceFill($attributes)->save();

                if ($isLinkingByEmail && $wasUnverified) {
                    $this->revokeSessions($user);
                }
            }

            Auth::login($user, remember: true);

            return redirect()->intended(route('home', absolute: false));
        } catch (Throwable $e) {
            report($e);

            return $this->failed();
        }
    }

    private function failed(): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->withErrors([
                'email' => 'Google login failed. Please try again or use email and password.',
            ]);
    }

    /**
     * Hapus semua sesi aktif milik user. Hanya berlaku selama SESSION_DRIVER=database.
     * Setelah pindah ke Redis, gunakan middleware AuthenticateSession.
     */
    private function revokeSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();
    }
}