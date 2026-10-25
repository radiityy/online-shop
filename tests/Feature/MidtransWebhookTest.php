<?php

namespace Tests\Feature;

use App\Http\Controllers\Store\MidtransWebhookController;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\BuildsMidtransOrders;
use Tests\TestCase;

class MidtransWebhookTest extends TestCase
{
    use BuildsMidtransOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureMidtrans();
    }

    public function test_a_valid_settlement_marks_the_order_paid(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder();

        $this->postJson($this->webhookUri(), $this->webhookPayload($payment, 'settlement'))
            ->assertOk();

        $payment->refresh();
        $order->refresh();

        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->order_status);
    }

    public function test_a_notification_with_an_invalid_signature_is_rejected(): void
    {
        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        $payload = $this->webhookPayload($payment, 'settlement');
        $payload['signature_key'] = str_repeat('0', 128);

        $this->postJson($this->webhookUri(), $payload)->assertForbidden();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(8, $variant->fresh()->stock);
    }

    public function test_a_notification_for_an_unknown_order_is_ignored(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder();

        $payload = $this->webhookPayload($payment, 'settlement', [
            'order_id' => 'NE-00000000-UNKNOWN-1',
        ]);

        $this->postJson($this->webhookUri(), $payload)
            ->assertOk()
            ->assertJson(['message' => 'Ignored.']);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_a_notification_with_a_mismatched_amount_is_ignored(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder();

        $payload = $this->webhookPayload($payment, 'settlement', [
            'gross_amount' => '1000.00',
        ]);

        $this->postJson($this->webhookUri(), $payload)->assertOk();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->paid_at);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->order_status);
    }

    public function test_a_repeated_settlement_notification_changes_nothing(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder();

        $payload = $this->webhookPayload($payment, 'settlement');

        $this->postJson($this->webhookUri(), $payload)->assertOk();

        $paidAt = $payment->fresh()->paid_at;

        $this->postJson($this->webhookUri(), $payload)->assertOk();

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertEquals($paidAt, $payment->fresh()->paid_at);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('processing', $order->fresh()->order_status);
    }

    public function test_an_expire_notice_before_the_order_deadline_leaves_the_order_open(): void
    {
        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        $payload = $this->webhookPayload($payment, 'expire', ['status_code' => '407']);

        $this->postJson($this->webhookUri(), $payload)->assertOk();

        $this->assertSame(Payment::STATUS_EXPIRED, $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(8, $variant->fresh()->stock);
    }

    public function test_an_expire_notice_after_the_order_deadline_closes_the_order_and_releases_stock(): void
    {
        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        $order->forceFill(['payment_expires_at' => now()->subMinute()])->saveQuietly();

        $payload = $this->webhookPayload($payment, 'expire', ['status_code' => '407']);

        $this->postJson($this->webhookUri(), $payload)->assertOk();

        $order->refresh();

        $this->assertSame('expired', $order->payment_status);
        $this->assertTrue($order->stock_released);
        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_paying_a_replaced_attempt_settles_the_order_and_closes_the_newer_attempt(): void
    {
        ['order' => $order, 'payment' => $old] = $this->createPendingOrder('qris');

        $old->forceFill([
            'status' => Payment::STATUS_CANCELLED,
            'gateway_status' => 'cancel',
        ])->save();

        $new = $this->createPayment($order, 'bca_va', 2);

        Http::fake([
            'api.sandbox.midtrans.com/*' => Http::response($this->midtransCancelBody($new)),
        ]);

        $this->postJson($this->webhookUri(), $this->webhookPayload($old, 'settlement'))->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_PAID, $old->fresh()->status);
        $this->assertSame(Payment::STATUS_CANCELLED, $new->fresh()->status);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/v2/' . $new->gateway_order_id . '/cancel')
            && $request->body() === '');
    }

    public function test_a_late_payment_does_not_reactivate_a_cancelled_order(): void
    {
        Log::spy();

        ['order' => $order, 'payment' => $payment, 'variant' => $variant] = $this->createPendingOrder();

        $order->update(['order_status' => 'cancelled']);

        $this->assertSame(10, $variant->fresh()->stock);

        $this->postJson($this->webhookUri(), $this->webhookPayload($payment, 'settlement'))->assertOk();

        $order->refresh();

        $this->assertSame('cancelled', $order->order_status);
        $this->assertNotSame('paid', $order->payment_status);
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(10, $variant->fresh()->stock);

        Log::shouldHaveReceived('critical')->once();
    }

    private function webhookUri(): string
    {
        foreach (app('router')->getRoutes() as $route) {
            if ($route->getControllerClass() === MidtransWebhookController::class) {
                return '/' . ltrim($route->uri(), '/');
            }
        }

        $this->fail('Rute webhook Midtrans tidak ditemukan.');
    }

    private function webhookPayload(Payment $payment, string $status, array $overrides = []): array
    {
        $payload = array_merge([
            'order_id' => $payment->gateway_order_id,
            'status_code' => '200',
            'gross_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'transaction_status' => $status,
            'fraud_status' => 'accept',
            'transaction_id' => $payment->transaction_id,
            'payment_type' => $payment->payment_type,
        ], $overrides);

        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id']
            . $payload['status_code']
            . $payload['gross_amount']
            . config('services.midtrans.server_key')
        );

        return $payload;
    }
}