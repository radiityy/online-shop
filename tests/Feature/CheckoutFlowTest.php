<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
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
            'services.biteship.insurance' => true,
        ]);

        Cache::flush();
    }

    public function test_checkout_creates_order_with_server_side_prices(): void
    {
        $this->fakeRates();
        $this->createOrigin();

        $user = $this->customer();
        $item = $this->createCartItemForUser($user, quantity: 2);
        $address = $this->createAddress($user, self::DESTINATION_AREA);

        $response = $this->actingAs($user)->post('/checkout', $this->payload($address) + [
            'shipping_cost' => 1,
            'total' => 1,
        ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();

        $response->assertRedirect(route('orders.show', $order->order_code));

        $this->assertSame(300000.0, (float) $order->subtotal);
        $this->assertSame(16000.0, (float) $order->shipping_cost);
        $this->assertSame(1000.0, (float) $order->shipping_insurance_fee);
        $this->assertSame(317000.0, (float) $order->total);
        $this->assertSame('jnt', $order->shipping_courier_code);
        $this->assertSame('ez', $order->shipping_service_code);
        $this->assertSame($address->id, $order->address_id);

        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'quantity' => 2]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseHas('shipments', ['order_id' => $order->id, 'courier_code' => 'jnt']);
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
        $this->assertSame(8, $item->variant()->first()->stock);
    }

    public function test_checkout_rejects_a_courier_that_is_not_offered(): void
    {
        $this->fakeRates();
        $this->createOrigin();

        $user = $this->customer();
        $this->createCartItemForUser($user);
        $address = $this->createAddress($user, self::DESTINATION_AREA);

        $this->actingAs($user)
            ->post('/checkout', $this->payload($address, ['shipping_courier_code' => 'sicepat']))
            ->assertSessionHasErrors('shipping_option');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_rejects_when_the_bag_changes_during_the_rate_check(): void
    {
        $this->createOrigin();

        $user = $this->customer();
        $item = $this->createCartItemForUser($user, quantity: 2);
        $address = $this->createAddress($user, self::DESTINATION_AREA);

        Http::fake(function () use ($item) {
            CartItem::query()->whereKey($item->id)->update(['quantity' => 1]);

            return Http::response($this->ratesResponse());
        });

        $this->actingAs($user)
            ->post('/checkout', $this->payload($address))
            ->assertSessionHas('error', 'Isi bag berubah. Silakan pilih ulang pengiriman.');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $item->variant()->first()->stock);
    }

    public function test_checkout_rejects_an_address_without_area(): void
    {
        Http::fake();
        $this->createOrigin();

        $user = $this->customer();
        $this->createCartItemForUser($user);
        $address = $this->createAddress($user, null);

        $this->actingAs($user)
            ->post('/checkout', $this->payload($address))
            ->assertSessionHasErrors('address_id');

        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_checkout_requires_terms_to_be_accepted(): void
    {
        Http::fake();
        $this->createOrigin();

        $user = $this->customer();
        $this->createCartItemForUser($user);
        $address = $this->createAddress($user, self::DESTINATION_AREA);

        $this->actingAs($user)
            ->post('/checkout', $this->payload($address, ['agree_terms' => false]))
            ->assertSessionHasErrors('agree_terms');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_rejects_another_customers_address(): void
    {
        Http::fake();
        $this->createOrigin();

        $owner = $this->customer();
        $intruder = $this->customer();
        $this->createCartItemForUser($intruder);
        $address = $this->createAddress($owner, self::DESTINATION_AREA);

        $this->actingAs($intruder)
            ->post('/checkout', $this->payload($address))
            ->assertSessionHasErrors('address_id');

        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_checkout_requires_a_saved_address(): void
    {
        Http::fake();
        $this->createOrigin();

        $user = $this->customer();
        $this->createCartItemForUser($user);

        $this->actingAs($user)
            ->post('/checkout', [
                'recipient_name' => 'Manual Name',
                'phone' => '08123456789',
                'area_id' => self::DESTINATION_AREA,
                'full_address' => 'Jl. Manual No. 1',
                'shipping_courier_code' => 'jnt',
                'shipping_service_code' => 'ez',
                'agree_terms' => true,
            ])
            ->assertSessionHasErrors('address_id');

        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_checkout_with_an_empty_bag_creates_no_order(): void
    {
        Http::fake();
        $this->createOrigin();

        $user = $this->customer();
        $address = $this->createAddress($user, self::DESTINATION_AREA);

        $this->actingAs($user)
            ->post('/checkout', $this->payload($address))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
    }

    private function payload(Address $address, array $overrides = []): array
    {
        return array_merge([
            'address_id' => $address->id,
            'notes' => 'Tolong dibungkus rapi.',
            'shipping_courier_code' => 'jnt',
            'shipping_service_code' => 'ez',
            'agree_terms' => true,
        ], $overrides);
    }

    private function ratesResponse(): array
    {
        return [
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
                [
                    'courier_code' => 'jne',
                    'courier_name' => 'JNE',
                    'courier_service_code' => 'reg',
                    'courier_service_name' => 'Reguler',
                    'description' => 'Reguler',
                    'duration' => '3 - 6 days',
                    'insurance_fee' => 500,
                    'price' => 12500,
                ],
            ],
        ];
    }

    private function fakeRates(): void
    {
        Http::fake([
            'api.biteship.com/v1/rates/couriers' => Http::response($this->ratesResponse()),
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
            'full_address' => 'Jl. Test No. 1, RT 01 RW 02',
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
            'name' => 'NEVERENDING Checkout Test Tee',
            'slug' => 'neverending-checkout-test-tee-' . Str::lower(Str::random(6)),
            'description' => 'Checkout test product.',
            'price' => 150000,
            'sale_price' => null,
            'weight' => $weight,
            'is_active' => true,
        ]);

        $variant = ProductVariant::forceCreate([
            'product_id' => $product->id,
            'size' => 'M',
            'color' => null,
            'sku' => 'CHK-' . Str::upper(Str::random(6)),
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