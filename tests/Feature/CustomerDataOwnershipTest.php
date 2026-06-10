<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerDataOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_view_other_customer_order_detail(): void
    {
        [$owner, $otherUser] = $this->createTwoUsers();

        $order = $this->createOrderForUser($owner);

        $this->actingAs($otherUser)
            ->get('/orders/' . $order->order_code)
            ->assertNotFound();
    }

    public function test_customer_cannot_update_other_customer_address(): void
    {
        [$owner, $otherUser] = $this->createTwoUsers();

        $address = $this->createAddressForUser($owner);

        $this->actingAs($otherUser)
            ->put('/account/addresses/' . $address->id, [
                'recipient_name' => 'Hacker Name',
                'phone' => '081111111111',
                'province' => 'DIY',
                'city' => 'Yogyakarta',
                'district' => 'Depok',
                'postal_code' => '55281',
                'full_address' => 'Alamat tidak valid',
                'is_default' => true,
            ])
            ->assertForbidden();

        $this->assertSame('Owner Name', $address->fresh()->recipient_name);
    }

    public function test_customer_cannot_delete_other_customer_address(): void
    {
        [$owner, $otherUser] = $this->createTwoUsers();

        $address = $this->createAddressForUser($owner);

        $this->actingAs($otherUser)
            ->delete('/account/addresses/' . $address->id)
            ->assertForbidden();

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_customer_cannot_set_other_customer_address_as_default(): void
    {
        [$owner, $otherUser] = $this->createTwoUsers();

        $address = $this->createAddressForUser($owner);

        $this->actingAs($otherUser)
            ->patch('/account/addresses/' . $address->id . '/default')
            ->assertForbidden();

        $this->assertFalse((bool) $address->fresh()->is_default);
    }

    public function test_customer_cannot_update_other_customer_cart_item(): void
    {
        [$owner, $otherUser] = $this->createTwoUsers();

        $cartItem = $this->createCartItemForUser($owner);

        $this->actingAs($otherUser)
            ->patch('/cart/items/' . $cartItem->id, [
                'quantity' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(2, $cartItem->fresh()->quantity);
    }

    public function test_customer_cannot_delete_other_customer_cart_item(): void
    {
        [$owner, $otherUser] = $this->createTwoUsers();

        $cartItem = $this->createCartItemForUser($owner);

        $this->actingAs($otherUser)
            ->delete('/cart/items/' . $cartItem->id)
            ->assertForbidden();

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
        ]);
    }

    private function createTwoUsers(): array
    {
        return [
            User::factory()->create([
                'role' => 'customer',
            ]),
            User::factory()->create([
                'role' => 'customer',
            ]),
        ];
    }

    private function createAddressForUser(User $user): Address
    {
        return Address::forceCreate([
            'user_id' => $user->id,
            'recipient_name' => 'Owner Name',
            'phone' => '08123456789',
            'province' => 'DIY',
            'city' => 'Yogyakarta',
            'district' => 'Depok',
            'postal_code' => '55281',
            'full_address' => 'Jl. Owner No. 1',
            'is_default' => false,
        ]);
    }

    private function createOrderForUser(User $user): Order
    {
        return Order::forceCreate([
            'user_id' => $user->id,
            'order_code' => 'NE-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'recipient_name' => 'Owner Name',
            'phone' => '08123456789',
            'province' => 'DIY',
            'city' => 'Yogyakarta',
            'district' => 'Depok',
            'postal_code' => '55281',
            'full_address' => 'Jl. Owner No. 1',
            'subtotal' => 150000,
            'shipping_cost' => 15000,
            'total' => 165000,
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'shipping_status' => 'not_shipped',
            'stock_released' => false,
            'notes' => null,
        ]);
    }

    private function createCartItemForUser(User $user): CartItem
    {
        $category = Category::forceCreate([
            'name' => 'Test Category',
            'slug' => 'test-category-' . Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        $product = Product::forceCreate([
            'category_id' => $category->id,
            'name' => 'NEVERENDING Security Test Tee',
            'slug' => 'neverending-security-test-tee-' . Str::lower(Str::random(6)),
            'description' => 'Security test product.',
            'price' => 150000,
            'sale_price' => null,
            'weight' => 300,
            'is_active' => true,
        ]);

        $variant = ProductVariant::forceCreate([
            'product_id' => $product->id,
            'size' => 'M',
            'color' => null,
            'sku' => 'SEC-' . Str::upper(Str::random(6)),
            'stock' => 5,
            'additional_price' => 0,
            'is_active' => true,
        ]);

        $cart = Cart::forceCreate([
            'user_id' => $user->id,
        ]);

        return CartItem::forceCreate([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);
    }
}