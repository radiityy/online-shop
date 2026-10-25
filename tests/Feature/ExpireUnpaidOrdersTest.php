<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsMidtransOrders;
use Tests\TestCase;

class ExpireUnpaidOrdersTest extends TestCase
{
    use BuildsMidtransOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureMidtrans();
    }

    public function test_an_overdue_unpaid_order_is_expired_and_its_stock_returned(): void
    {
        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        $this->makeOverdue($order);

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/status' => Http::response($this->midtransStatusBody($payment)),
            'api.sandbox.midtrans.com/v2/*/cancel' => Http::response($this->midtransCancelBody($payment)),
        ]);

        $this->assertStringContainsString('Order kedaluwarsa: 1', $this->runCommand());

        $order->refresh();

        $this->assertSame('expired', $order->payment_status);
        $this->assertTrue($order->stock_released);
        $this->assertSame(10, $variant->fresh()->stock);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/v2/' . $payment->gateway_order_id . '/cancel'));
    }

    public function test_an_order_that_is_not_yet_due_is_left_alone(): void
    {
        ['order' => $order, 'variant' => $variant] = $this->createPendingOrder();

        Http::fake();

        $this->assertStringContainsString('Order kedaluwarsa: 0', $this->runCommand());

        $order->refresh();

        $this->assertSame('pending', $order->payment_status);
        $this->assertFalse($order->stock_released);
        $this->assertSame(8, $variant->fresh()->stock);

        Http::assertNothingSent();
    }

    public function test_an_order_is_not_expired_when_the_payment_arrived_in_the_meantime(): void
    {
        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        $this->makeOverdue($order);

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/status' => Http::response($this->midtransStatusBody($payment, 'settlement')),
        ]);

        $this->assertStringContainsString('Order kedaluwarsa: 0', $this->runCommand());

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->order_status);
        $this->assertFalse($order->stock_released);
        $this->assertSame(8, $variant->fresh()->stock);

        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/cancel'));
    }

    public function test_an_order_still_expires_locally_when_the_gateway_is_unreachable(): void
    {
        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        $this->makeOverdue($order);

        $down = [
            'status_code' => '500',
            'status_message' => 'Sorry. Our system is recovering from unexpected issues. Please retry.',
        ];

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/status' => Http::response($down, 500),
            'api.sandbox.midtrans.com/v2/*/cancel' => Http::response($down, 500),
        ]);

        $this->assertStringContainsString('Order kedaluwarsa: 1', $this->runCommand());

        $order->refresh();

        $this->assertSame('expired', $order->payment_status);
        $this->assertTrue($order->stock_released);
        $this->assertSame(10, $variant->fresh()->stock);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v2/' . $payment->gateway_order_id . '/cancel'));
    }

    public function test_running_the_command_twice_returns_the_stock_only_once(): void
    {
        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        $this->makeOverdue($order);

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/status' => Http::response($this->midtransStatusBody($payment)),
            'api.sandbox.midtrans.com/v2/*/cancel' => Http::response($this->midtransCancelBody($payment)),
        ]);

        $this->assertStringContainsString('Order kedaluwarsa: 1', $this->runCommand());
        $this->assertStringContainsString('Order kedaluwarsa: 0', $this->runCommand());

        $this->assertSame('expired', $order->fresh()->payment_status);
        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_an_already_cancelled_order_is_skipped_even_when_overdue(): void
    {
        ['order' => $order, 'variant' => $variant] = $this->createPendingOrder();

        $order->forceFill([
            'order_status' => 'cancelled',
            'stock_released' => true,
            'payment_expires_at' => now()->subMinutes(5),
        ])->saveQuietly();

        Http::fake();

        $this->assertStringContainsString('Order kedaluwarsa: 0', $this->runCommand());

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(8, $variant->fresh()->stock);

        Http::assertNothingSent();
    }

    private function makeOverdue(Order $order): void
    {
        $order->forceFill(['payment_expires_at' => now()->subMinutes(5)])->saveQuietly();
    }

    private function runCommand(): string
    {
        Artisan::call('orders:expire-unpaid');

        return Artisan::output();
    }
}