<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\MidtransService;
use App\Services\OrderNotificationService;
use App\Services\PaymentSettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = 'Menutup order yang melewati batas bayar dan melepas stoknya';

    public function handle(
        MidtransService $midtrans,
        PaymentSettlementService $settlement,
        OrderNotificationService $notifications,
    ): int {
        $expired = 0;

        Order::query()
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<=', now())
            ->where('payment_status', 'pending')
            ->where('stock_released', false)
            ->where('order_status', '!=', 'cancelled')
            ->orderBy('id')
            ->chunkById(50, function ($orders) use ($midtrans, $settlement, $notifications, &$expired) {
                foreach ($orders as $order) {
                    try {
                        if ($this->expire($order, $midtrans, $settlement, $notifications)) {
                            $expired++;
                        }
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }
            });

        $this->info("Order kedaluwarsa: {$expired}");

        return self::SUCCESS;
    }

    private function expire(
        Order $order,
        MidtransService $midtrans,
        PaymentSettlementService $settlement,
        OrderNotificationService $notifications,
    ): bool {
        $latest = $order->payments()->first();

        if ($latest && $latest->gateway && $latest->isPending() && $midtrans->isConfigured()) {
            try {
                $settlement->apply($latest, $midtrans->status($latest->gateway_order_id));
            } catch (RuntimeException) {
            }

            $order->refresh();

            if ($order->payment_status !== 'pending') {
                return false;
            }

            try {
                $midtrans->cancel($latest->gateway_order_id);
            } catch (RuntimeException) {
            }
        }

        $closed = DB::transaction(function () use ($order) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $locked || $locked->payment_status !== 'pending' || $locked->stock_released) {
                return false;
            }

            $locked->update(['payment_status' => 'expired']);

            return true;
        });

        if ($closed) {
            try {
                $notifications->notifyPaymentExpired($order);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $closed;
    }
}