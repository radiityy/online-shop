<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $activePaymentStatuses = [
            'pending',
            'waiting_confirmation',
            'paid',
        ];

        $activeOrderStatuses = [
            'pending',
            'processing',
            'packed',
            'shipped',
            'delivered',
            'completed',
        ];

        $activeShippingStatuses = [
            'not_shipped',
            'packed',
            'shipped',
            'delivered',
        ];

        $isTryingToReactivate =
            $this->record->stock_released
            && (
                (
                    ($data['payment_status'] ?? null) !== $this->record->payment_status
                    && in_array($data['payment_status'] ?? null, $activePaymentStatuses, true)
                )
                || (
                    ($data['order_status'] ?? null) !== $this->record->order_status
                    && in_array($data['order_status'] ?? null, $activeOrderStatuses, true)
                )
                || (
                    ($data['shipping_status'] ?? null) !== $this->record->shipping_status
                    && in_array($data['shipping_status'] ?? null, $activeShippingStatuses, true)
                )
            );

        if ($isTryingToReactivate) {
            Notification::make()
                ->title('Order cannot be reactivated')
                ->body('This order has already been cancelled, failed, expired, or refunded. Please ask the customer to place a new order.')
                ->danger()
                ->persistent()
                ->send();

            throw new Halt();
        }

        return $data;
    }
}