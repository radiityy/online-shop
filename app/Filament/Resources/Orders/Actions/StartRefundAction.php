<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Models\Order;
use App\Models\RefundAccount;
use App\Services\RefundAccountService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class StartRefundAction
{
    public static function make(): Action
    {
        return Action::make('startRefund')
            ->label('Start refund (lock account)')
            ->icon('heroicon-o-lock-closed')
            ->color('info')
            ->modalHeading('Start processing this refund?')
            ->modalDescription(fn (Order $record): HtmlString => self::describe($record))
            ->modalSubmitActionLabel('Lock account and start')
            ->schema([
                Checkbox::make('reviewed')
                    ->label('I checked these details and the money may be sent to this account.')
                    ->accepted(),
            ])
            ->visible(fn (Order $record): bool => self::pendingAccounts($record)->isNotEmpty())
            ->action(function (Order $record): void {
                $service = app(RefundAccountService::class);

                foreach (self::pendingAccounts($record) as $account) {
                    $service->lock($account);
                }

                Notification::make()
                    ->title('Refund account locked')
                    ->body('The customer can no longer change the account. Transfer the money, then use "Mark as refunded".')
                    ->success()
                    ->send();
            });
    }

    /**
     * Accounts that have been sent in but not yet locked.
     *
     * @return \Illuminate\Support\Collection<int, RefundAccount>
     */
    private static function pendingAccounts(Order $order)
    {
        return $order->refundAccounts()
            ->whereNull('refund_accounts.locked_at')
            ->whereNull('refund_accounts.cleared_at')
            ->get();
    }

    private static function describe(Order $order): HtmlString
    {
        $lines = self::pendingAccounts($order)->map(function (RefundAccount $account) use ($order): string {
            $line = e($account->bankLabel().' · '.$account->account_number.' · a.n. '.$account->account_holder);

            if ($account->needs_review) {
                $line .= ' <strong>(the holder name differs from the customer name "'.e($order->user?->name).'", check before sending)</strong>';
            }

            return '<div>'.$line.'</div>';
        });

        return new HtmlString(
            '<div>Customers can change their account until you start, After this it is locked.</div>'.$lines->implode('')
        );
    }
}