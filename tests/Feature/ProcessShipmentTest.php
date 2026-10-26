<?php

namespace Tests\Feature;

use App\Exceptions\ShippingException;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\ShipmentFulfillmentService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\Concerns\BuildsBiteshipShipments;
use Tests\TestCase;

class ProcessShipmentTest extends TestCase
{
    use BuildsBiteshipShipments;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureBiteship();
        $this->configureOrigin();

        Filament::setCurrentPanel('admin');
    }

    private function service(): ShipmentFulfillmentService
    {
        return app(ShipmentFulfillmentService::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        return $admin;
    }

    public function test_processing_a_paid_order_books_the_courier_and_marks_it_packed(): void
    {
        Http::fake(['api.biteship.com/v1/orders' => Http::response($this->biteshipOrderResponse())]);

        ['order' => $order, 'shipment' => $shipment] = $this->createPaidOrder();

        $this->service()->process($order);

        Http::assertSent(function ($request) use ($order) {
            $data = $request->data();

            return $request->url() === 'https://api.biteship.com/v1/orders'
                && $request->hasHeader('authorization', 'biteship_test.dummy')
                && $data['reference_id'] === $order->order_code
                && $data['courier_company'] === 'jnt'
                && $data['courier_type'] === 'ez'
                && $data['delivery_type'] === 'now'
                && $data['origin_area_id'] === self::ORIGIN_AREA_ID
                && $data['origin_postal_code'] === 12110
                && $data['origin_contact_name'] === 'Gudang NEVERENDING'
                && $data['destination_area_id'] === 'IDNP6IDNC148IDND843IDZ12250'
                && $data['destination_postal_code'] === 12250
                && $data['destination_contact_name'] === 'Test Recipient'
                && $data['courier_insurance'] === 300000
                && count($data['items']) === 1
                && $data['items'][0]['quantity'] === 2
                && $data['items'][0]['value'] === 150000
                && $data['items'][0]['weight'] === 300
                && $data['items'][0]['category'] === 'fashion';
        });

        $shipment->refresh();
        $order->refresh();

        $this->assertSame('bs-order-123', $shipment->biteship_order_id);
        $this->assertSame('confirmed', $shipment->biteship_status);
        $this->assertSame(16000, $shipment->biteship_price);
        $this->assertSame('JNT0001234567', $shipment->tracking_number);
        $this->assertSame('packed', $shipment->status);
        $this->assertSame('packed', $order->order_status);
        $this->assertSame('packed', $order->shipping_status);
        $this->assertSame(['order_packed'], $this->notificationTypes($order->id));
    }

    public function test_the_courier_is_never_booked_twice_for_the_same_order(): void
    {
        Http::fake(['api.biteship.com/v1/orders' => Http::response($this->biteshipOrderResponse())]);

        ['order' => $order] = $this->createPaidOrder();

        $this->service()->process($order);

        try {
            $this->service()->process($order);
            $this->fail('A second shipment was accepted.');
        } catch (ShippingException $e) {
            $this->assertStringContainsString('already has a Biteship shipment', $e->getMessage());
        }

        Http::assertSentCount(1);
        $this->assertSame(['order_packed'], $this->notificationTypes($order->id));
    }

    public function test_an_unpaid_order_cannot_be_processed(): void
    {
        Http::fake();

        ['order' => $order] = $this->createPaidOrder(['payment_status' => 'pending', 'order_status' => 'pending']);

        $this->expectException(ShippingException::class);

        try {
            $this->service()->process($order);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_a_cancelled_order_cannot_be_processed(): void
    {
        Http::fake();

        ['order' => $order] = $this->createPaidOrder([
            'payment_status' => 'refunded',
            'order_status' => 'cancelled',
            'stock_released' => true,
        ]);

        $this->assertFalse($this->service()->canProcess($order->fresh('shipment')));

        $this->expectException(ShippingException::class);

        try {
            $this->service()->process($order);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_a_manually_entered_tracking_number_blocks_processing(): void
    {
        Http::fake();

        ['order' => $order, 'shipment' => $shipment] = $this->createPaidOrder();
        $shipment->update(['tracking_number' => 'MANUAL123']);

        $this->assertFalse($this->service()->canProcess($order->fresh('shipment')));

        $this->expectException(ShippingException::class);
        $this->expectExceptionMessage('entered manually');

        try {
            $this->service()->process($order);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_a_missing_shipping_origin_is_reported_before_calling_biteship(): void
    {
        Http::fake();

        StoreSetting::query()->delete();

        ['order' => $order] = $this->createPaidOrder();

        $this->expectException(ShippingException::class);
        $this->expectExceptionMessage('shipping origin is incomplete');

        try {
            $this->service()->process($order);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_a_product_without_weight_is_reported_before_calling_biteship(): void
    {
        Http::fake();

        ['order' => $order] = $this->createPaidOrder();

        Product::query()->whereKey($order->items()->first()->product_id)->update(['weight' => 0]);

        $this->expectException(ShippingException::class);
        $this->expectExceptionMessage('weight');

        try {
            $this->service()->process($order);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_a_biteship_rejection_leaves_the_order_untouched(): void
    {
        Http::fake([
            'api.biteship.com/v1/orders' => Http::response([
                'success' => false,
                'error' => 'Insufficient balance',
                'code' => 40001234,
            ], 400),
        ]);

        ['order' => $order, 'shipment' => $shipment] = $this->createPaidOrder();

        try {
            $this->service()->process($order);
            $this->fail('The rejection was not reported.');
        } catch (ShippingException $e) {
            $this->assertStringContainsString('Insufficient balance', $e->getMessage());
        }

        $this->assertSame('processing', $order->fresh()->order_status);
        $this->assertSame('not_shipped', $order->fresh()->shipping_status);
        $this->assertNull($shipment->fresh()->biteship_order_id);
        $this->assertSame([], $this->notificationTypes($order->id));
    }

    public function test_an_unreachable_biteship_is_reported_and_changes_nothing(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        ['order' => $order, 'shipment' => $shipment] = $this->createPaidOrder();

        $this->expectException(ShippingException::class);
        $this->expectExceptionMessage('could not be reached');

        try {
            $this->service()->process($order);
        } finally {
            $this->assertNull($shipment->fresh()->biteship_order_id);
            $this->assertSame('processing', $order->fresh()->order_status);
        }
    }

    public function test_a_reference_that_biteship_already_knows_is_adopted_instead_of_duplicated(): void
    {
        Http::fake([
            'api.biteship.com/v1/orders' => Http::response([
                'success' => false,
                'error' => 'Reference id has already been used before. Please input other reference id',
                'code' => 40002060,
                'details' => [
                    'order_id' => 'bs-existing-1',
                    'waybill_id' => 'JNT9999999999',
                    'reference_id' => 'whatever',
                ],
            ], 400),
        ]);

        ['order' => $order, 'shipment' => $shipment] = $this->createPaidOrder();

        $this->service()->process($order);

        $shipment->refresh();

        $this->assertSame('bs-existing-1', $shipment->biteship_order_id);
        $this->assertSame('JNT9999999999', $shipment->tracking_number);
        $this->assertSame('confirmed', $shipment->biteship_status);
        $this->assertSame('packed', $order->fresh()->shipping_status);
    }

    public function test_the_courier_company_and_type_are_sent_in_lowercase(): void
    {
        Http::fake(['api.biteship.com/v1/orders' => Http::response($this->biteshipOrderResponse())]);

        ['order' => $order] = $this->createPaidOrder([
            'shipping_courier_code' => 'JNE',
            'shipping_service_code' => 'REG',
        ]);

        $this->service()->process($order);

        Http::assertSent(fn ($request) => $request['courier_company'] === 'jne' && $request['courier_type'] === 'reg');
    }

    public function test_insurance_is_only_requested_when_it_was_charged(): void
    {
        Http::fake(['api.biteship.com/v1/orders' => Http::response($this->biteshipOrderResponse())]);

        ['order' => $order] = $this->createPaidOrder(['shipping_insurance_fee' => 0]);

        $this->service()->process($order);

        Http::assertSent(fn ($request) => ! array_key_exists('courier_insurance', $request->data()));
    }

    public function test_can_process_follows_the_order_state(): void
    {
        ['order' => $order, 'shipment' => $shipment] = $this->createPaidOrder();

        $this->assertTrue($this->service()->canProcess($order->fresh('shipment')));

        $order->forceFill(['payment_status' => 'pending'])->saveQuietly();
        $this->assertFalse($this->service()->canProcess($order->fresh('shipment')));

        $order->forceFill(['payment_status' => 'paid', 'order_status' => 'shipped', 'shipping_status' => 'shipped'])->saveQuietly();
        $this->assertFalse($this->service()->canProcess($order->fresh('shipment')));

        $order->forceFill(['order_status' => 'processing', 'shipping_status' => 'not_shipped'])->saveQuietly();
        $shipment->update(['biteship_order_id' => 'bs-1']);
        $this->assertFalse($this->service()->canProcess($order->fresh('shipment')));
    }

    public function test_admin_can_process_a_shipment_from_the_orders_list(): void
    {
        Http::fake(['api.biteship.com/v1/orders' => Http::response($this->biteshipOrderResponse())]);

        ['order' => $order] = $this->createPaidOrder();

        Livewire::actingAs($this->admin())
            ->test(ListOrders::class)
            ->assertTableActionVisible('processShipment', $order)
            ->callTableAction('processShipment', $order)
            ->assertNotified('Shipment processed');

        $this->assertSame('packed', $order->fresh()->shipping_status);
        $this->assertSame('JNT0001234567', $order->fresh()->shipment->tracking_number);
    }

    public function test_the_process_action_is_hidden_for_orders_that_cannot_be_shipped(): void
    {
        ['order' => $order] = $this->createPaidOrder(['payment_status' => 'pending', 'order_status' => 'pending']);

        Livewire::actingAs($this->admin())
            ->test(ListOrders::class)
            ->assertTableActionHidden('processShipment', $order);
    }

    public function test_admin_can_process_a_shipment_from_the_order_page(): void
    {
        Http::fake(['api.biteship.com/v1/orders' => Http::response($this->biteshipOrderResponse())]);

        ['order' => $order] = $this->createPaidOrder();

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction('processShipment')
            ->assertNotified('Shipment processed');

        $this->assertSame('packed', $order->fresh()->order_status);
    }

    public function test_a_failure_is_shown_to_the_admin_and_the_order_stays_as_it_was(): void
    {
        Http::fake();

        StoreSetting::query()->delete();

        ['order' => $order] = $this->createPaidOrder();

        Livewire::actingAs($this->admin())
            ->test(ListOrders::class)
            ->callTableAction('processShipment', $order)
            ->assertNotified('Shipment could not be processed');

        $this->assertSame('processing', $order->fresh()->order_status);
        Http::assertNothingSent();
    }

    public function test_customers_never_receive_internal_shipment_fields(): void
    {
        ['user' => $user, 'order' => $order, 'shipment' => $shipment] = $this->createBookedOrder();

        $shipment->update(['tracking_raw_response' => ['secret' => 'internal']]);

        $this->actingAs($user)
            ->get(route('orders.show', $order->order_code))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Orders/Show')
                ->has('order.shipment')
                ->where('order.shipment.tracking_number', 'JNT0001234567')
                ->missing('order.shipment.tracking_raw_response')
                ->missing('order.shipment.biteship_order_id')
                ->missing('order.shipment.biteship_status')
                ->missing('order.shipment.biteship_price'));
    }
}