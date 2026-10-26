<?php

namespace App\Services;

use App\Exceptions\ShippingException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShipmentFulfillmentService
{
    private const PROCESSABLE_ORDER_STATUSES = ['processing', 'packed'];

    private const PROCESSABLE_SHIPPING_STATUSES = ['not_shipped', 'packed'];

    private const WEBHOOK_EVENTS = ['order.status', 'order.waybill_id', 'order.price'];

    private const STATUS_TARGETS = [
        'confirmed' => 'packed',
        'scheduled' => 'packed',
        'allocated' => 'packed',
        'picking_up' => 'packed',
        'picked' => 'shipped',
        'in_transit' => 'shipped',
        'dropping_off' => 'shipped',
        'return_in_transit' => 'shipped',
        'delivered' => 'delivered',
        'returned' => 'returned',
    ];

    private const STATUS_RANK = [
        'not_shipped' => 0,
        'packed' => 1,
        'shipped' => 2,
        'delivered' => 3,
        'returned' => 4,
    ];

    private const ATTENTION_STATUSES = [
        'cancelled',
        'rejected',
        'disposed',
        'courier_not_found',
        'on_hold',
    ];

    public function __construct(
        private readonly BiteshipService $biteship,
        private readonly OrderNotificationService $notifications,
    ) {}

    public function canProcess(Order $order): bool
    {
        $shipment = $order->shipment;

        return $order->payment_status === 'paid'
            && ! $order->stock_released
            && in_array($order->order_status, self::PROCESSABLE_ORDER_STATUSES, true)
            && in_array($order->shipping_status, self::PROCESSABLE_SHIPPING_STATUSES, true)
            && filled($order->shipping_courier_code)
            && blank($shipment?->biteship_order_id)
            && blank($shipment?->tracking_number);
    }

    public function process(Order $order): Shipment
    {
        return DB::transaction(function () use ($order): Shipment {
            $locked = Order::query()
                ->with(['items.product', 'shipment'])
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCanProcess($locked);

            $shipment = $locked->shipment ?? $this->createShipment($locked);

            $result = $this->biteship->createOrder($this->buildPayload($locked));

            $shipment->update([
                'biteship_order_id' => $result['id'],
                'biteship_status' => $result['status'] ?? 'confirmed',
                'biteship_price' => $result['price'],
                'tracking_number' => $result['waybill_id'] ?? $shipment->tracking_number,
                'courier' => $locked->shipping_courier_name,
                'courier_code' => $locked->shipping_courier_code,
                'service' => $locked->shipping_service_name,
            ]);

            $alreadyPacked = $locked->order_status === 'packed';

            $locked->update([
                'order_status' => 'packed',
                'shipping_status' => 'packed',
            ]);

            if (! $alreadyPacked) {
                $this->notifications->notifyOrderPacked($locked);
            }

            return $shipment->refresh();
        });
    }

    public function applyWebhook(array $payload): bool
    {
        $event = (string) ($payload['event'] ?? '');
        $biteshipOrderId = (string) ($payload['order_id'] ?? '');

        if ($biteshipOrderId === '' || ! in_array($event, self::WEBHOOK_EVENTS, true)) {
            return false;
        }

        return DB::transaction(function () use ($payload, $event, $biteshipOrderId): bool {
            $shipment = Shipment::query()
                ->where('biteship_order_id', $biteshipOrderId)
                ->lockForUpdate()
                ->first();

            if (! $shipment) {
                return false;
            }

            $order = Order::query()
                ->whereKey($shipment->order_id)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return false;
            }

            $order->setRelation('shipment', $shipment);

            if ($event === 'order.price') {
                $this->applyPrice($order, $shipment, $payload);

                return true;
            }

            $this->applyStatus($order, $shipment, $payload);

            return true;
        });
    }

    private function applyPrice(Order $order, Shipment $shipment, array $payload): void
    {
        $price = $payload['price'] ?? null;

        if (! is_numeric($price)) {
            return;
        }

        $newPrice = (int) round((float) $price);

        if ($shipment->biteship_price !== null && $shipment->biteship_price !== $newPrice) {
            Log::notice('Biteship: ongkir berubah dari perkiraan awal.', [
                'order_code' => $order->order_code,
                'before' => $shipment->biteship_price,
                'after' => $newPrice,
            ]);
        }

        $shipment->update(['biteship_price' => $newPrice]);
    }

    private function applyStatus(Order $order, Shipment $shipment, array $payload): void
    {
        $status = Str::lower(trim((string) ($payload['status'] ?? '')));
        $waybill = trim((string) ($payload['courier_waybill_id'] ?? ''));
        $target = self::STATUS_TARGETS[$status] ?? null;
        $currentRank = self::STATUS_RANK[$order->shipping_status] ?? 0;
        $isStale = $target !== null && self::STATUS_RANK[$target] < $currentRank;

        $attributes = [];

        if ($status !== '' && ! $isStale) {
            $attributes['biteship_status'] = $status;
        }

        if ($waybill !== '' && $waybill !== $shipment->tracking_number) {
            $attributes['tracking_number'] = $waybill;
        }

        if ($attributes !== []) {
            $shipment->update($attributes);
        }

        if (in_array($status, self::ATTENTION_STATUSES, true)) {
            Log::warning('Biteship: pengiriman butuh perhatian.', [
                'order_code' => $order->order_code,
                'status' => $status,
            ]);
        }

        if ($target === null || ! $this->canAdvance($order->shipping_status, $target)) {
            return;
        }

        if ($order->stock_released || $order->order_status === 'cancelled') {
            Log::warning('Biteship: status pengiriman diabaikan karena pesanan sudah dibatalkan.', [
                'order_code' => $order->order_code,
                'status' => $status,
            ]);

            return;
        }

        $this->advance($order, $shipment, $target);
    }

    private function canAdvance(string $current, string $target): bool
    {
        if (in_array($current, ['delivered', 'returned'], true)) {
            return false;
        }

        if ($target === 'returned') {
            return in_array($current, ['packed', 'shipped'], true);
        }

        return self::STATUS_RANK[$target] > (self::STATUS_RANK[$current] ?? 0);
    }

    private function advance(Order $order, Shipment $shipment, string $target): void
    {
        $orderStatusBefore = $order->order_status;

        if ($target === 'returned') {
            $order->update(['shipping_status' => 'returned']);

            Log::warning('Biteship: paket dikembalikan ke pengirim.', [
                'order_code' => $order->order_code,
            ]);

            return;
        }

        $order->update([
            'order_status' => $target,
            'shipping_status' => $target,
        ]);

        if (in_array($target, ['shipped', 'delivered'], true)) {
            $shipment->update([
                'shipped_at' => $shipment->shipped_at ?? now(),
            ]);
        }

        if ($target === 'delivered') {
            $shipment->update(['delivered_at' => now()]);
        }

        match ($target) {
            'packed' => $orderStatusBefore !== 'packed' ? $this->notifications->notifyOrderPacked($order) : null,
            'shipped' => $this->notifications->notifyOrderShipped($order),
            'delivered' => $this->notifications->notifyOrderDelivered($order),
            default => null,
        };
    }

    private function assertCanProcess(Order $order): void
    {
        if ($order->payment_status !== 'paid') {
            throw new ShippingException('Only paid orders can be shipped.');
        }

        if (
            $order->stock_released
            || ! in_array($order->order_status, self::PROCESSABLE_ORDER_STATUSES, true)
            || ! in_array($order->shipping_status, self::PROCESSABLE_SHIPPING_STATUSES, true)
        ) {
            throw new ShippingException('This order is not in a state that can be shipped.');
        }

        if (filled($order->shipment?->biteship_order_id)) {
            throw new ShippingException('This order already has a Biteship shipment.');
        }

        if (filled($order->shipment?->tracking_number)) {
            throw new ShippingException('A tracking number was already entered manually. Clear it first to create a Biteship shipment.');
        }

        foreach (['shipping_courier_code', 'shipping_service_code', 'area_id', 'recipient_name', 'phone', 'full_address'] as $field) {
            if (blank($order->{$field})) {
                throw new ShippingException('This order has incomplete shipping data, so it cannot be sent through Biteship.');
            }
        }
    }

    private function createShipment(Order $order): Shipment
    {
        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'courier' => $order->shipping_courier_name,
            'courier_code' => $order->shipping_courier_code,
            'service' => $order->shipping_service_name,
            'status' => 'not_shipped',
        ]);

        $order->setRelation('shipment', $shipment);

        return $shipment;
    }

    private function buildPayload(Order $order): array
    {
        $origin = StoreSetting::current();

        if (
            ! $origin->hasOrigin()
            || blank($origin->origin_contact_name)
            || blank($origin->origin_contact_phone)
            || blank($origin->origin_address)
            || blank($origin->origin_postal_code)
        ) {
            throw new ShippingException('The shipping origin is incomplete. Fill it in under Settings > Store Settings.');
        }

        $payload = [
            'shipper_contact_name' => $origin->origin_contact_name,
            'shipper_contact_phone' => $origin->origin_contact_phone,
            'shipper_organization' => (string) config('app.name'),
            'origin_contact_name' => $origin->origin_contact_name,
            'origin_contact_phone' => $origin->origin_contact_phone,
            'origin_address' => $origin->origin_address,
            'origin_postal_code' => (int) $origin->origin_postal_code,
            'origin_area_id' => $origin->origin_area_id,
            'destination_contact_name' => $order->recipient_name,
            'destination_contact_phone' => $order->phone,
            'destination_address' => $order->full_address,
            'destination_area_id' => $order->area_id,
            'courier_company' => Str::lower((string) $order->shipping_courier_code),
            'courier_type' => Str::lower((string) $order->shipping_service_code),
            'delivery_type' => 'now',
            'order_note' => 'Order '.$order->order_code,
            'reference_id' => $order->order_code,
            'metadata' => ['order_code' => $order->order_code],
            'items' => $this->buildItems($order),
        ];

        if (filled($order->postal_code)) {
            $payload['destination_postal_code'] = (int) $order->postal_code;
        }

        if ((float) $order->shipping_insurance_fee > 0) {
            $payload['courier_insurance'] = (int) round((float) $order->subtotal);
        }

        return $payload;
    }

    private function buildItems(Order $order): array
    {
        if ($order->items->isEmpty()) {
            throw new ShippingException('This order has no items.');
        }

        return $order->items->map(function (OrderItem $item): array {
            $weight = (int) ($item->product?->weight ?? 0);

            if ($weight < 1) {
                throw new ShippingException('The weight of "'.$item->product_name.'" is not set, so the shipment cannot be created.');
            }

            return array_filter([
                'name' => Str::limit(trim($item->product_name.' '.$item->variant_name), 100, ''),
                'description' => $item->variant_name,
                'category' => 'fashion',
                'sku' => $item->sku,
                'value' => (int) round((float) $item->price),
                'quantity' => (int) $item->quantity,
                'weight' => $weight,
            ], fn ($value) => $value !== null && $value !== '');
        })->values()->all();
    }
}