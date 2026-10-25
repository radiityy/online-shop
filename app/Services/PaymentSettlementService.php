<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PaymentSettlementService
{
    public function __construct(
        private readonly MidtransService $midtrans,
        private readonly OrderNotificationService $notifications,
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

        $after = [];

        $result = DB::transaction(function () use ($payment, $data, $raw, $status, &$after) {
            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $previous = $payment->status;

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
                $this->markOrderPaid($order, $payment, $isLatest, $after);
            } elseif ($status === Payment::STATUS_REFUNDED) {
                $this->markOrderRefunded($order, $payment);

                if ($previous !== Payment::STATUS_REFUNDED) {
                    $after[] = fn () => $this->notifications->notifyRefunded($order, $payment);
                }
            } elseif (
                $status === Payment::STATUS_EXPIRED
                && $isLatest
                && $order->payment_status === 'pending'
                && $order->payment_expires_at
                && $order->payment_expires_at->isPast()
            ) {
                $order->update(['payment_status' => 'expired']);

                $after[] = fn () => $this->notifications->notifyPaymentExpired($order);
            }

            return $payment;
        });

        $this->send($after);

        return $result;
    }

    public function markRefundedManually(Order $order): bool
    {
        $refunded = DB::transaction(function () use ($order): ?array {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $payments = Payment::query()
                ->where('order_id', $locked->id)
                ->where('status', Payment::STATUS_REFUND_REQUIRED)
                ->lockForUpdate()
                ->get();

            $orderNeedsRefund = $locked->payment_status === 'refund_required';

            if ($payments->isEmpty() && ! $orderNeedsRefund) {
                return null;
            }

            foreach ($payments as $payment) {
                $payment->forceFill(['status' => Payment::STATUS_REFUNDED])->save();
            }

            if ($orderNeedsRefund) {
                $locked->forceFill(['payment_status' => 'refunded'])->saveQuietly();

                $locked->releaseReservedStockIfNeeded();
            }

            Log::info('Refund manual ditandai selesai', [
                'order_code' => $locked->order_code,
                'payment_ids' => $payments->pluck('id')->all(),
                'amount' => $payments->sum(fn (Payment $payment) => (float) $payment->amount),
                'admin_id' => Auth::id(),
            ]);

            $targets = $payments->isNotEmpty()
                ? $payments->all()
                : array_filter([Payment::query()->where('order_id', $locked->id)->orderByDesc('id')->first()]);

            return [$locked, $targets];
        });

        if ($refunded === null) {
            return false;
        }

        [$locked, $targets] = $refunded;

        $this->send(array_map(
            fn (Payment $payment) => fn () => $this->notifications->notifyRefunded($locked, $payment),
            $targets,
        ));

        return true;
    }

    private function markOrderPaid(Order $order, Payment $payment, bool $isLatest, array &$after): void
    {
        if ($order->payment_status === 'paid') {
            $this->flagForRefund($order, $payment, 'duplicate_payment', false, $after);

            return;
        }

        if ($order->stock_released || $order->order_status === 'cancelled') {
            $this->flagForRefund($order, $payment, 'closed_order', true, $after);

            return;
        }

        $order->update([
            'payment_status' => 'paid',
            'order_status' => $order->order_status === 'pending' ? 'processing' : $order->order_status,
        ]);

        $after[] = fn () => $this->notifications->notifyPaymentConfirmed($order);

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

    private function flagForRefund(Order $order, Payment $payment, string $reason, bool $markOrder, array &$after): void
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

        $after[] = fn () => $this->notifications->notifyRefundRequired(
            $order,
            $payment,
            $reason === 'duplicate_payment',
        );
    }

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

    private function send(array $notifications): void
    {
        foreach ($notifications as $notification) {
            try {
                $notification();
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}