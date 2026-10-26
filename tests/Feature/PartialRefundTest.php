<?php

namespace Tests\Feature;

use App\Jobs\RefundLatePayment;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\Payment;
use App\Services\MidtransService;
use App\Services\PaymentSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Concerns\BuildsMidtransOrders;
use Tests\TestCase;

class PartialRefundTest extends TestCase
{
    use BuildsMidtransOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureMidtrans();

        config(['services.midtrans.auto_refund' => true]);
    }

    public function test_a_partial_refund_from_the_dashboard_does_not_close_a_paid_order(): void
    {
        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder('qris');

        $this->settle($payment, 'settlement');

        Log::spy();

        $this->settle($payment, 'partial_refund', ['refund_amount' => '50000.00']);
        $this->settle($payment, 'partial_refund', ['refund_amount' => '50000.00']);

        $order->refresh();

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame('partial_refund', $payment->fresh()->gateway_status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->order_status);
        $this->assertSame(8, $variant->fresh()->stock);
        $this->assertSame(0, $this->notificationCount($order, 'refunded'));

        Log::shouldHaveReceived('warning')
            ->with('Midtrans: refund atau chargeback sebagian, status order tidak diubah', \Mockery::type('array'))
            ->once();
    }

    public function test_a_full_refund_from_the_dashboard_still_closes_a_paid_order(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder('qris');

        $this->settle($payment, 'settlement');
        $this->settle($payment, 'refund', ['refund_amount' => number_format((float) $payment->amount, 2, '.', '')]);

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame(1, $this->notificationCount($order, 'refunded'));
    }

    public function test_a_partial_refund_response_to_our_own_request_is_not_treated_as_done(): void
    {
        ['payment' => $payment] = $this->createPendingOrder('qris');

        $payment->forceFill(['status' => Payment::STATUS_REFUND_REQUIRED])->save();

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/refund' => Http::response(
                $this->midtransStatusBody($payment, 'partial_refund')
            ),
        ]);

        $this->expectException(RuntimeException::class);

        try {
            (new RefundLatePayment($payment->id))->handle(
                app(MidtransService::class),
                app(PaymentSettlementService::class),
            );
        } finally {
            $this->assertSame(Payment::STATUS_REFUND_REQUIRED, $payment->fresh()->status);
        }
    }

    private function settle(Payment $payment, string $transactionStatus, array $extra = []): Payment
    {
        $body = array_merge($this->midtransStatusBody($payment, $transactionStatus), $extra);

        return app(PaymentSettlementService::class)->apply($payment->fresh(), [
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
}