<?php

namespace Tests\Feature;

use App\Jobs\RefundLatePayment;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\Payment;
use App\Services\PaymentSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Concerns\BuildsMidtransOrders;
use Tests\TestCase;

class AutoRefundTest extends TestCase
{
    use BuildsMidtransOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureMidtrans();

        config(['services.midtrans.auto_refund' => true]);
    }

    public function test_money_for_a_closed_order_paid_by_qris_is_refunded_automatically(): void
    {
        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder('qris');

        $order->update(['order_status' => 'cancelled']);

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/refund' => Http::response($this->refundBody($payment)),
        ]);

        $this->settle($payment);

        $order->refresh();

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame('cancelled', $order->order_status);
        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(1, $this->notificationCount($order, 'refund_required'));
        $this->assertSame(1, $this->notificationCount($order, 'refunded'));

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/v2/' . $payment->transaction_id . '/refund')
            && $request['refund_key'] === $order->order_code . '-refund-' . $payment->id
            && $request['amount'] === (int) round((float) $payment->amount));
    }

    public function test_a_virtual_account_payment_is_never_refunded_through_the_api(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder('bca_va');

        $order->update(['order_status' => 'cancelled']);

        Http::fake();

        $this->settle($payment);

        Http::assertNothingSent();

        $this->assertSame(Payment::STATUS_REFUND_REQUIRED, $payment->fresh()->status);
        $this->assertSame('refund_required', $order->fresh()->payment_status);
    }

    public function test_nothing_is_sent_when_automatic_refunds_are_switched_off(): void
    {
        config(['services.midtrans.auto_refund' => false]);

        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder('qris');

        $order->update(['order_status' => 'cancelled']);

        Http::fake();

        $this->settle($payment);

        Http::assertNothingSent();

        $this->assertSame(Payment::STATUS_REFUND_REQUIRED, $payment->fresh()->status);
        $this->assertSame('refund_required', $order->fresh()->payment_status);
    }

    public function test_a_duplicate_refund_id_response_is_treated_as_success_after_checking_the_status(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder('qris');

        $order->update(['order_status' => 'cancelled']);

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/refund' => Http::response([
                'status_code' => '406',
                'status_message' => 'Duplicate refund ID',
            ], 406),
            'api.sandbox.midtrans.com/v2/*/status' => Http::response($this->midtransStatusBody($payment, 'refund')),
        ]);

        $this->settle($payment);

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame(1, $this->notificationCount($order, 'refunded'));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/refund'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/status'));
    }

    public function test_a_rejected_refund_leaves_the_payment_for_the_admin_and_logs_it(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder('qris');

        $order->update(['order_status' => 'cancelled']);

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/refund' => Http::response([
                'status_code' => '412',
                'status_message' => 'Transaction status cannot be updated.',
            ], 412),
            'api.sandbox.midtrans.com/v2/*/status' => Http::response($this->midtransStatusBody($payment, 'settlement')),
        ]);

        Log::spy();

        $this->settle($payment);

        $this->assertSame(Payment::STATUS_REFUND_REQUIRED, $payment->fresh()->status);
        $this->assertSame('refund_required', $order->fresh()->payment_status);
        $this->assertSame(0, $this->notificationCount($order, 'refunded'));

        Log::shouldHaveReceived('critical')
            ->with('Refund otomatis gagal, perlu ditangani admin', Mockery::type('array'))
            ->once();
    }

    public function test_refunding_a_duplicate_payment_does_not_touch_the_paid_order(): void
    {
        ['order' => $order, 'payment' => $first, 'variant' => $variant] = $this->createPendingOrder('bca_va');

        $this->settle($first);

        $second = $this->createPayment($order, 'qris', 2);

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/refund' => Http::response($this->refundBody($second)),
        ]);

        $this->settle($second);

        $order->refresh();

        $this->assertSame(Payment::STATUS_PAID, $first->fresh()->status);
        $this->assertSame(Payment::STATUS_REFUNDED, $second->fresh()->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->order_status);
        $this->assertSame(8, $variant->fresh()->stock);
        $this->assertSame(1, $this->notificationCount($order, 'payment_confirmed'));
        $this->assertSame(1, $this->notificationCount($order, 'refunded'));
    }

    public function test_the_job_does_nothing_when_the_payment_is_not_waiting_for_a_refund(): void
    {
        ['payment' => $payment] = $this->createPendingOrder('qris');

        Http::fake();

        RefundLatePayment::dispatchSync($payment->id);

        $payment->forceFill(['status' => Payment::STATUS_REFUNDED])->save();

        RefundLatePayment::dispatchSync($payment->id);

        Http::assertNothingSent();

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
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

    private function refundBody(Payment $payment): array
    {
        $amount = number_format((float) $payment->amount, 2, '.', '');

        return [
            'status_code' => '200',
            'status_message' => 'Success, refund request is approved',
            'transaction_id' => $payment->transaction_id,
            'order_id' => $payment->gateway_order_id,
            'payment_type' => $payment->payment_type,
            'transaction_status' => 'refund',
            'fraud_status' => 'accept',
            'gross_amount' => $amount,
            'refund_chargeback_id' => 1,
            'refund_amount' => $amount,
            'refund_key' => 'reference1',
        ];
    }

    private function notificationCount(Order $order, string $type): int
    {
        return OrderNotification::query()
            ->where('order_id', $order->id)
            ->where('type', $type)
            ->count();
    }
}