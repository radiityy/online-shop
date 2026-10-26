<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\OrderNotificationService;
use App\Services\RefundAccountService;
use Illuminate\Console\Command;
use Throwable;

class RemindRefundAccounts extends Command
{
    protected $signature = 'refunds:remind-accounts';

    protected $description = 'Mengingatkan pelanggan Virtual Account yang belum mengisi rekening refund';

    public function handle(RefundAccountService $accounts, OrderNotificationService $notifications): int
    {
        $afterDays = (int) config('refund.reminder_after_days', 3);
        $max = (int) config('refund.reminder_max', 3);
        $sent = 0;

        Payment::query()
            ->where('status', Payment::STATUS_REFUND_REQUIRED)
            ->where('account_reminders_sent', '<', $max)
            ->whereDoesntHave('refundAccount')
            ->with('order')
            ->orderBy('id')
            ->chunkById(50, function ($payments) use ($accounts, $notifications, $afterDays, &$sent): void {
                foreach ($payments as $payment) {
                    if (! $payment->order || ! $accounts->requiresAccount($payment)) {
                        continue;
                    }

                    $since = $payment->account_reminded_at ?? $payment->paid_at ?? $payment->created_at;

                    if ($since === null || $since->gt(now()->subDays($afterDays))) {
                        continue;
                    }

                    try {
                        $notifications->notifyRefundAccountReminder($payment->order, $payment);

                        $payment->forceFill([
                            'account_reminded_at' => now(),
                            'account_reminders_sent' => $payment->account_reminders_sent + 1,
                        ])->save();

                        $sent++;
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }
            });

        $this->info("Pengingat rekening refund terkirim: {$sent}");

        return self::SUCCESS;
    }
}