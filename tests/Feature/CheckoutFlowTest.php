<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
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
            'services.midtrans.server_key' => 'Mid-server-test-key',
            'services.midtrans.is_production' => false,
            'services.midtrans.expiry_minutes' => 60,
            'services.midtrans.methods' => [
                'bca_va' => [
                    'label' => 'BCA Virtual Account',
                    'payment_type' => 'bank_transfer',
                    'bank' => 'bca',
                    'fee_flat' => 4000,
                    'fee_percent' => 0,
                    'enabled' => true,
                ],
                'qris' => [
                    'label' => 'QRIS',
                    'payment_type' => 'qris',
                    'bank' => null,
                    'fee_flat' => 0,
                    'fee_percent' => 0.7,
                    'enabled' => true,
                ],
            ],
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
            'payment_fee' => 1,
            'total' => 1,
        ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();

        $response->assertRedirect(route('orders.show', $order->order_code));

        $this->assertSame(300000.0, (float) $order->subtotal);
        $this->assertSame(16000.0, (float) $order->shipping_cost);
        $this->assertSame(1000.0, (float) $order->shipping_insurance_fee);
        $this->assertSame(4000.0, (float) $order->payment_fee);
        $this->assertSame(321000.0, (float) $order->total);
        $this->assertSame('jnt', $order->shipping_courier_code);
        $this->assertSame('ez', $order->shipping_service_code);
        $this->assertSame($address->id, $order->address_id);
        $this->assertNotNull($order->payment_expires_at);

        $payment = $order->payments()->firstOrFail();

        $this->assertSame('bca_va', $payment->payment_method);
        $this->assertSame($order->order_code . '-1', $payment->gateway_order_id);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame('95073296922772990300925', $payment->va_number);
        $this->assertSame(321000.0, (float) $payment->amount);
        $this->assertSame(4000.0, (float) $payment->fee);

        Http::assertSent(function ($request) use ($order) {
            $data = $request->data();

            return str_ends_with($request->url(), '/v2/charge')
                && $data['payment_type'] === 'bank_transfer'
                && $data['bank_transfer']['bank'] === 'bca'
                && $data['transaction_details']['order_id'] === $order->order_code . '-1'
                && $data['transaction_details']['gross_amount'] === 321000
                && in_array($data['custom_expiry']['expiry_duration'], [59, 60], true);
        });

        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'quantity' => 2]);
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
                'payment_method' => 'bca_va',
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

    public function test_checkout_requires_a_payment_method(): void
    {
        Http::fake();
        $this->createOrigin();

        $user = $this->customer();
        $this->createCartItemForUser($user);
        $address = $this->createAddress($user, self::DESTINATION_AREA);

        $payload = $this->payload($address);
        unset($payload['payment_method']);

        $this->actingAs($user)
            ->post('/checkout', $payload)
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_checkout_rejects_a_payment_method_that_is_not_offered(): void
    {
        Http::fake();
        $this->createOrigin();

        $user = $this->customer();
        $this->createCartItemForUser($user);
        $address = $this->createAddress($user, self::DESTINATION_AREA);

        $this->actingAs($user)
            ->post('/checkout', $this->payload($address, ['payment_method' => 'bitcoin']))
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_checkout_keeps_the_order_without_a_fee_when_the_gateway_rejects_the_charge(): void
    {
        $this->assertOrderSurvivesGatewayFailure(
            Http::response([
                'status_code' => '401',
                'status_message' => 'Access denied due to unauthorized transaction, please check client/server key',
            ], 401)
        );
    }

    public function test_checkout_keeps_the_order_without_a_fee_when_the_gateway_is_unreachable(): void
    {
        $this->assertOrderSurvivesGatewayFailure(
            fn () => throw new ConnectionException('Connection timed out')
        );
    }

    private function assertOrderSurvivesGatewayFailure(mixed $midtransStub): void
    {
        Http::fake([
            'api.biteship.com/v1/rates/couriers' => Http::response($this->ratesResponse()),
            'api.sandbox.midtrans.com/*' => $midtransStub,
        ]);
        $this->createOrigin();

        $user = $this->customer();
        $item = $this->createCartItemForUser($user, quantity: 2);
        $address = $this->createAddress($user, self::DESTINATION_AREA);

        $response = $this->actingAs($user)->post('/checkout', $this->payload($address));

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();

        $response->assertRedirect(route('orders.show', $order->order_code));
        $response->assertSessionHas('error');

        $this->assertSame(0.0, (float) $order->payment_fee);
        $this->assertSame(317000.0, (float) $order->total);
        $this->assertDatabaseMissing('payments', ['order_id' => $order->id]);
        $this->assertSame(8, $item->variant()->first()->stock);
    }

    private function payload(Address $address, array $overrides = []): array
    {
        return array_merge([
            'address_id' => $address->id,
            'notes' => 'Tolong dibungkus rapi.',
            'shipping_courier_code' => 'jnt',
            'shipping_service_code' => 'ez',
            'payment_method' => 'bca_va',
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
            'api.sandbox.midtrans.com/v2/charge' => Http::response($this->chargeResponse()),
        ]);
    }

    private function chargeResponse(): array
    {
        return [
            'status_code' => '201',
            'status_message' => 'Success, Bank Transfer transaction is created',
            'transaction_id' => 'fd391aca-6af0-4179-8bb9-46f8d9a488c5',
            'transaction_status' => 'pending',
            'fraud_status' => 'accept',
            'payment_type' => 'bank_transfer',
            'va_numbers' => [
                ['bank' => 'bca', 'va_number' => '95073296922772990300925'],
            ],
            'expiry_time' => '2026-10-25 13:05:59',
        ];
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