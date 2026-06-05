<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::query()
                ->where('email', $googleUser->getEmail())
                ->orWhere('google_id', $googleUser->getId())
                ->first();

            if (! $user) {
                $user = User::query()->create([
                    'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'NEVERENDING Customer',
                    'email' => $googleUser->getEmail(),
                    'password' => Hash::make(Str::random(32)),
                    'role' => 'customer',
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                ]);
            } else {
                $user->forceFill([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ])->save();
            }

            Auth::login($user, remember: true);

            return redirect()->intended(route('home', absolute: false));
        } catch (Throwable) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Google login failed. Please try again or use email and password.',
                ]);
        }
    }
}