<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\Payment;
use App\Services\OrderNotificationService;
use App\Services\PaymentSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\BuildsMidtransOrders;
use Tests\TestCase;

class OrderNotificationTest extends TestCase
{
    use BuildsMidtransOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureMidtrans();
    }

    public function test_a_settlement_notifies_the_customer_once(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment] = $this->createPendingOrder();

        $this->settle($payment);
        $this->settle($payment);

        $this->assertSame(1, $this->notificationCount($order, 'payment_confirmed'));
        $this->assertSame($user->id, $this->latestOf($order, 'payment_confirmed')->user_id);
    }

    public function test_money_for_a_closed_order_notifies_a_refund_once_and_never_a_payment_confirmation(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder();

        $order->update(['order_status' => 'cancelled']);

        $this->settle($payment);
        $this->settle($payment);

        $notification = $this->latestOf($order, 'refund_required');

        $this->assertSame(1, $this->notificationCount($order, 'refund_required'));
        $this->assertSame(0, $this->notificationCount($order, 'payment_confirmed'));
        $this->assertStringContainsString($this->money($payment), $notification->message);
        $this->assertStringContainsString('after the order was closed', $notification->message);
    }

    public function test_a_duplicate_payment_notifies_a_refund_without_touching_the_paid_order_message(): void
    {
        ['order' => $order, 'payment' => $first] = $this->createPendingOrder();

        $this->settle($first);

        $second = $this->createPayment($order, 'qris', 2);

        $this->settle($second);
        $this->settle($second);

        $notification = $this->latestOf($order, 'refund_required');

        $this->assertSame(1, $this->notificationCount($order, 'payment_confirmed'));
        $this->assertSame(1, $this->notificationCount($order, 'refund_required'));
        $this->assertStringContainsString('second payment', $notification->message);
        $this->assertStringContainsString($this->money($second), $notification->message);
    }

    public function test_a_refund_notice_from_the_gateway_notifies_the_customer_once(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder();

        $order->update(['order_status' => 'cancelled']);

        $this->settle($payment);
        $this->settle($payment, 'refund');
        $this->settle($payment, 'refund');

        $this->assertSame(1, $this->notificationCount($order, 'refunded'));
        $this->assertStringContainsString($this->money($payment), $this->latestOf($order, 'refunded')->message);
    }

    public function test_marking_a_refund_manually_notifies_the_customer_once(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder();

        $order->update(['order_status' => 'cancelled']);

        $this->settle($payment);

        $service = app(PaymentSettlementService::class);

        $this->assertTrue($service->markRefundedManually($order));
        $this->assertFalse($service->markRefundedManually($order));

        $this->assertSame(1, $this->notificationCount($order, 'refunded'));
    }

    public function test_an_expire_notice_after_the_deadline_notifies_the_customer_once(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder();

        $order->forceFill(['payment_expires_at' => now()->subMinutes(5)])->saveQuietly();

        $this->settle($payment, 'expire');
        $this->settle($payment, 'expire');

        $this->assertSame('expired', $order->fresh()->payment_status);
        $this->assertSame(1, $this->notificationCount($order, 'payment_expired'));
    }

    public function test_the_expiry_command_notifies_the_customer_once(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder();

        $order->forceFill(['payment_expires_at' => now()->subMinutes(5)])->saveQuietly();

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/status' => Http::response($this->midtransStatusBody($payment)),
            'api.sandbox.midtrans.com/v2/*/cancel' => Http::response($this->midtransCancelBody($payment)),
        ]);

        Artisan::call('orders:expire-unpaid');
        Artisan::call('orders:expire-unpaid');

        $this->assertSame('expired', $order->fresh()->payment_status);
        $this->assertSame(1, $this->notificationCount($order, 'payment_expired'));
    }

    public function test_a_notification_failure_never_blocks_recording_the_payment(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder();

        $this->partialMock(OrderNotificationService::class, function ($mock) {
            $mock->shouldReceive('notifyPaymentConfirmed')->andThrow(new RuntimeException('boom'));
        });

        $this->settle($payment);

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('processing', $order->fresh()->order_status);
        $this->assertSame(0, $this->notificationCount($order, 'payment_confirmed'));
    }

    private function settle(Payment $payment, string $transactionStatus = 'settlement'): Payment
    {
        $body = $this->midtransStatusBody($payment, $transactionStatus);

        return app(PaymentSettlementService::class)->apply($payment, [
            'transaction_id' => $body['transaction_id'],
            'gateway_status' => $body['transaction_status'],
            'fraud_status' => $body['fraud_status'],
            'raw' => $body,
        ]);
    }

    private function notificationCount(Order $order, string $type): int
    {
        return OrderNotification::query()
            ->where('order_id', $order->id)
            ->where('type', $type)
            ->count();
    }

    private function latestOf(Order $order, string $type): OrderNotification
    {
        return OrderNotification::query()
            ->where('order_id', $order->id)
            ->where('type', $type)
            ->latest('id')
            ->firstOrFail();
    }

    private function money(Payment $payment): string
    {
        return 'Rp ' . number_format((float) $payment->amount, 0, ',', '.');
    }
}