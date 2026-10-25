<?php

namespace Tests\Concerns;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Support\Str;

trait BuildsMidtransOrders
{
    private const BEFORE_FEE = 317000;

    protected function configureMidtrans(): void
    {
        config([
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
    }

    protected function createPendingOrder(string $methodKey = 'bca_va', ?User $user = null): array
    {
        $user ??= User::factory()->create(['role' => 'customer']);

        $category = Category::forceCreate([
            'name' => 'Test Category',
            'slug' => 'test-category-' . Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        $product = Product::forceCreate([
            'category_id' => $category->id,
            'name' => 'NEVERENDING Payment Test Tee',
            'slug' => 'neverending-payment-test-tee-' . Str::lower(Str::random(6)),
            'description' => 'Payment test product.',
            'price' => 150000,
            'sale_price' => null,
            'weight' => 300,
            'is_active' => true,
        ]);

        $variant = ProductVariant::forceCreate([
            'product_id' => $product->id,
            'size' => 'M',
            'color' => null,
            'sku' => 'PAY-' . Str::upper(Str::random(6)),
            'stock' => 8,
            'additional_price' => 0,
            'is_active' => true,
        ]);

        $address = Address::forceCreate([
            'user_id' => $user->id,
            'recipient_name' => 'Test Recipient',
            'phone' => '08123456789',
            'province' => 'DKI Jakarta',
            'city' => 'Jakarta Selatan',
            'district' => 'Pesanggrahan',
            'area_id' => 'IDNP6IDNC148IDND843IDZ12250',
            'postal_code' => '12250',
            'full_address' => 'Jl. Test No. 1, RT 01 RW 02',
            'is_default' => false,
        ]);

        $fee = app(MidtransService::class)->feeFor($methodKey, self::BEFORE_FEE);

        $order = Order::forceCreate([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'order_code' => 'NE-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
            'recipient_name' => 'Test Recipient',
            'phone' => '08123456789',
            'province' => 'DKI Jakarta',
            'city' => 'Jakarta Selatan',
            'district' => 'Pesanggrahan',
            'area_id' => 'IDNP6IDNC148IDND843IDZ12250',
            'postal_code' => '12250',
            'full_address' => 'Jl. Test No. 1, RT 01 RW 02',
            'subtotal' => 300000,
            'shipping_cost' => 16000,
            'shipping_insurance_fee' => 1000,
            'payment_fee' => $fee,
            'shipping_courier_code' => 'jnt',
            'shipping_courier_name' => 'J&T Express',
            'shipping_service_code' => 'ez',
            'shipping_service_name' => 'EZ',
            'shipping_duration' => '2 - 3 days',
            'total' => self::BEFORE_FEE + $fee,
            'payment_expires_at' => now()->addMinutes(60),
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'shipping_status' => 'not_shipped',
            'stock_released' => false,
        ]);

        OrderItem::forceCreate([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => 'M',
            'sku' => $variant->sku,
            'price' => 150000,
            'quantity' => 2,
            'subtotal' => 300000,
        ]);

        $payment = $this->createPayment($order, $methodKey, 1);

        return [
            'user' => $user,
            'order' => $order,
            'payment' => $payment,
            'variant' => $variant,
        ];
    }

    protected function createPayment(
        Order $order,
        string $methodKey,
        int $attempt,
        string $status = Payment::STATUS_PENDING,
    ): Payment {
        $method = config("services.midtrans.methods.{$methodKey}");
        $fee = app(MidtransService::class)->feeFor($methodKey, self::BEFORE_FEE);

        return Payment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => $methodKey,
            'gateway' => 'midtrans',
            'payment_type' => $method['payment_type'],
            'bank' => $method['bank'],
            'gateway_order_id' => $order->order_code . '-' . $attempt,
            'transaction_id' => (string) Str::uuid(),
            'va_number' => $methodKey === 'qris' ? null : '95073296922772990300925',
            'amount' => self::BEFORE_FEE + $fee,
            'fee' => $fee,
            'status' => $status,
            'gateway_status' => $status === Payment::STATUS_CANCELLED ? 'cancel' : 'pending',
            'expires_at' => $order->payment_expires_at,
        ]);
    }

    protected function midtransStatusBody(Payment $payment, string $transactionStatus = 'pending'): array
    {
        return [
            'status_code' => $transactionStatus === 'pending' ? '201' : '200',
            'status_message' => 'Success, transaction is found',
            'transaction_id' => $payment->transaction_id,
            'order_id' => $payment->gateway_order_id,
            'gross_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'payment_type' => $payment->payment_type,
            'transaction_status' => $transactionStatus,
            'fraud_status' => 'accept',
        ];
    }

    protected function midtransCancelBody(Payment $payment): array
    {
        return [
            'status_code' => '200',
            'status_message' => 'Success, transaction is canceled',
            'transaction_id' => $payment->transaction_id,
            'order_id' => $payment->gateway_order_id,
            'gross_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'payment_type' => $payment->payment_type,
            'transaction_status' => 'cancel',
            'fraud_status' => 'accept',
        ];
    }
}