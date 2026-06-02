<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderStatusSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_status_syncs_to_payment_record(): void
    {
        [$order] = $this->createOrderWithRelations();

        $order->update([
            'payment_status' => 'paid',
        ]);

        $this->assertSame('paid', $order->payment()->first()->status);
    }

    public function test_shipping_status_syncs_to_shipment_record(): void
    {
        [$order] = $this->createOrderWithRelations();

        $order->update([
            'shipping_status' => 'shipped',
        ]);

        $this->assertSame('shipped', $order->shipment()->first()->status);
    }

    public function test_stock_is_restored_when_order_cancelled(): void
    {
        [$order, $variant] = $this->createOrderWithRelations();

        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertFalse($order->fresh()->stock_released);

        $order->update([
            'order_status' => 'cancelled',
        ]);

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertTrue($order->fresh()->stock_released);
    }

    public function test_stock_is_restored_when_payment_failed(): void
    {
        [$order, $variant] = $this->createOrderWithRelations();

        $this->assertSame(3, $variant->fresh()->stock);

        $order->update([
            'payment_status' => 'failed',
        ]);

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertTrue($order->fresh()->stock_released);
        $this->assertSame('failed', $order->payment()->first()->status);
    }

    public function test_released_order_cannot_be_reactivated(): void
    {
        [$order, $variant] = $this->createOrderWithRelations();

        $order->update([
            'order_status' => 'cancelled',
        ]);

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertTrue($order->fresh()->stock_released);

        $this->expectException(ValidationException::class);

        $order->fresh()->update([
            'payment_status' => 'paid',
            'order_status' => 'processing',
        ]);
    }

    public function test_stock_is_not_restored_twice(): void
    {
        [$order, $variant] = $this->createOrderWithRelations();

        $order->update([
            'payment_status' => 'failed',
        ]);

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertTrue($order->fresh()->stock_released);

        $order->fresh()->update([
            'payment_status' => 'refunded',
            'order_status' => 'cancelled',
        ]);

        $this->assertSame(5, $variant->fresh()->stock);
    }

    private function createOrderWithRelations(): array
    {
        $user = User::factory()->create();

        $category = Category::forceCreate([
            'name' => 'Test Tees',
            'slug' => 'test-tees-' . Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        $product = Product::forceCreate([
            'category_id' => $category->id,
            'name' => 'NEVERENDING Status Safety Tee',
            'slug' => 'neverending-status-safety-tee-' . Str::lower(Str::random(6)),
            'description' => 'Order safety test product.',
            'price' => 150000,
            'weight' => 300,
            'is_active' => true,
        ]);

        $variant = ProductVariant::forceCreate([
            'product_id' => $product->id,
            'size' => 'M',
            'color' => null,
            'sku' => 'SAFE-' . Str::upper(Str::random(6)),
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

        Payment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'manual_transfer',
            'amount' => 315000,
            'status' => 'pending',
            'proof_image' => null,
        ]);

        Shipment::forceCreate([
            'order_id' => $order->id,
            'courier' => null,
            'service' => null,
            'tracking_number' => null,
            'status' => 'not_shipped',
        ]);

        return [$order, $variant];
    }
}