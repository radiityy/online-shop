<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentSettlementService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class MarkRefundedAction
{
    public static function make(): Action
    {
        return Action::make('markRefunded')
            ->label('Mark as refunded')
            ->icon('heroicon-o-banknotes')
            ->color('warning')
            ->modalHeading('Mark refund as completed?')
            ->modalDescription('Only confirm after the money has really been returned to the customer, for example by manual bank transfer for a Virtual Account payment. The customer bank details are erased when you confirm.')
            ->modalSubmitActionLabel('Yes, money returned')
            ->schema([
                TextInput::make('reference')
                    ->label('Transfer reference')
                    ->helperText('The reference or receipt number of the transfer, kept as the audit trail.')
                    ->required()
                    ->maxLength(100),

                Textarea::make('note')
                    ->label('Note')
                    ->rows(3)
                    ->maxLength(500),
            ])
            ->visible(fn (Order $record): bool => $record->payment_status === 'refund_required'
                || $record->payments()->where('status', Payment::STATUS_REFUND_REQUIRED)->exists())
            ->action(function (Order $record, array $data): void {
                app(PaymentSettlementService::class)->markRefundedManually(
                    $record,
                    $data['reference'],
                    $data['note'] ?? null,
                );

                Notification::make()
                    ->title('Refund marked as completed')
                    ->success()
                    ->send();
            });
    }
}