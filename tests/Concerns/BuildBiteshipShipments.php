<?php

namespace Tests\Concerns;

use App\Models\OrderNotification;
use App\Models\Shipment;
use App\Models\StoreSetting;

trait BuildsBiteshipShipments
{
    use BuildsMidtransOrders;

    protected const ORIGIN_AREA_ID = 'IDNP6IDNC148IDND836IDZ12110';

    protected const WEBHOOK_SECRET = 'test-webhook-secret';

    protected function configureBiteship(): void
    {
        config([
            'services.biteship.key' => 'biteship_test.dummy',
            'services.biteship.base_url' => 'https://api.biteship.com',
            'services.biteship.webhook_header' => 'X-Biteship-Secret',
            'services.biteship.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
    }

    protected function configureOrigin(): void
    {
        StoreSetting::query()->create([
            'origin_contact_name' => 'Gudang NEVERENDING',
            'origin_contact_phone' => '081234567890',
            'origin_area_id' => self::ORIGIN_AREA_ID,
            'origin_area_name' => 'Kebayoran Baru, Jakarta Selatan, DKI Jakarta. 12110',
            'origin_postal_code' => '12110',
            'origin_address' => 'Jl. Contoh No. 1, Kebayoran Baru',
        ]);
    }

    protected function createPaidOrder(array $orderOverrides = []): array
    {
        $this->configureMidtrans();

        $built = $this->createPendingOrder();

        $built['order']->forceFill(array_merge([
            'payment_status' => 'paid',
            'order_status' => 'processing',
        ], $orderOverrides))->saveQuietly();

        $built['shipment'] = Shipment::query()->create([
            'order_id' => $built['order']->id,
            'courier' => 'J&T Express',
            'courier_code' => 'jnt',
            'service' => 'EZ',
            'status' => 'not_shipped',
        ]);

        return $built;
    }

    protected function createBookedOrder(string $shippingStatus = 'packed'): array
    {
        $built = $this->createPaidOrder([
            'order_status' => $shippingStatus,
            'shipping_status' => $shippingStatus,
        ]);

        $built['shipment']->forceFill([
            'biteship_order_id' => 'bs-order-123',
            'biteship_status' => 'confirmed',
            'biteship_price' => 16000,
            'tracking_number' => 'JNT0001234567',
            'status' => $shippingStatus,
        ])->save();

        return $built;
    }

    protected function biteshipOrderResponse(array $overrides = []): array
    {
        return array_replace_recursive([
            'success' => true,
            'message' => 'Order successfully created',
            'object' => 'order',
            'id' => 'bs-order-123',
            'courier' => [
                'tracking_id' => 'trk-123',
                'waybill_id' => 'JNT0001234567',
                'company' => 'jnt',
                'type' => 'ez',
            ],
            'price' => 16000,
            'status' => 'confirmed',
        ], $overrides);
    }

    protected function notificationTypes(int $orderId): array
    {
        return OrderNotification::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->pluck('type')
            ->all();
    }
}