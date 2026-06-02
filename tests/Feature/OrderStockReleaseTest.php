<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderStockReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_is_restored_when_payment_failed(): void
    {
        [$order, $variant] = $this->createOrderWithReservedStock();

        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertFalse($order->fresh()->stock_released);

        $order->update([
            'payment_status' => 'failed',
        ]);

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertTrue($order->fresh()->stock_released);
    }

    public function test_stock_is_restored_when_order_cancelled(): void
    {
        [$order, $variant] = $this->createOrderWithReservedStock();

        $this->assertSame(3, $variant->fresh()->stock);

        $order->update([
            'order_status' => 'cancelled',
        ]);

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertTrue($order->fresh()->stock_released);
    }

    public function test_stock_is_not_restored_twice(): void
    {
        [$order, $variant] = $this->createOrderWithReservedStock();

        $order->update([
            'payment_status' => 'failed',
        ]);

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertTrue($order->fresh()->stock_released);

        $order->update([
            'order_status' => 'cancelled',
        ]);

        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_stock_is_not_restored_for_paid_order(): void
    {
        [$order, $variant] = $this->createOrderWithReservedStock();

        $order->update([
            'payment_status' => 'paid',
            'order_status' => 'processing',
        ]);

        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertFalse($order->fresh()->stock_released);
    }

    private function createOrderWithReservedStock(): array
    {
        $user = User::factory()->create();

        $category = Category::forceCreate([
            'name' => 'Test Tees',
            'slug' => 'test-tees-' . Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        $product = Product::forceCreate([
            'category_id' => $category->id,
            'name' => 'NEVERENDING Stock Test Tee',
            'slug' => 'neverending-stock-test-tee-' . Str::lower(Str::random(6)),
            'description' => 'Stock release test product.',
            'price' => 150000,
            'weight' => 300,
            'is_active' => true,
        ]);

        $variant = ProductVariant::forceCreate([
            'product_id' => $product->id,
            'size' => 'M',
            'color' => null,
            'sku' => 'STOCK-' . Str::upper(Str::random(6)),
            'stock' => 3,
            'additional_price' => 0,
            'is_active' => true,
        ]);

        $order = Order::forceCreate([
            'user_id' => $user->id,
            'order_code' => 'NE-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'recipient_name' => 'Test Customer',
            'phone' => '08123456789',
            'province' => 'DIY',
            'city' => 'Yogyakarta',
            'district' => 'Depok',
            'postal_code' => '55281',
            'full_address' => 'Jl. Testing No. 1',
            'subtotal' => 300000,
            'shipping_cost' => 15000,
            'total' => 315000,
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'shipping_status' => 'not_shipped',
            'stock_released' => false,
            'notes' => null,
        ]);

        OrderItem::forceCreate([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->size,
            'sku' => $variant->sku,
            'price' => 150000,
            'quantity' => 2,
            'subtotal' => 300000,
        ]);

        return [$order, $variant];
    }
}