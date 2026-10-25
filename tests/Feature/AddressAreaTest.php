<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use App\Services\BiteshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AddressAreaTest extends TestCase
{
    use RefreshDatabase;

    private const AREA_A = 'IDNP6IDNC148IDND836IDZ12110';

    private const AREA_B = 'IDNP6IDNC148IDND843IDZ12250';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'services.biteship.key' => 'biteship_test.fake-key',
            'services.biteship.base_url' => 'https://api.biteship.com',
        ]);
    }

    private function primeAreas(): void
    {
        Http::fake([
            'api.biteship.com/v1/maps/areas*' => Http::response([
                'success' => true,
                'areas' => [
                    [
                        'id' => self::AREA_A,
                        'name' => 'Kebayoran Baru, Jakarta Selatan, DKI Jakarta. 12110',
                        'administrative_division_level_1_name' => 'DKI Jakarta',
                        'administrative_division_level_2_name' => 'Jakarta Selatan',
                        'administrative_division_level_3_name' => 'Kebayoran Baru',
                        'postal_code' => 12110,
                    ],
                    [
                        'id' => self::AREA_B,
                        'name' => 'Pesanggrahan, Jakarta Selatan, DKI Jakarta. 12250',
                        'administrative_division_level_1_name' => 'DKI Jakarta',
                        'administrative_division_level_2_name' => 'Jakarta Selatan',
                        'administrative_division_level_3_name' => 'Pesanggrahan',
                        'postal_code' => 12250,
                    ],
                ],
            ]),
        ]);

        app(BiteshipService::class)->searchAreas('jakarta selatan');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'recipient_name' => 'Budi',
            'phone' => '081234567890',
            'area_id' => self::AREA_A,
            'postal_code' => '',
            'full_address' => 'Jl. Contoh No. 1',
            'is_default' => false,
        ], $overrides);
    }

    private function legacyAddress(User $user, array $overrides = []): Address
    {
        return Address::query()->create(array_merge([
            'user_id' => $user->id,
            'recipient_name' => 'Lama',
            'phone' => '081200000000',
            'province' => 'Banten',
            'city' => 'Tangerang',
            'district' => 'Karawaci',
            'postal_code' => '15113',
            'full_address' => 'Alamat lama',
            'is_default' => true,
        ], $overrides));
    }

    public function test_store_takes_area_fields_from_server_not_from_browser(): void
    {
        $this->primeAreas();
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)
            ->post('/account/addresses', $this->payload([
                'province' => 'Diinjeksi',
                'city' => 'Diinjeksi',
                'district' => 'Diinjeksi',
                'area_name' => 'Diinjeksi',
            ]))
            ->assertSessionHasNoErrors();

        $address = Address::query()->where('user_id', $user->id)->sole();

        $this->assertSame(self::AREA_A, $address->area_id);
        $this->assertSame('Kebayoran Baru, Jakarta Selatan, DKI Jakarta. 12110', $address->area_name);
        $this->assertSame('DKI Jakarta', $address->province);
        $this->assertSame('Jakarta Selatan', $address->city);
        $this->assertSame('Kebayoran Baru', $address->district);
        $this->assertTrue($address->is_default);
    }

    public function test_blank_postal_code_falls_back_to_area_postal_code(): void
    {
        $this->primeAreas();
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)
            ->post('/account/addresses', $this->payload(['postal_code' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame('12110', Address::query()->where('user_id', $user->id)->sole()->postal_code);
    }

    public function test_store_rejects_area_that_did_not_come_from_search(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)
            ->post('/account/addresses', $this->payload(['area_id' => 'ID-TIDAK-ADA']))
            ->assertSessionHasErrors('area_id');

        $this->assertSame(0, Address::query()->count());
    }

    public function test_store_requires_area(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)
            ->post('/account/addresses', $this->payload(['area_id' => '']))
            ->assertSessionHasErrors('area_id');

        $this->assertSame(0, Address::query()->count());
    }

    public function test_full_address_is_limited_to_500_characters(): void
    {
        $this->primeAreas();
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)
            ->post('/account/addresses', $this->payload(['full_address' => str_repeat('a', 501)]))
            ->assertSessionHasErrors('full_address');

        $this->actingAs($user)
            ->post('/account/addresses', $this->payload(['full_address' => str_repeat('a', 500)]))
            ->assertSessionHasNoErrors();
    }

    public function test_postal_code_must_be_five_digits(): void
    {
        $this->primeAreas();
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)
            ->post('/account/addresses', $this->payload(['postal_code' => '12A45']))
            ->assertSessionHasErrors('postal_code');
    }

    public function test_update_with_same_area_keeps_area_even_after_cache_expires(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $address = $this->legacyAddress($user, [
            'province' => 'DKI Jakarta',
            'city' => 'Jakarta Selatan',
            'district' => 'Kebayoran Baru',
            'area_id' => self::AREA_A,
            'area_name' => 'Kebayoran Baru, Jakarta Selatan, DKI Jakarta. 12110',
            'postal_code' => '12110',
        ]);

        $this->actingAs($user)
            ->put("/account/addresses/{$address->id}", $this->payload([
                'recipient_name' => 'Nama Baru',
                'is_default' => true,
            ]))
            ->assertSessionHasNoErrors();

        $address->refresh();

        $this->assertSame('Nama Baru', $address->recipient_name);
        $this->assertSame(self::AREA_A, $address->area_id);
        $this->assertSame('Kebayoran Baru', $address->district);
        $this->assertSame('12110', $address->postal_code);
    }

    public function test_update_with_new_area_replaces_area_fields(): void
    {
        $this->primeAreas();
        $user = User::factory()->create(['role' => 'customer']);
        $address = $this->legacyAddress($user, ['area_id' => self::AREA_A]);

        $this->actingAs($user)
            ->put("/account/addresses/{$address->id}", $this->payload([
                'area_id' => self::AREA_B,
                'is_default' => true,
            ]))
            ->assertSessionHasNoErrors();

        $address->refresh();

        $this->assertSame(self::AREA_B, $address->area_id);
        $this->assertSame('Pesanggrahan', $address->district);
        $this->assertSame('12250', $address->postal_code);
    }

    public function test_legacy_address_without_area_must_pick_a_valid_area_on_update(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $address = $this->legacyAddress($user);

        $this->actingAs($user)
            ->put("/account/addresses/{$address->id}", $this->payload(['area_id' => '']))
            ->assertSessionHasErrors('area_id');

        $this->actingAs($user)
            ->put("/account/addresses/{$address->id}", $this->payload(['area_id' => 'ID-TIDAK-ADA']))
            ->assertSessionHasErrors('area_id');

        $this->assertNull($address->refresh()->area_id);
    }

    public function test_user_cannot_update_another_users_address(): void
    {
        $this->primeAreas();
        $owner = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        $address = $this->legacyAddress($owner);

        $this->actingAs($other)
            ->put("/account/addresses/{$address->id}", $this->payload())
            ->assertForbidden();

        $this->assertSame('Lama', $address->refresh()->recipient_name);
    }

    public function test_index_exposes_area_fields(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->legacyAddress($user, [
            'area_id' => self::AREA_A,
            'area_name' => 'Kebayoran Baru, Jakarta Selatan, DKI Jakarta. 12110',
        ]);

        $this->actingAs($user)
            ->get('/account/addresses')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Account/Addresses/Index')
                ->where('addresses.0.area_id', self::AREA_A)
                ->where('addresses.0.area_name', 'Kebayoran Baru, Jakarta Selatan, DKI Jakarta. 12110'));
    }
}