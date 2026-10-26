<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Actions\RetryRefundAction;
use App\Jobs\RefundLatePayment;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsMidtransOrders;
use Tests\TestCase;

class RetryRefundActionTest extends TestCase
{
    use BuildsMidtransOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureMidtrans();

        config(['services.midtrans.auto_refund' => true]);
    }

    public function test_a_refundable_payment_waiting_for_a_refund_is_queued_again(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder('qris');

        $payment->forceFill(['status' => Payment::STATUS_REFUND_REQUIRED])->save();

        Queue::fake();

        $this->assertSame(1, RetryRefundAction::dispatchFor($order));

        Queue::assertPushed(RefundLatePayment::class, fn (RefundLatePayment $job): bool => $job->paymentId === $payment->id);
    }

    public function test_a_virtual_account_payment_is_never_queued(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder('bca_va');

        $payment->forceFill(['status' => Payment::STATUS_REFUND_REQUIRED])->save();

        Queue::fake();

        $this->assertTrue(RetryRefundAction::retryablePayments($order)->isEmpty());
        $this->assertSame(0, RetryRefundAction::dispatchFor($order));

        Queue::assertNothingPushed();
    }

    public function test_payments_in_other_states_are_ignored(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder('qris');

        Queue::fake();

        foreach ([Payment::STATUS_PENDING, Payment::STATUS_PAID, Payment::STATUS_REFUNDED] as $status) {
            $payment->forceFill(['status' => $status])->save();

            $this->assertSame(0, RetryRefundAction::dispatchFor($order), "status {$status} must not be retried");
        }

        Queue::assertNothingPushed();
    }

    public function test_only_the_payments_waiting_for_a_refund_are_queued_for_a_duplicate_payment_order(): void
    {
        ['order' => $order, 'payment' => $first] = $this->createPendingOrder('bca_va');

        $first->forceFill(['status' => Payment::STATUS_PAID])->save();

        $second = $this->createPayment($order, 'qris', 2);
        $second->forceFill(['status' => Payment::STATUS_REFUND_REQUIRED])->save();

        Queue::fake();

        $this->assertSame(1, RetryRefundAction::dispatchFor($order));

        Queue::assertPushed(RefundLatePayment::class, 1);
        Queue::assertPushed(RefundLatePayment::class, fn (RefundLatePayment $job): bool => $job->paymentId === $second->id);
    }
}