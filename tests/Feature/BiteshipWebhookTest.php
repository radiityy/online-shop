<?php

namespace Tests\Feature;

use App\Models\OrderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBiteshipShipments;
use Tests\TestCase;

class BiteshipWebhookTest extends TestCase
{
    use BuildsBiteshipShipments;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBiteship();
    }

    private function send(array $payload, ?string $secret = self::WEBHOOK_SECRET)
    {
        $headers = $secret === null ? [] : ['X-Biteship-Secret' => $secret];

        return $this->postJson(route('webhooks.biteship'), $payload, $headers);
    }

    private function statusEvent(string $status, array $extra = []): array
    {
        return array_merge([
            'event' => 'order.status',
            'order_id' => 'bs-order-123',
            'courier_tracking_id' => 'trk-123',
            'courier_waybill_id' => 'JNT0001234567',
            'courier_company' => 'JNT',
            'courier_type' => 'EZ',
            'status' => $status,
        ], $extra);
    }

    public function test_a_request_without_the_secret_header_is_rejected(): void
    {
        ['order' => $order, 'shipment' => $shipment] = $this->createBookedOrder();

        $this->send($this->statusEvent('picked'), null)->assertForbidden();

        $this->assertSame('packed', $order->fresh()->shipping_status);
        $this->assertSame('confirmed', $shipment->fresh()->biteship_status);
    }

    public function test_a_request_with_the_wrong_secret_is_rejected(): void
    {
        ['order' => $order] = $this->createBookedOrder();

        $this->send($this->statusEvent('picked'), 'wrong-secret')->assertForbidden();

        $this->assertSame('packed', $order->fresh()->shipping_status);
    }

    public function test_every_request_is_rejected_when_no_secret_is_configured(): void
    {
        config(['services.biteship.webhook_secret' => '']);

        ['order' => $order] = $this->createBookedOrder();

        $this->send($this->statusEvent('picked'), '')->assertForbidden();
        $this->send($this->statusEvent('picked'), null)->assertForbidden();

        $this->assertSame('packed', $order->fresh()->shipping_status);
    }

    public function test_an_event_for_an_unknown_biteship_order_is_ignored(): void
    {
        ['order' => $order] = $this->createBookedOrder();

        $this->send($this->statusEvent('picked', ['order_id' => 'someone-elses-order']))
            ->assertOk()
            ->assertJson(['message' => 'Ignored.']);

        $this->assertSame('packed', $order->fresh()->shipping_status);
    }

    public function test_an_unknown_event_type_is_ignored(): void
    {
        ['order' => $order] = $this->createBookedOrder();

        $this->send($this->statusEvent('picked', ['event' => 'order.something_new']))
            ->assertOk()
            ->assertJson(['message' => 'Ignored.']);

        $this->assertSame('packed', $order->fresh()->shipping_status);
    }

    public function test_a_pickup_marks_the_order_shipped_and_notifies_the_customer(): void
    {
        ['order' => $order, 'shipment' => $shipment] = $this->createBookedOrder();

        $this->send($this->statusEvent('picked'))->assertOk()->assertJson(['message' => 'OK']);

        $order->refresh();
        $shipment->refresh();

        $this->assertSame('shipped', $order->order_status);
        $this->assertSame('shipped', $order->shipping_status);
        $this->assertSame('shipped', $shipment->status);
        $this->assertSame('picked', $shipment->biteship_status);
        $this->assertNotNull($shipment->shipped_at);
        $this->assertSame(['order_shipped'], $this->notificationTypes($order->id));
        $this->assertStringContainsString(
            'JNT0001234567',
            OrderNotification::query()->where('order_id', $order->id)->value('message'),
        );
    }

    public function test_a_delivery_marks_the_order_delivered_and_notifies_the_customer(): void
    {
        ['order' => $order, 'shipment' => $shipment] = $this->createBookedOrder('shipped');

        $this->send($this->statusEvent('delivered'))->assertOk();

        $order->refresh();
        $shipment->refresh();

        $this->assertSame('delivered', $order->order_status);
        $this->assertSame('delivered', $order->shipping_status);
        $this->assertSame('delivered', $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertSame(['order_delivered'], $this->notificationTypes($order->id));
    }

    public function test_the_same_event_delivered_twice_changes_nothing_the_second_time(): void
    {
        ['order' => $order] = $this->createBookedOrder('shipped');

        $this->send($this->statusEvent('delivered'))->assertOk();
        $this->send($this->statusEvent('delivered'))->assertOk();

        $this->assertSame(['order_delivered'], $this->notificationTypes($order->id));
    }

    public function test_an_older_event_arriving_late_never_moves_the_order_backwards(): void
    {
        ['order' => $order, 'shipment' => $shipment] = $this->createBookedOrder('shipped');

        $this->send($this->statusEvent('delivered'))->assertOk();
        $this->send($this->statusEvent('picked'))->assertOk();
        $this->send($this->statusEvent('confirmed'))->assertOk();

        $this->assertSame('delivered', $order->fresh()->shipping_status);
        $this->assertSame('delivered', $order->fresh()->order_status);
        $this->assertSame('delivered', $shipment->fresh()->biteship_status);
        $this->assertSame(['order_delivered'], $this->notificationTypes($order->id));
    }

    public function test_in_transit_counts_as_shipped(): void
    {
        ['order' => $order] = $this->createBookedOrder();

        $this->send($this->statusEvent('in_transit'))->assertOk();

        $this->assertSame('shipped', $order->fresh()->shipping_status);
    }

    public function test_a_missed_pickup_event_does_not_block_a_later_delivery(): void
    {
        ['order' => $order] = $this->createBookedOrder();

        $this->send($this->statusEvent('delivered'))->assertOk();

        $this->assertSame('delivered', $order->fresh()->shipping_status);
        $this->assertSame(['order_delivered'], $this->notificationTypes($order->id));
    }

    public function test_a_waybill_event_updates_the_tracking_number_and_status(): void
    {
        ['order' => $order, 'shipment' => $shipment] = $this->createBookedOrder();

        $this->send([
            'event' => 'order.waybill_id',
            'order_id' => 'bs-order-123',
            'courier_tracking_id' => 'trk-123',
            'courier_waybill_id' => 'JNT7777777777',
            'status' => 'picked',
        ])->assertOk();

        $this->assertSame('JNT7777777777', $shipment->fresh()->tracking_number);
        $this->assertSame('shipped', $order->fresh()->shipping_status);
    }

    public function test_a_price_event_records_the_new_cost_without_touching_the_order(): void
    {
        ['order' => $order, 'shipment' => $shipment] = $this->createBookedOrder();

        $total = $order->fresh()->total;

        $this->send([
            'event' => 'order.price',
            'order_id' => 'bs-order-123',
            'courier_waybill_id' => 'JNT0001234567',
            'price' => 21000,
            'status' => 'picked',
        ])->assertOk();

        $this->assertSame(21000, $shipment->fresh()->biteship_price);
        $this->assertSame('packed', $order->fresh()->shipping_status);
        $this->assertEquals($total, $order->fresh()->total);
    }

    public function test_a_returned_package_marks_shipping_returned_but_keeps_the_order_status(): void
    {
        ['order' => $order] = $this->createBookedOrder('shipped');

        $this->send($this->statusEvent('returned'))->assertOk();

        $this->assertSame('returned', $order->fresh()->shipping_status);
        $this->assertSame('shipped', $order->fresh()->order_status);
    }

    public function test_a_return_cannot_overwrite_a_delivery(): void
    {
        ['order' => $order] = $this->createBookedOrder('shipped');

        $this->send($this->statusEvent('delivered'))->assertOk();
        $this->send($this->statusEvent('returned'))->assertOk();

        $this->assertSame('delivered', $order->fresh()->shipping_status);
    }

    public function test_a_problem_status_is_recorded_without_changing_the_order(): void
    {
        ['order' => $order, 'shipment' => $shipment] = $this->createBookedOrder();

        $this->send($this->statusEvent('courier_not_found'))->assertOk();

        $this->assertSame('courier_not_found', $shipment->fresh()->biteship_status);
        $this->assertSame('packed', $order->fresh()->shipping_status);
        $this->assertSame('packed', $order->fresh()->order_status);
    }

    public function test_a_cancelled_order_is_never_reactivated_by_a_late_shipping_event(): void
    {
        ['order' => $order] = $this->createBookedOrder();

        $order->forceFill([
            'payment_status' => 'refunded',
            'order_status' => 'cancelled',
            'stock_released' => true,
        ])->saveQuietly();

        $this->send($this->statusEvent('picked'))->assertOk();

        $order->refresh();

        $this->assertSame('cancelled', $order->order_status);
        $this->assertSame('packed', $order->shipping_status);
        $this->assertSame([], $this->notificationTypes($order->id));
    }
}