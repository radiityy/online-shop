<?php

namespace Tests\Feature;

use App\Http\Controllers\Store\OrderController;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsMidtransOrders;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use BuildsMidtransOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureMidtrans();
    }

    public function test_cancelling_an_unpaid_order_closes_the_payment_and_returns_the_stock(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/status' => Http::response($this->midtransStatusBody($payment)),
            'api.sandbox.midtrans.com/v2/*/cancel' => Http::response($this->midtransCancelBody($payment)),
        ]);

        $this->callAction($user, 'cancel', $order)
            ->assertRedirect(route('orders.show', $order->order_code))
            ->assertSessionHas('success', 'Your order has been cancelled.');

        $order->refresh();

        $this->assertSame('cancelled', $order->order_status);
        $this->assertTrue($order->stock_released);
        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(Payment::STATUS_CANCELLED, $payment->fresh()->status);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/v2/' . $payment->gateway_order_id . '/cancel')
            && $request->body() === '');
    }

    public function test_cancelling_keeps_the_order_open_when_the_gateway_fails_and_the_payment_is_still_pending(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/status' => Http::response($this->midtransStatusBody($payment)),
            'api.sandbox.midtrans.com/v2/*/cancel' => Http::response([
                'status_code' => '500',
                'status_message' => 'Sorry. Our system is recovering from unexpected issues. Please retry.',
            ], 500),
        ]);

        $this->callAction($user, 'cancel', $order)
            ->assertSessionHas('error', 'We could not close your payment with the payment provider. Please try again in a moment.');

        $order->refresh();

        $this->assertSame('pending', $order->order_status);
        $this->assertFalse($order->stock_released);
        $this->assertSame(8, $variant->fresh()->stock);
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_cancelling_succeeds_when_the_gateway_refuses_but_the_transaction_is_already_closed(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/status' => Http::sequence()
                ->push($this->midtransStatusBody($payment))
                ->push($this->midtransStatusBody($payment, 'cancel')),
            'api.sandbox.midtrans.com/v2/*/cancel' => Http::response([
                'status_code' => '412',
                'status_message' => 'Transaction status cannot be updated.',
            ], 412),
        ]);

        $this->callAction($user, 'cancel', $order)
            ->assertSessionHas('success', 'Your order has been cancelled.');

        $this->assertSame('cancelled', $order->fresh()->order_status);
        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(Payment::STATUS_CANCELLED, $payment->fresh()->status);
    }

    public function test_cancelling_is_refused_when_the_payment_just_arrived(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        Http::fake([
            'api.sandbox.midtrans.com/v2/*/status' => Http::response($this->midtransStatusBody($payment, 'settlement')),
        ]);

        $this->callAction($user, 'cancel', $order)
            ->assertSessionHas('error', 'Your payment just arrived, so this order cannot be cancelled. We are processing it now.');

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->order_status);
        $this->assertFalse($order->stock_released);
        $this->assertSame(8, $variant->fresh()->stock);

        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/cancel'));
    }

    public function test_a_customer_cannot_cancel_another_customers_order(): void
    {
        ['order' => $order, 'variant' => $variant] = $this->createPendingOrder();

        $intruder = User::factory()->create(['role' => 'customer']);

        Http::fake();

        $this->callAction($intruder, 'cancel', $order)->assertNotFound();

        $this->assertSame('pending', $order->fresh()->order_status);
        $this->assertSame(8, $variant->fresh()->stock);

        Http::assertNothingSent();
    }

    public function test_reordering_adds_the_items_back_once_and_never_doubles_them(): void
    {
        ['user' => $user, 'order' => $order, 'variant' => $variant] = $this->createPendingOrder();

        $order->update(['order_status' => 'cancelled']);

        $this->assertSame(10, $variant->fresh()->stock);

        $this->callAction($user, 'reorder', $order)->assertRedirect(route('cart.index'));
        $this->callAction($user, 'reorder', $order)->assertRedirect(route('cart.index'));

        $cart = Cart::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(1, $cart->items()->count());
        $this->assertSame(2, (int) $cart->items()->sum('quantity'));
    }

    public function test_reordering_reduces_quantities_to_the_available_stock(): void
    {
        ['user' => $user, 'order' => $order, 'variant' => $variant] = $this->createPendingOrder();

        $order->update(['order_status' => 'cancelled']);

        $variant->forceFill(['stock' => 1])->save();

        $this->callAction($user, 'reorder', $order)
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'reduced'));

        $cart = Cart::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(1, (int) $cart->items()->sum('quantity'));
    }

    public function test_a_paid_order_cannot_be_reordered(): void
    {
        ['user' => $user, 'order' => $order] = $this->createPendingOrder();

        $order->forceFill([
            'payment_status' => 'paid',
            'order_status' => 'processing',
        ])->saveQuietly();

        $this->callAction($user, 'reorder', $order)
            ->assertRedirect(route('orders.show', $order->order_code))
            ->assertSessionHas('error', 'This order cannot be re-ordered.');

        $this->assertNull(Cart::query()->where('user_id', $user->id)->first());
    }

    private function callAction(User $user, string $action, Order $order): TestResponse
    {
        foreach (app('router')->getRoutes() as $route) {
            if ($route->getActionName() === OrderController::class . '@' . $action) {
                $uri = '/' . ltrim(preg_replace('/\{[^}]+\}/', $order->order_code, $route->uri()), '/');

                return $this->actingAs($user)->call($route->methods()[0], $uri);
            }
        }

        $this->fail("Rute untuk {$action} tidak ditemukan.");
    }
}