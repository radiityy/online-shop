<?php

namespace Tests\Feature;

use App\Http\Controllers\Store\OrderController;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsMidtransOrders;
use Tests\TestCase;

class LatePaymentRefundTest extends TestCase
{
    use BuildsMidtransOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureMidtrans();
    }

    public function test_money_arriving_for_a_cancelled_order_is_flagged_for_refund_and_the_order_stays_cancelled(): void
    {
        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        $order->update(['order_status' => 'cancelled']);

        $this->assertSame(10, $variant->fresh()->stock);

        $this->settle($payment);

        $payment->refresh();
        $order->refresh();

        $this->assertSame(Payment::STATUS_REFUND_REQUIRED, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('refund_required', $order->payment_status);
        $this->assertSame('cancelled', $order->order_status);
        $this->assertTrue($order->stock_released);
        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_money_arriving_for_an_expired_order_is_flagged_for_refund_and_the_order_is_not_reactivated(): void
    {
        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        $order->update(['payment_status' => 'expired']);

        $this->assertSame(10, $variant->fresh()->stock);

        $this->settle($payment);

        $order->refresh();

        $this->assertSame(Payment::STATUS_REFUND_REQUIRED, $payment->fresh()->status);
        $this->assertSame('refund_required', $order->payment_status);
        $this->assertNotSame('processing', $order->order_status);
        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_a_repeated_settlement_notification_keeps_the_refund_flag_and_changes_nothing(): void
    {
        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        $order->update(['order_status' => 'cancelled']);

        $this->settle($payment);
        $this->settle($payment);

        $this->assertSame(Payment::STATUS_REFUND_REQUIRED, $payment->fresh()->status);
        $this->assertSame('refund_required', $order->fresh()->payment_status);
        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_a_second_payment_on_an_already_paid_order_is_flagged_and_the_order_stays_paid(): void
    {
        ['order' => $order, 'payment' => $first, 'variant' => $variant] = $this->createPendingOrder();

        $this->settle($first);

        $second = $this->createPayment($order, 'qris', 2);

        $this->settle($second);

        $order->refresh();

        $this->assertSame(Payment::STATUS_PAID, $first->fresh()->status);
        $this->assertSame(Payment::STATUS_REFUND_REQUIRED, $second->fresh()->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->order_status);
        $this->assertSame(8, $variant->fresh()->stock);
    }

    public function test_the_refund_notification_closes_a_refund_required_order_without_returning_stock_twice(): void
    {
        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        $order->update(['order_status' => 'cancelled']);

        $this->settle($payment);
        $this->settle($payment, 'refund');

        $order->refresh();

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame('cancelled', $order->order_status);
        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_refunding_the_duplicate_payment_does_not_refund_the_paid_order(): void
    {
        ['order' => $order, 'payment' => $first, 'variant' => $variant] = $this->createPendingOrder();

        $this->settle($first);

        $second = $this->createPayment($order, 'qris', 2);

        $this->settle($second);
        $this->settle($second, 'refund');

        $order->refresh();

        $this->assertSame(Payment::STATUS_PAID, $first->fresh()->status);
        $this->assertSame(Payment::STATUS_REFUNDED, $second->fresh()->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->order_status);
        $this->assertSame(8, $variant->fresh()->stock);
    }

    public function test_refunding_a_normally_paid_order_still_marks_it_refunded(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder();

        $this->settle($payment);
        $this->settle($payment, 'refund');

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    public function test_an_expired_order_with_a_late_payment_can_still_be_reordered(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment] = $this->createPendingOrder();

        $order->update(['payment_status' => 'expired']);

        $this->settle($payment);

        $this->callAction($user, 'reorder', $order)->assertRedirect(route('cart.index'));

        $cart = Cart::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(2, (int) $cart->items()->sum('quantity'));
    }

    public function test_marking_a_refund_manually_closes_a_refund_required_order_without_returning_stock_twice(): void
    {
        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        $order->update(['order_status' => 'cancelled']);

        $this->settle($payment);

        $this->assertTrue(app(PaymentSettlementService::class)->markRefundedManually($order));

        $order->refresh();

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame('cancelled', $order->order_status);
        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_marking_a_refund_manually_for_a_duplicate_payment_keeps_the_paid_order_paid(): void
    {
        ['order' => $order, 'payment' => $first, 'variant' => $variant] = $this->createPendingOrder();

        $this->settle($first);

        $second = $this->createPayment($order, 'qris', 2);

        $this->settle($second);

        $this->assertTrue(app(PaymentSettlementService::class)->markRefundedManually($order));

        $order->refresh();

        $this->assertSame(Payment::STATUS_PAID, $first->fresh()->status);
        $this->assertSame(Payment::STATUS_REFUNDED, $second->fresh()->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->order_status);
        $this->assertSame(8, $variant->fresh()->stock);
    }

    public function test_marking_a_refund_manually_twice_changes_nothing_the_second_time(): void
    {
        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        $order->update(['order_status' => 'cancelled']);

        $this->settle($payment);

        $service = app(PaymentSettlementService::class);

        $this->assertTrue($service->markRefundedManually($order));
        $this->assertFalse($service->markRefundedManually($order));

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_marking_a_refund_manually_does_nothing_when_no_refund_is_needed(): void
    {
        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        $this->assertFalse(app(PaymentSettlementService::class)->markRefundedManually($order));

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(8, $variant->fresh()->stock);
    }

    public function test_marking_a_refund_manually_releases_stock_that_was_still_held(): void
    {
        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        // Admin mengubah status lewat form tanpa stok terlepas: refund_required tidak melepas stok.
        $order->update(['payment_status' => 'refund_required']);

        $this->assertFalse($order->fresh()->stock_released);
        $this->assertSame(8, $variant->fresh()->stock);

        $this->assertTrue(app(PaymentSettlementService::class)->markRefundedManually($order));

        $order->refresh();

        $this->assertSame('refunded', $order->payment_status);
        $this->assertTrue($order->stock_released);
        $this->assertSame(10, $variant->fresh()->stock);
    }

    private function settle(Payment $payment, string $transactionStatus = 'settlement'): Payment
    {
        $body = $this->midtransStatusBody($payment, $transactionStatus);

        return app(PaymentSettlementService::class)->apply($payment, [
            'transaction_id' => $body['transaction_id'],
            'gateway_status' => $body['transaction_status'],
            'fraud_status' => $body['fraud_status'],
            'raw' => $body,
        ]);
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