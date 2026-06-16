<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderNotification;

class OrderNotificationService
{
    public function notifyPaymentConfirmed(Order $order): void
    {
        $this->create(
            order: $order,
            type: 'payment_confirmed',
            title: 'Payment Confirmed',
            message: "Your payment for order {$order->order_code} has been confirmed. We are preparing your order."
        );
    }

    public function notifyPaymentFailed(Order $order): void
    {
        $this->create(
            order: $order,
            type: 'payment_failed',
            title: 'Payment Failed',
            message: "We could not verify the payment for order {$order->order_code}. This order will not be processed."
        );
    }

    public function notifyOrderProcessing(Order $order): void
    {
        $this->create(
            order: $order,
            type: 'order_processing',
            title: 'Order Processing',
            message: "Order {$order->order_code} is now being prepared."
        );
    }

    public function notifyOrderPacked(Order $order): void
    {
        $this->create(
            order: $order,
            type: 'order_packed',
            title: 'Order Packed',
            message: "Order {$order->order_code} has been packed and is waiting for shipment."
        );
    }

    public function notifyOrderShipped(Order $order): void
    {
        $trackingNumber = $order->shipment?->tracking_number;

        $message = $trackingNumber
            ? "Order {$order->order_code} has been shipped. Tracking number: {$trackingNumber}."
            : "Order {$order->order_code} has been shipped.";

        $this->create(
            order: $order,
            type: 'order_shipped',
            title: 'Order Shipped',
            message: $message
        );
    }

    public function notifyOrderDelivered(Order $order): void
    {
        $this->create(
            order: $order,
            type: 'order_delivered',
            title: 'Order Delivered',
            message: "Order {$order->order_code} has been delivered. Thank you for shopping with NEVERENDING."
        );
    }

    public function notifyOrderCancelled(Order $order): void
    {
        $this->create(
            order: $order,
            type: 'order_cancelled',
            title: 'Order Cancelled',
            message: "Order {$order->order_code} has been cancelled."
        );
    }

    private function create(Order $order, string $type, string $title, string $message): void
    {
        OrderNotification::query()->create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);
    }
}