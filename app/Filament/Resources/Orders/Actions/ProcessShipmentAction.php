<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Exceptions\ShippingException;
use App\Models\Order;
use App\Services\ShipmentFulfillmentService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ProcessShipmentAction
{
    public static function make(): Action
    {
        return Action::make('processShipment')
            ->label('Process shipment')
            ->icon('heroicon-o-truck')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Request courier pickup?')
            ->modalDescription(fn (Order $record): string => sprintf(
                'Biteship will book %s %s for order %s and the courier will be asked to pick up the package. Your Biteship balance is charged for this shipment. Make sure the package is packed and ready.',
                $record->shipping_courier_name ?: strtoupper((string) $record->shipping_courier_code),
                $record->shipping_service_name ?: strtoupper((string) $record->shipping_service_code),
                $record->order_code,
            ))
            ->modalSubmitActionLabel('Yes, process shipment')
            ->visible(fn (Order $record): bool => app(ShipmentFulfillmentService::class)->canProcess($record))
            ->action(function (Order $record): void {
                try {
                    $shipment = app(ShipmentFulfillmentService::class)->process($record);
                } catch (ShippingException $e) {
                    Notification::make()
                        ->title('Shipment could not be processed')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                $record->refresh();

                Notification::make()
                    ->title('Shipment processed')
                    ->body($shipment->tracking_number
                        ? 'Tracking number: '.$shipment->tracking_number
                        : 'The tracking number will appear as soon as the courier issues it.')
                    ->success()
                    ->send();
            });
    }
}