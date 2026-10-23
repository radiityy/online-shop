<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(array $overrides = [], bool $emailVerified = true): void
    {
        $attributes = array_merge([
            'id' => 'google-123',
            'name' => 'Google Customer',
            'email' => 'customer@example.com',
            'avatar' => null,
        ], $overrides);

        $googleUser = (new SocialiteUser())
            ->map($attributes)
            ->setRaw(['email_verified' => $emailVerified]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_new_google_user_becomes_a_verified_customer(): void
    {
        $this->fakeGoogleUser();

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('home', absolute: false));

        $user = User::query()->where('email', 'customer@example.com')->firstOrFail();

        $this->assertSame('customer', $user->role);
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_cannot_log_in_through_google(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'customer@example.com',
        ]);

        $this->fakeGoogleUser();

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($admin->fresh()->google_id);
    }

    public function test_google_email_that_is_not_verified_is_rejected(): void
    {
        $this->fakeGoogleUser(emailVerified: false);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_linking_an_unverified_local_account_disables_its_old_password(): void
    {
        $local = User::factory()->create([
            'role' => 'customer',
            'email' => 'customer@example.com',
            'password' => 'attacker-known-pass',
            'email_verified_at' => null,
        ]);

        $this->assertTrue(Hash::check('attacker-known-pass', $local->password));

        $this->fakeGoogleUser();

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('home', absolute: false));

        $local->refresh();

        $this->assertFalse(Hash::check('attacker-known-pass', $local->password));
        $this->assertSame('google-123', $local->google_id);
        $this->assertNotNull($local->email_verified_at);
        $this->assertAuthenticatedAs($local);
    }

    public function test_linking_a_verified_local_account_keeps_its_password(): void
    {
        $local = User::factory()->create([
            'role' => 'customer',
            'email' => 'customer@example.com',
            'password' => 'my-own-password',
        ]);

        $this->fakeGoogleUser();

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('home', absolute: false));

        $local->refresh();

        $this->assertTrue(Hash::check('my-own-password', $local->password));
        $this->assertSame('google-123', $local->google_id);
        $this->assertAuthenticatedAs($local);
    }

    public function test_account_already_linked_to_another_google_id_is_rejected(): void
    {
        $local = User::factory()->create([
            'role' => 'customer',
            'email' => 'customer@example.com',
        ]);
        $local->forceFill(['google_id' => 'google-other'])->save();

        $this->fakeGoogleUser();

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame('google-other', $local->fresh()->google_id);
    }
}