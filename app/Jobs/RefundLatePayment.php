<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Payment;
use App\Services\MidtransService;
use App\Services\PaymentSettlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RefundLatePayment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public function __construct(public readonly int $paymentId)
    {
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(MidtransService $midtrans, PaymentSettlementService $settlement): void
    {
        $payment = Payment::query()->find($this->paymentId);

        if (! $payment || $payment->status !== Payment::STATUS_REFUND_REQUIRED) {
            return;
        }

        if (! $midtrans->autoRefundEnabled() || ! $midtrans->supportsRefund($payment->payment_type)) {
            return;
        }

        $order = Order::query()->find($payment->order_id);

        if (! $order) {
            return;
        }

        $result = $midtrans->refund(
            $payment,
            $order->order_code.'-refund-'.$payment->id,
            (int) round((float) $payment->amount),
            'Order '.$order->order_code.' could not be fulfilled',
        );

        if (! in_array($result['gateway_status'], ['refund', 'partial_refund'], true)) {
            throw new RuntimeException('Respons refund tidak terduga: '.($result['gateway_status'] ?? 'kosong'));
        }

        $settlement->apply($payment, [
            'transaction_id' => $result['transaction_id'] ?? $payment->transaction_id,
            'gateway_status' => $result['gateway_status'],
            'fraud_status' => $result['fraud_status'] ?? null,
            'raw' => $result['raw'],
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $payment = Payment::query()->find($this->paymentId);

        Log::critical('Refund otomatis gagal, perlu ditangani admin', [
            'payment_id' => $this->paymentId,
            'gateway_order_id' => $payment?->gateway_order_id,
            'amount' => $payment?->amount,
            'error' => $exception->getMessage(),
        ]);
    }
}