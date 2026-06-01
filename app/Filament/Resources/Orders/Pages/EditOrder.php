<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function afterSave(): void
    {
        $order = $this->record->fresh(['payment', 'shipment']);

        if ($order->payment) {
            $order->payment->update([
                'status' => $order->payment_status,
                'paid_at' => $order->payment_status === 'paid'
                    ? ($order->payment->paid_at ?? now())
                    : $order->payment->paid_at,
            ]);
        }

        if ($order->shipment) {
            $order->shipment->update([
                'status' => $order->shipping_status,
                'shipped_at' => $order->shipping_status === 'shipped'
                    ? ($order->shipment->shipped_at ?? now())
                    : $order->shipment->shipped_at,
                'delivered_at' => $order->shipping_status === 'delivered'
                    ? ($order->shipment->delivered_at ?? now())
                    : $order->shipment->delivered_at,
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}