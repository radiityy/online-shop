<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    private function adminUrl(): string
    {
        return '/' . trim((string) (config('app.admin_path') ?: 'admin'), '/');
    }

    public function test_guest_is_redirected_from_admin_panel(): void
    {
        $this->get($this->adminUrl())
            ->assertRedirect();
    }

    public function test_customer_cannot_access_admin_panel(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $this->actingAs($customer)
            ->get($this->adminUrl())
            ->assertForbidden();
    }

    public function test_admin_without_mfa_is_sent_to_mfa_setup(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        // MFA diwajibkan di panel, jadi admin yang belum mengatur MFA dialihkan
        // ke halaman pengaturan MFA dan tidak langsung melihat dashboard.
        $this->actingAs($admin)
            ->get($this->adminUrl())
            ->assertRedirect();
    }

    public function test_admin_with_mfa_can_access_admin_panel(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        // Metode ini disediakan trait InteractsWithAppAuthentication.
        $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        $this->actingAs($admin)
            ->get($this->adminUrl())
            ->assertOk();
    }

    public function test_default_admin_path_is_not_exposed_when_custom_path_is_set(): void
    {
        if ((config('app.admin_path') ?: 'admin') === 'admin') {
            $this->markTestSkipped('ADMIN_PATH masih memakai nilai default.');
        }

        $this->get('/admin')
            ->assertNotFound();
    }
}