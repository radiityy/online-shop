<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Jobs\RefundLatePayment;
use App\Models\Order;
use App\Models\Payment;
use App\Services\MidtransService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class RetryRefundAction
{
    public static function make(): Action
    {
        return Action::make('retryRefund')
            ->label('Retry refund')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Retry the automatic refund?')
            ->modalDescription('The refund request will be sent to Midtrans again for every payment of this order that is still waiting for a refund. Make sure the queue worker is running. If Midtrans rejects it again, the reason is written to storage/logs/laravel.log.')
            ->modalSubmitActionLabel('Yes, retry refund')
            ->visible(fn (Order $record): bool => self::retryablePayments($record)->isNotEmpty())
            ->action(function (Order $record): void {
                if (! app(MidtransService::class)->autoRefundEnabled()) {
                    Notification::make()
                        ->title('Automatic refunds are switched off')
                        ->body('Set MIDTRANS_AUTO_REFUND=true and clear the config cache, or return the money manually and use "Mark as refunded".')
                        ->warning()
                        ->persistent()
                        ->send();

                    return;
                }

                $count = self::dispatchFor($record);

                if ($count === 0) {
                    Notification::make()
                        ->title('Nothing to refund')
                        ->body('No payment of this order is waiting for an automatic refund anymore.')
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Refund retry queued')
                    ->body('The request is processed by the queue worker. Refresh this page in a moment to see the result.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Payments of the order that wait for a refund and can be refunded through the API.
     * Virtual Account payments are excluded: those are returned manually.
     *
     * @return Collection<int, Payment>
     */
    public static function retryablePayments(Order $order): Collection
    {
        $midtrans = app(MidtransService::class);

        return $order->payments()
            ->where('status', Payment::STATUS_REFUND_REQUIRED)
            ->get()
            ->filter(fn (Payment $payment): bool => $midtrans->supportsRefund($payment->payment_type))
            ->values();
    }

    public static function dispatchFor(Order $order): int
    {
        $payments = self::retryablePayments($order);

        foreach ($payments as $payment) {
            RefundLatePayment::dispatch($payment->id);
        }

        return $payments->count();
    }
}