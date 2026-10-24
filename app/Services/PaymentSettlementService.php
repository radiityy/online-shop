<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentSettlementService
{
    public function __construct(
        private readonly MidtransService $midtrans,
    ) {
    }

    public function apply(Payment $payment, array $data): Payment
    {
        $raw = Arr::except((array) ($data['raw'] ?? []), ['signature_key']);
        $gross = $raw['gross_amount'] ?? null;

        if ($gross !== null && (int) round((float) $gross) !== (int) round((float) $payment->amount)) {
            Log::error('Midtrans: nominal tidak cocok, notifikasi diabaikan', [
                'gateway_order_id' => $payment->gateway_order_id,
                'expected' => $payment->amount,
                'received' => $gross,
            ]);

            return $payment;
        }

        $status = $this->midtrans->mapStatus(
            $data['gateway_status'] ?? null,
            $data['fraud_status'] ?? null,
        );

        return DB::transaction(function () use ($payment, $data, $raw, $status) {
            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === Payment::STATUS_PAID && $status !== Payment::STATUS_REFUNDED) {
                $payment->forceFill(['last_webhook_at' => now()])->save();

                return $payment;
            }

            $payment->fill([
                'status' => $status,
                'gateway_status' => $data['gateway_status'] ?? $payment->gateway_status,
                'fraud_status' => $data['fraud_status'] ?? $payment->fraud_status,
                'transaction_id' => $data['transaction_id'] ?? $payment->transaction_id,
                'last_webhook_at' => now(),
                'raw_response' => $raw,
            ]);

            if ($status === Payment::STATUS_PAID && ! $payment->paid_at) {
                $payment->paid_at = now();
            }

            $payment->save();

            $latestId = (int) Payment::query()->where('order_id', $order->id)->max('id');
            $isLatest = (int) $payment->id === $latestId;

            if ($status === Payment::STATUS_PAID) {
                $this->markOrderPaid($order, $payment, $isLatest);
            } elseif ($status === Payment::STATUS_REFUNDED) {
                if ($order->payment_status === 'paid') {
                    $order->update(['payment_status' => 'refunded']);
                }
            } elseif (
                $status === Payment::STATUS_EXPIRED
                && $isLatest
                && $order->payment_status === 'pending'
                && $order->payment_expires_at
                && $order->payment_expires_at->isPast()
            ) {
                $order->update(['payment_status' => 'expired']);
            }

            return $payment;
        });
    }

    private function markOrderPaid(Order $order, Payment $payment, bool $isLatest): void
    {
        if ($order->payment_status === 'paid') {
            return;
        }

        if ($order->stock_released || $order->order_status === 'cancelled') {
            Log::critical('Pembayaran masuk untuk order yang sudah ditutup, perlu refund manual', [
                'order_code' => $order->order_code,
                'gateway_order_id' => $payment->gateway_order_id,
                'amount' => $payment->amount,
            ]);

            return;
        }

        $order->update([
            'payment_status' => 'paid',
            'order_status' => $order->order_status === 'pending' ? 'processing' : $order->order_status,
        ]);

        if ($isLatest) {
            return;
        }

        $latest = Payment::query()
            ->where('order_id', $order->id)
            ->orderByDesc('id')
            ->first();

        if ($latest && $latest->id !== $payment->id) {
            $latest->updateQuietly(['status' => Payment::STATUS_CANCELLED]);

            if ($latest->gateway_order_id) {
                try {
                    $this->midtrans->cancel($latest->gateway_order_id);
                } catch (RuntimeException) {
                }
            }
        }
    }
}