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

            // Payment yang sudah paid atau sudah ditandai refund_required tidak boleh
            // diubah lagi oleh notifikasi apa pun selain refund.
            if (
                in_array($payment->status, [Payment::STATUS_PAID, Payment::STATUS_REFUND_REQUIRED], true)
                && $status !== Payment::STATUS_REFUNDED
            ) {
                $payment->forceFill(['last_webhook_at' => now()])->save();

                return $payment;
            }

            if ($payment->isFinal() && $status === Payment::STATUS_PENDING) {
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
                $this->markOrderRefunded($order, $payment);
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
        // Order sudah dibayar oleh payment lain: uang ini kelebihan.
        // Order tetap paid, hanya payment ini yang perlu direfund.
        if ($order->payment_status === 'paid') {
            $this->flagForRefund($order, $payment, 'duplicate_payment', false);

            return;
        }

        // Order sudah ditutup (dibatalkan, kedaluwarsa, gagal): tidak diaktifkan lagi.
        if ($order->stock_released || $order->order_status === 'cancelled') {
            $this->flagForRefund($order, $payment, 'closed_order', true);

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

    /**
     * Menandai payment ini perlu direfund.
     *
     * Order hanya ikut ditandai kalau order itu memang sudah ditutup. Ditulis dengan
     * saveQuietly() agar Order::syncRelatedStatuses() tidak menimpa status payment lain
     * dan hook updating tidak terpicu (stok sudah dilepas sebelumnya).
     */
    private function flagForRefund(Order $order, Payment $payment, string $reason, bool $markOrder): void
    {
        $payment->forceFill(['status' => Payment::STATUS_REFUND_REQUIRED])->save();

        if ($markOrder) {
            $order->forceFill(['payment_status' => 'refund_required'])->saveQuietly();
        }

        Log::critical('Pembayaran masuk yang tidak bisa dipakai, perlu refund', [
            'reason' => $reason,
            'order_code' => $order->order_code,
            'gateway_order_id' => $payment->gateway_order_id,
            'amount' => $payment->amount,
        ]);
    }

    /**
     * Notifikasi refund hanya mengubah order kalau payment yang direfund memang
     * yang menahan status order. Refund atas pembayaran ganda tidak boleh
     * membuat order yang sah ikut jadi refunded.
     */
    private function markOrderRefunded(Order $order, Payment $payment): void
    {
        $others = Payment::query()
            ->where('order_id', $order->id)
            ->whereKeyNot($payment->id);

        if ($order->payment_status === 'refund_required') {
            if ((clone $others)->where('status', Payment::STATUS_REFUND_REQUIRED)->exists()) {
                return;
            }

            $order->forceFill(['payment_status' => 'refunded'])->saveQuietly();

            return;
        }

        if ($order->payment_status === 'paid') {
            if ((clone $others)->where('status', Payment::STATUS_PAID)->exists()) {
                return;
            }

            $order->update(['payment_status' => 'refunded']);
        }
    }
}