<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Services\OrderNotificationService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected ?string $previousPaymentStatus = null;

    protected ?string $previousOrderStatus = null;

    protected ?string $previousShippingStatus = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->previousPaymentStatus = $this->record->payment_status;
        $this->previousOrderStatus = $this->record->order_status;
        $this->previousShippingStatus = $this->record->shipping_status;

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

        $refundStatuses = ['refund_required', 'refunded'];

        $currentPaymentStatus = $this->record->payment_status;

        $requestedPaymentStatus = $data['payment_status'] ?? $currentPaymentStatus;

        $isTouchingRefundStatus =
            $requestedPaymentStatus !== $currentPaymentStatus
            && (
                in_array($requestedPaymentStatus, $refundStatuses, true)
                || in_array($currentPaymentStatus, $refundStatuses, true)
            );

        if ($isTouchingRefundStatus) {
            Notification::make()
                ->title('Refund statuses are managed by the system')
                ->body('Refund Required is set automatically when a payment cannot be used. To finish a refund, use the "Mark as refunded" action on the orders list after the money has been returned.')
                ->danger()
                ->persistent()
                ->send();

            throw new Halt();
        }

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

    protected function afterSave(): void
    {
        $this->record->refresh()->load('shipment');

        $service = app(OrderNotificationService::class);

        $paymentStatusChanged = $this->previousPaymentStatus !== $this->record->payment_status;
        $orderStatusChanged = $this->previousOrderStatus !== $this->record->order_status;
        $shippingStatusChanged = $this->previousShippingStatus !== $this->record->shipping_status;

        if ($paymentStatusChanged) {
            match ($this->record->payment_status) {
                'paid' => $service->notifyPaymentConfirmed($this->record),
                'failed' => $service->notifyPaymentFailed($this->record),
                'expired' => $service->notifyPaymentExpired($this->record),
                default => null,
            };
        }

        if ($orderStatusChanged) {
            match ($this->record->order_status) {
                'processing' => $service->notifyOrderProcessing($this->record),
                'packed' => $service->notifyOrderPacked($this->record),
                'cancelled' => $service->notifyOrderCancelled($this->record),
                default => null,
            };
        }

        $shouldNotifyShipped =
            (
                $orderStatusChanged
                && $this->record->order_status === 'shipped'
            )
            || (
                $shippingStatusChanged
                && $this->record->shipping_status === 'shipped'
            );

        if ($shouldNotifyShipped) {
            $service->notifyOrderShipped($this->record);
        }

        $shouldNotifyDelivered =
            (
                $orderStatusChanged
                && in_array($this->record->order_status, ['delivered', 'completed'], true)
            )
            || (
                $shippingStatusChanged
                && $this->record->shipping_status === 'delivered'
            );

        if ($shouldNotifyDelivered) {
            $service->notifyOrderDelivered($this->record);
        }
    }
}