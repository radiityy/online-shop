<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_log_in_through_storefront_form(): void
    {
        // Password bawaan UserFactory Laravel adalah "password".
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_customer_can_still_log_in_through_storefront_form(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $this->post(route('login'), [
            'email' => $customer->email,
            'password' => 'password',
        ])->assertRedirect(route('home', absolute: false));

        $this->assertAuthenticatedAs($customer);
    }

    public function test_registration_always_creates_a_customer(): void
    {
        $this->post(route('register'), [
            'name' => 'Test Customer',
            'email' => 'test-customer@example.com',
            'password' => 'a-long-test-password-123',
            'password_confirmation' => 'a-long-test-password-123',
            'role' => 'admin',
        ]);

        $user = User::query()->where('email', 'test-customer@example.com')->firstOrFail();

        $this->assertSame('customer', $user->role);
    }
}