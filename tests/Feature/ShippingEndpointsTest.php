<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShippingEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN_AREA = 'IDNP6IDNC148IDND836IDZ12410';

    private const DESTINATION_AREA = 'IDNP6IDNC148IDND843IDZ12250';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.biteship.key' => 'biteship_test.fake-key',
            'services.biteship.base_url' => 'https://api.biteship.com',
            'services.biteship.couriers' => 'jnt,jne',
            'services.biteship.preferred_courier' => 'jnt',
            'services.biteship.insurance' => false,
        ]);

        Cache::flush();
    }

    public function test_guest_cannot_search_areas(): void
    {
        Http::fake();

        $this->getJson('/shipping/areas?q=pesanggrahan')->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_guest_cannot_request_rates(): void
    {
        Http::fake();

        $this->postJson('/shipping/rates', ['area_id' => self::DESTINATION_AREA])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_area_search_requires_at_least_three_characters(): void
    {
        Http::fake();

        $this->actingAs($this->customer())
            ->getJson('/shipping/areas?q=ja')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');

        Http::assertNothingSent();
    }

    public function test_area_search_returns_normalized_areas_and_sends_the_api_key(): void
    {
        $this->fakeBiteship();

        $this->actingAs($this->customer())
            ->getJson('/shipping/areas?q=Pesanggrahan')
            ->assertOk()
            ->assertJsonCount(1, 'areas')
            ->assertJsonPath('areas.0.id', self::DESTINATION_AREA)
            ->assertJsonPath('areas.0.province', 'DKI Jakarta')
            ->assertJsonPath('areas.0.city', 'Jakarta Selatan')
            ->assertJsonPath('areas.0.district', 'Pesanggrahan')
            ->assertJsonPath('areas.0.postal_code', '12250');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v1/maps/areas')
            && $request->hasHeader('authorization', 'biteship_test.fake-key')
            && $request['input'] === 'Pesanggrahan'
            && $request['type'] === 'single'
            && $request['countries'] === 'ID');
    }

    public function test_repeated_area_search_is_served_from_cache(): void
    {
        $this->fakeBiteship();

        $user = $this->customer();

        $this->actingAs($user)->getJson('/shipping/areas?q=Pesanggrahan')->assertOk();
        $this->actingAs($user)->getJson('/shipping/areas?q=pesanggrahan')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_area_search_failure_does_not_leak_provider_details(): void
    {
        Http::fake([
            'api.biteship.com/v1/maps/areas*' => Http::response([
                'success' => false,
                'error' => 'internal-provider-detail',
                'code' => 50000000,
            ], 500),
        ]);

        $this->actingAs($this->customer())
            ->getJson('/shipping/areas?q=Pesanggrahan')
            ->assertStatus(503)
            ->assertJsonPath('message', 'Ongkir belum bisa dihitung saat ini. Coba lagi beberapa saat.')
            ->assertDontSee('internal-provider-detail');
    }

    public function test_rates_use_cart_weight_origin_and_put_preferred_courier_first(): void
    {
        $this->fakeBiteship();
        $this->createOrigin();

        $user = $this->customer();
        $this->createCartItemForUser($user, weight: 300, quantity: 2);

        $this->actingAs($user)->getJson('/shipping/areas?q=Pesanggrahan')->assertOk();

        $this->actingAs($user)
            ->postJson('/shipping/rates', ['area_id' => self::DESTINATION_AREA])
            ->assertOk()
            ->assertJsonCount(2, 'options')
            ->assertJsonPath('options.0.courier_code', 'jnt')
            ->assertJsonPath('options.0.key', 'jnt:ez')
            ->assertJsonPath('options.0.total', 16000)
            ->assertJsonPath('options.1.courier_code', 'jne')
            ->assertJsonPath('options.1.total', 12000);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v1/rates/couriers')
            && $request['origin_area_id'] === self::ORIGIN_AREA
            && $request['destination_area_id'] === self::DESTINATION_AREA
            && $request['couriers'] === 'jnt,jne'
            && $request['items'][0]['weight'] === 300
            && $request['items'][0]['quantity'] === 2
            && $request['items'][0]['value'] === 150000
            && ! isset($request['courier_insurance']));
    }

    public function test_rates_separate_insurance_fee_when_insurance_is_enabled(): void
    {
        config(['services.biteship.insurance' => true]);

        Http::fake([
            'api.biteship.com/v1/rates/couriers' => Http::response([
                'success' => true,
                'pricing' => [
                    [
                        'courier_code' => 'jnt',
                        'courier_name' => 'J&T Express',
                        'courier_service_code' => 'ez',
                        'courier_service_name' => 'EZ',
                        'description' => 'Reguler',
                        'duration' => '2 - 3 days',
                        'insurance_fee' => 1000,
                        'price' => 17000,
                    ],
                ],
            ]),
        ]);

        $this->createOrigin();

        $user = $this->customer();
        $this->createCartItemForUser($user, weight: 300, quantity: 2);
        $address = $this->createAddress($user, self::DESTINATION_AREA);

        $this->actingAs($user)
            ->postJson('/shipping/rates', ['address_id' => $address->id])
            ->assertOk()
            ->assertJsonPath('options.0.shipping_cost', 16000)
            ->assertJsonPath('options.0.insurance_fee', 1000)
            ->assertJsonPath('options.0.total', 17000);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v1/rates/couriers')
            && $request['courier_insurance'] === 300000);
    }

    public function test_rates_for_saved_address_use_the_stored_area(): void
    {
        $this->fakeBiteship();
        $this->createOrigin();

        $user = $this->customer();
        $this->createCartItemForUser($user);
        $address = $this->createAddress($user, self::DESTINATION_AREA);

        $this->actingAs($user)
            ->postJson('/shipping/rates', ['address_id' => $address->id])
            ->assertOk()
            ->assertJsonCount(2, 'options');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v1/rates/couriers')
            && $request['destination_area_id'] === self::DESTINATION_AREA);
    }

    public function test_rates_reject_an_address_without_area(): void
    {
        Http::fake();
        $this->createOrigin();

        $user = $this->customer();
        $this->createCartItemForUser($user);
        $address = $this->createAddress($user, null);

        $this->actingAs($user)
            ->postJson('/shipping/rates', ['address_id' => $address->id])
            ->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_rates_reject_another_customers_address(): void
    {
        Http::fake();
        $this->createOrigin();

        $owner = $this->customer();
        $intruder = $this->customer();
        $this->createCartItemForUser($intruder);
        $address = $this->createAddress($owner, self::DESTINATION_AREA);

        $this->actingAs($intruder)
            ->postJson('/shipping/rates', ['address_id' => $address->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('address_id');

        Http::assertNothingSent();
    }

    public function test_rates_reject_an_area_that_was_never_returned_by_search(): void
    {
        Http::fake();
        $this->createOrigin();

        $user = $this->customer();
        $this->createCartItemForUser($user);

        $this->actingAs($user)
            ->postJson('/shipping/rates', ['area_id' => 'IDNP1IDNC1IDND1IDZ99999'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Area pengiriman tidak valid. Silakan pilih ulang area.');

        Http::assertNothingSent();
    }

    public function test_rates_require_a_destination(): void
    {
        Http::fake();

        $this->actingAs($this->customer())
            ->postJson('/shipping/rates', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address_id', 'area_id']);

        Http::assertNothingSent();
    }

    public function test_rates_fail_when_the_store_origin_is_not_set(): void
    {
        Http::fake();

        $user = $this->customer();
        $this->createCartItemForUser($user);
        $address = $this->createAddress($user, self::DESTINATION_AREA);

        $this->actingAs($user)
            ->postJson('/shipping/rates', ['address_id' => $address->id])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Pengiriman belum tersedia. Silakan hubungi toko.');

        Http::assertNothingSent();
    }

    public function test_rates_reject_products_without_weight(): void
    {
        Http::fake();
        $this->createOrigin();

        $user = $this->customer();
        $this->createCartItemForUser($user, weight: 0);
        $address = $this->createAddress($user, self::DESTINATION_AREA);

        $this->actingAs($user)
            ->postJson('/shipping/rates', ['address_id' => $address->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ada produk yang beratnya belum diatur. Silakan hubungi toko.');

        Http::assertNothingSent();
    }

    public function test_rates_reject_an_empty_cart(): void
    {
        Http::fake();
        $this->createOrigin();

        $user = $this->customer();
        $address = $this->createAddress($user, self::DESTINATION_AREA);

        $this->actingAs($user)
            ->postJson('/shipping/rates', ['address_id' => $address->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Bag masih kosong.');

        Http::assertNothingSent();
    }

    private function fakeBiteship(): void
    {
        Http::fake([
            'api.biteship.com/v1/maps/areas*' => Http::response([
                'success' => true,
                'areas' => [
                    [
                        'id' => self::DESTINATION_AREA,
                        'name' => 'Pesanggrahan, Jakarta Selatan, DKI Jakarta. 12250',
                        'country_name' => 'Indonesia',
                        'country_code' => 'ID',
                        'administrative_division_level_1_name' => 'DKI Jakarta',
                        'administrative_division_level_2_name' => 'Jakarta Selatan',
                        'administrative_division_level_3_name' => 'Pesanggrahan',
                        'postal_code' => 12250,
                    ],
                ],
            ]),
            'api.biteship.com/v1/rates/couriers' => Http::response([
                'success' => true,
                'pricing' => [
                    [
                        'courier_code' => 'jne',
                        'courier_name' => 'JNE',
                        'courier_service_code' => 'reg',
                        'courier_service_name' => 'Reguler',
                        'description' => 'Layanan reguler',
                        'duration' => '2 - 3 days',
                        'insurance_fee' => 0,
                        'price' => 12000,
                    ],
                    [
                        'courier_code' => 'jnt',
                        'courier_name' => 'J&T Express',
                        'courier_service_code' => 'ez',
                        'courier_service_name' => 'EZ',
                        'description' => 'Layanan reguler',
                        'duration' => '2 - 3 days',
                        'insurance_fee' => 0,
                        'price' => 16000,
                    ],
                ],
            ]),
        ]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    private function createOrigin(): StoreSetting
    {
        return StoreSetting::forceCreate([
            'origin_area_id' => self::ORIGIN_AREA,
            'origin_area_name' => 'Origin Test Area',
        ]);
    }

    private function createAddress(User $user, ?string $areaId): Address
    {
        return Address::forceCreate([
            'user_id' => $user->id,
            'recipient_name' => 'Test Recipient',
            'phone' => '08123456789',
            'province' => 'DKI Jakarta',
            'city' => 'Jakarta Selatan',
            'district' => 'Pesanggrahan',
            'area_id' => $areaId,
            'postal_code' => '12250',
            'full_address' => 'Jl. Test No. 1',
            'is_default' => false,
        ]);
    }

    private function createCartItemForUser(User $user, int $weight = 300, int $quantity = 2): CartItem
    {
        $category = Category::forceCreate([
            'name' => 'Test Category',
            'slug' => 'test-category-' . Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        $product = Product::forceCreate([
            'category_id' => $category->id,
            'name' => 'NEVERENDING Shipping Test Tee',
            'slug' => 'neverending-shipping-test-tee-' . Str::lower(Str::random(6)),
            'description' => 'Shipping test product.',
            'price' => 150000,
            'sale_price' => null,
            'weight' => $weight,
            'is_active' => true,
        ]);

        $variant = ProductVariant::forceCreate([
            'product_id' => $product->id,
            'size' => 'M',
            'color' => null,
            'sku' => 'SHP-' . Str::upper(Str::random(6)),
            'stock' => 10,
            'additional_price' => 0,
            'is_active' => true,
        ]);

        $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);

        return CartItem::forceCreate([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
        ]);
    }
}
