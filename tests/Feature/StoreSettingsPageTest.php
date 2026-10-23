<?php

namespace Tests\Feature;

use App\Filament\Pages\StoreSettings;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\BiteshipService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class StoreSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private const AREA_ID = 'IDNP6IDNC148IDND836IDZ12110';

    private const AREA_NAME = 'Kebayoran Baru, Jakarta Selatan, DKI Jakarta. 12110';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'services.biteship.key' => 'biteship_test.dummy',
            'services.biteship.base_url' => 'https://api.biteship.com',
        ]);

        Filament::setCurrentPanel('admin');
    }

    private function adminUrl(): string
    {
        return '/' . trim((string) (config('app.admin_path') ?: 'admin'), '/') . '/store-settings';
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        return $admin;
    }

    private function primeAreaCache(): void
    {
        Http::fake([
            'api.biteship.com/v1/maps/areas*' => Http::response([
                'success' => true,
                'areas' => [[
                    'id' => self::AREA_ID,
                    'name' => self::AREA_NAME,
                    'administrative_division_level_1_name' => 'DKI Jakarta',
                    'administrative_division_level_2_name' => 'Jakarta Selatan',
                    'administrative_division_level_3_name' => 'Kebayoran Baru',
                    'postal_code' => 12110,
                ]],
            ]),
        ]);

        app(BiteshipService::class)->searchAreas('kebayoran');
    }

    private function validForm(array $overrides = []): array
    {
        return array_merge([
            'origin_area_id' => self::AREA_ID,
            'origin_postal_code' => '12110',
            'origin_contact_name' => 'Gudang NEVERENDING',
            'origin_contact_phone' => '081234567890',
            'origin_address' => 'Jl. Contoh No. 1, Kebayoran Baru',
        ], $overrides);
    }

    public function test_guest_cannot_open_store_settings(): void
    {
        $this->get($this->adminUrl())->assertRedirect();
    }

    public function test_customer_cannot_open_store_settings(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->get($this->adminUrl())
            ->assertForbidden();
    }

    public function test_admin_with_mfa_can_open_store_settings(): void
    {
        $this->actingAs($this->admin())
            ->get($this->adminUrl())
            ->assertOk();
    }

    public function test_admin_saves_origin_and_area_name_comes_from_server_cache(): void
    {
        $this->primeAreaCache();

        Livewire::actingAs($this->admin())
            ->test(StoreSettings::class)
            ->fillForm($this->validForm())
            ->call('save')
            ->assertHasNoFormErrors();

        $setting = StoreSetting::current();

        $this->assertSame(self::AREA_ID, $setting->origin_area_id);
        $this->assertSame(self::AREA_NAME, $setting->origin_area_name);
        $this->assertSame('12110', $setting->origin_postal_code);
        $this->assertSame('Gudang NEVERENDING', $setting->origin_contact_name);
        $this->assertTrue($setting->hasOrigin());
        $this->assertSame(1, StoreSetting::query()->count());
    }

    public function test_area_not_from_search_is_rejected(): void
    {
        Livewire::actingAs($this->admin())
            ->test(StoreSettings::class)
            ->fillForm($this->validForm(['origin_area_id' => 'ID-TIDAK-ADA']))
            ->call('save')
            ->assertHasFormErrors(['origin_area_id']);

        $this->assertSame(0, StoreSetting::query()->count());
    }

    public function test_unchanged_area_can_be_saved_after_cache_expires(): void
    {
        StoreSetting::create([
            'origin_contact_name' => 'Lama',
            'origin_contact_phone' => '081200000000',
            'origin_area_id' => self::AREA_ID,
            'origin_area_name' => self::AREA_NAME,
            'origin_postal_code' => '12110',
            'origin_address' => 'Alamat lama',
        ]);

        Cache::flush();

        Livewire::actingAs($this->admin())
            ->test(StoreSettings::class)
            ->assertFormSet(['origin_area_id' => self::AREA_ID])
            ->fillForm($this->validForm(['origin_contact_name' => 'Baru']))
            ->call('save')
            ->assertHasNoFormErrors();

        $setting = StoreSetting::current();

        $this->assertSame('Baru', $setting->origin_contact_name);
        $this->assertSame(self::AREA_NAME, $setting->origin_area_name);
        $this->assertSame(1, StoreSetting::query()->count());
    }

    public function test_invalid_postal_code_and_phone_are_rejected(): void
    {
        $this->primeAreaCache();

        Livewire::actingAs($this->admin())
            ->test(StoreSettings::class)
            ->fillForm($this->validForm([
                'origin_postal_code' => '12A',
                'origin_contact_phone' => 'abc',
            ]))
            ->call('save')
            ->assertHasFormErrors(['origin_postal_code', 'origin_contact_phone']);

        $this->assertSame(0, StoreSetting::query()->count());
    }
}
