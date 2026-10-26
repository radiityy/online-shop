<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\Payment;
use App\Models\RefundAccount;
use App\Models\User;
use App\Services\PaymentSettlementService;
use App\Services\RefundAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMidtransOrders;
use Tests\TestCase;

class RefundAccountTest extends TestCase
{
    use BuildsMidtransOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureMidtrans();
    }

    public function test_the_owner_can_add_a_refund_account_and_it_is_stored_encrypted(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment] = $this->vaRefund('Budi Santoso');

        $this->actingAs($user)
            ->post($this->url($order, $payment), $this->body())
            ->assertRedirect(route('orders.show', $order->order_code))
            ->assertSessionHas('success');

        $account = RefundAccount::query()->where('payment_id', $payment->id)->firstOrFail();

        $this->assertSame('1234567890', $account->account_number);
        $this->assertSame('7890', $account->account_last4);
        $this->assertSame('bca', $account->bank_code);
        $this->assertNotNull($account->submitted_at);
        $this->assertNotNull($account->submitted_ip);

        $raw = (string) DB::table('refund_accounts')->where('id', $account->id)->value('account_number');

        $this->assertNotSame('1234567890', $raw);
        $this->assertStringNotContainsString('1234567890', $raw);

        $this->assertSame(1, $this->notificationCount($order, 'refund_account_received'));
    }

    public function test_the_order_page_never_receives_the_full_account_number(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment] = $this->vaRefund('Budi Santoso');

        $this->actingAs($user)->post($this->url($order, $payment), $this->body());

        $response = $this->actingAs($user)->get('/orders/'.$order->order_code);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Store/Orders/Show')
            ->where('refundAccounts.0.state', 'submitted')
            ->where('refundAccounts.0.masked', '•••• 7890')
            ->where('refundAccounts.0.bank_label', 'BCA')
            ->where('refundAccounts.0.can_edit', true));

        $this->assertStringNotContainsString('1234567890', $response->getContent());
    }

    public function test_the_page_asks_for_an_account_before_one_is_sent(): void
    {
        ['user' => $user, 'order' => $order] = $this->vaRefund('Budi Santoso');

        $this->actingAs($user)
            ->get('/orders/'.$order->order_code)
            ->assertInertia(fn (Assert $page) => $page
                ->where('refundAccounts.0.state', 'needed')
                ->where('refundAccounts.0.masked', null));
    }

    public function test_another_customer_cannot_add_an_account_to_someone_elses_order(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->vaRefund('Budi Santoso');

        $other = User::factory()->create(['role' => 'customer']);

        $this->actingAs($other)
            ->post($this->url($order, $payment), $this->body())
            ->assertNotFound();

        $this->assertSame(0, RefundAccount::query()->count());
    }

    public function test_a_guest_cannot_add_an_account(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->vaRefund('Budi Santoso');

        $this->post($this->url($order, $payment), $this->body())->assertRedirect();

        $this->assertSame(0, RefundAccount::query()->count());
    }

    public function test_a_payment_of_another_order_cannot_be_used(): void
    {
        ['user' => $user, 'order' => $order] = $this->vaRefund('Budi Santoso');
        ['payment' => $foreign] = $this->createPendingOrder('bca_va');

        $foreign->forceFill(['status' => Payment::STATUS_REFUND_REQUIRED])->save();

        $this->actingAs($user)
            ->post($this->url($order, $foreign), $this->body())
            ->assertNotFound();

        $this->assertSame(0, RefundAccount::query()->count());
    }

    public function test_payments_that_refund_automatically_do_not_take_an_account(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment] = $this->createPendingOrder('qris');

        $this->markRefundRequired($order, $payment);

        $this->actingAs($user)
            ->post($this->url($order, $payment), $this->body())
            ->assertRedirect(route('orders.show', $order->order_code))
            ->assertSessionHas('error');

        $this->assertSame(0, RefundAccount::query()->count());

        $this->actingAs($user)
            ->get('/orders/'.$order->order_code)
            ->assertInertia(fn (Assert $page) => $page->where('refundAccounts', []));
    }

    public function test_an_account_number_must_fit_the_bank_and_contain_only_digits(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment] = $this->vaRefund('Budi Santoso');

        $this->actingAs($user)
            ->post($this->url($order, $payment), $this->body(['account_number' => '12345']))
            ->assertSessionHasErrors('account_number');

        $this->actingAs($user)
            ->post($this->url($order, $payment), $this->body(['account_number' => '12345abc90']))
            ->assertSessionHasErrors('account_number');

        $this->actingAs($user)
            ->post($this->url($order, $payment), $this->body(['bank_code' => 'not-a-bank']))
            ->assertSessionHasErrors('bank_code');

        $this->actingAs($user)
            ->post($this->url($order, $payment), $this->body(['confirmed' => false]))
            ->assertSessionHasErrors('confirmed');

        $this->assertSame(0, RefundAccount::query()->count());
    }

    public function test_spaces_and_dashes_in_the_number_are_ignored(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment] = $this->vaRefund('Budi Santoso');

        $this->actingAs($user)
            ->post($this->url($order, $payment), $this->body(['account_number' => '1234 567-890']))
            ->assertSessionHasNoErrors();

        $this->assertSame('1234567890', RefundAccount::query()->firstOrFail()->account_number);
    }

    public function test_a_holder_name_that_differs_from_the_customer_is_flagged_not_rejected(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment] = $this->vaRefund('Budi Santoso');

        $this->actingAs($user)
            ->post($this->url($order, $payment), $this->body(['account_holder' => 'Andi Wijaya']))
            ->assertSessionHasNoErrors();

        $account = RefundAccount::query()->firstOrFail();

        $this->assertTrue($account->needs_review);
        $this->assertFalse($account->name_matches);
    }

    public function test_a_matching_holder_name_is_not_flagged(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment] = $this->vaRefund('Budi Santoso');

        $this->actingAs($user)
            ->post($this->url($order, $payment), $this->body(['account_holder' => 'BUDI SANTOSO']))
            ->assertSessionHasNoErrors();

        $this->assertFalse(RefundAccount::query()->firstOrFail()->needs_review);
    }

    public function test_name_matching_is_forgiving_about_order_titles_and_typos(): void
    {
        $service = app(RefundAccountService::class);

        $this->assertTrue($service->nameMatches('Budi', ['Budi Santoso']));
        $this->assertTrue($service->nameMatches('Santoso Budi', ['Budi Santoso']));
        $this->assertTrue($service->nameMatches('Budy Santoso', ['Budi Santoso']));
        $this->assertTrue($service->nameMatches('Siti Aminah', ['Budi Santoso', 'Siti Aminah']));
        $this->assertFalse($service->nameMatches('Andi Wijaya', ['Budi Santoso']));
        $this->assertFalse($service->nameMatches('', ['Budi Santoso']));
    }

    public function test_the_account_can_be_changed_until_the_admin_locks_it(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment] = $this->vaRefund('Budi Santoso');

        $this->actingAs($user)->post($this->url($order, $payment), $this->body());

        $this->actingAs($user)
            ->post($this->url($order, $payment), $this->body([
                'bank_code' => 'bni',
                'account_number' => '9876543210',
                'account_holder' => 'Andi Wijaya',
            ]))
            ->assertSessionHas('success');

        $account = RefundAccount::query()->firstOrFail();

        $this->assertSame(1, RefundAccount::query()->count());
        $this->assertSame('bni', $account->bank_code);
        $this->assertSame('9876543210', $account->account_number);
        $this->assertTrue($account->needs_review);

        // Changing it again does not send a second "received" notification.
        $this->assertSame(1, $this->notificationCount($order, 'refund_account_received'));

        $this->actingAs($user)
            ->post($this->url($order, $payment), $this->body())
            ->assertSessionHas('success');

        $this->assertFalse($account->fresh()->needs_review);
    }

    public function test_a_locked_account_cannot_be_changed(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment] = $this->vaRefund('Budi Santoso');

        $this->actingAs($user)->post($this->url($order, $payment), $this->body());

        app(RefundAccountService::class)->lock(RefundAccount::query()->firstOrFail());

        $this->actingAs($user)
            ->post($this->url($order, $payment), $this->body(['account_number' => '9999999999']))
            ->assertSessionHas('error');

        $this->assertSame('1234567890', RefundAccount::query()->firstOrFail()->account_number);

        $this->actingAs($user)
            ->get('/orders/'.$order->order_code)
            ->assertInertia(fn (Assert $page) => $page
                ->where('refundAccounts.0.state', 'locked')
                ->where('refundAccounts.0.can_edit', false));
    }

    public function test_the_route_is_throttled(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment] = $this->vaRefund('Budi Santoso');

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->post($this->url($order, $payment), $this->body(['account_number' => '1']));
        }

        $this->actingAs($user)
            ->post($this->url($order, $payment), $this->body())
            ->assertStatus(429);
    }

    public function test_marking_the_refund_done_stores_the_reference_and_erases_the_account(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment] = $this->vaRefund('Budi Santoso');

        $this->actingAs($user)->post($this->url($order, $payment), $this->body());

        $this->assertTrue(app(PaymentSettlementService::class)->markRefundedManually($order, 'TRF-20261026-001', 'Sent from BCA'));

        $payment->refresh();

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->status);
        $this->assertSame('TRF-20261026-001', $payment->refund_reference);
        $this->assertSame('Sent from BCA', $payment->refund_note);
        $this->assertNotNull($payment->refunded_at);

        $row = DB::table('refund_accounts')->where('payment_id', $payment->id)->first();

        $this->assertNull($row->account_number);
        $this->assertNull($row->account_holder);
        $this->assertNull($row->submitted_ip);
        $this->assertNotNull($row->cleared_at);
        $this->assertSame('7890', $row->account_last4);
    }

    public function test_the_refund_notification_asks_a_virtual_account_customer_for_an_account(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder('bca_va');

        $order->update(['order_status' => 'cancelled']);

        $this->settle($payment);

        $message = $this->latestOf($order, 'refund_required')->message;

        $this->assertStringContainsString('bank account', $message);
        $this->assertStringContainsString('after the order was closed', $message);
    }

    public function test_the_refund_notification_does_not_ask_a_qris_customer_for_an_account(): void
    {
        ['order' => $order, 'payment' => $payment] = $this->createPendingOrder('qris');

        $order->update(['order_status' => 'cancelled']);

        $this->settle($payment);

        $this->assertStringNotContainsString('bank account', $this->latestOf($order, 'refund_required')->message);
    }

    public function test_customers_who_have_not_sent_an_account_are_reminded_a_few_days_later(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment] = $this->vaRefund('Budi Santoso');

        $payment->forceFill(['paid_at' => now()->subDays(1)])->save();

        Artisan::call('refunds:remind-accounts');
        $this->assertSame(0, $this->notificationCount($order, 'refund_account_reminder'));

        $payment->forceFill(['paid_at' => now()->subDays(4)])->save();

        Artisan::call('refunds:remind-accounts');
        $this->assertSame(1, $this->notificationCount($order, 'refund_account_reminder'));
        $this->assertSame($user->id, $this->latestOf($order, 'refund_account_reminder')->user_id);

        // Not again on the same day.
        Artisan::call('refunds:remind-accounts');
        $this->assertSame(1, $this->notificationCount($order, 'refund_account_reminder'));

        // Again after another interval, up to the maximum.
        foreach ([4, 8, 12, 16] as $days) {
            Carbon::setTestNow(now()->addDays(4));
            Artisan::call('refunds:remind-accounts');
        }

        Carbon::setTestNow();

        $this->assertSame(3, $this->notificationCount($order, 'refund_account_reminder'));
    }

    public function test_nobody_is_reminded_once_an_account_exists_or_for_qris(): void
    {
        ['user' => $user, 'order' => $order, 'payment' => $payment] = $this->vaRefund('Budi Santoso');

        $payment->forceFill(['paid_at' => now()->subDays(10)])->save();

        $this->actingAs($user)->post($this->url($order, $payment), $this->body());

        ['order' => $qrisOrder, 'payment' => $qris] = $this->createPendingOrder('qris');
        $this->markRefundRequired($qrisOrder, $qris);
        $qris->forceFill(['paid_at' => now()->subDays(10)])->save();

        Artisan::call('refunds:remind-accounts');

        $this->assertSame(0, OrderNotification::query()->where('type', 'refund_account_reminder')->count());
    }

    private function vaRefund(string $customerName): array
    {
        $data = $this->createPendingOrder('bca_va');

        $data['user']->forceFill(['name' => $customerName])->save();

        $this->markRefundRequired($data['order'], $data['payment']);

        return $data;
    }

    private function markRefundRequired(Order $order, Payment $payment): void
    {
        $order->forceFill(['payment_status' => 'refund_required', 'order_status' => 'cancelled'])->saveQuietly();
        $payment->forceFill(['status' => Payment::STATUS_REFUND_REQUIRED, 'paid_at' => now()])->save();
    }

    private function url(Order $order, Payment $payment): string
    {
        return '/orders/'.$order->order_code.'/refund-accounts/'.$payment->id;
    }

    private function body(array $overrides = []): array
    {
        return array_merge([
            'bank_code' => 'bca',
            'account_number' => '1234567890',
            'account_holder' => 'Budi Santoso',
            'confirmed' => true,
        ], $overrides);
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

    private function notificationCount(Order $order, string $type): int
    {
        return OrderNotification::query()
            ->where('order_id', $order->id)
            ->where('type', $type)
            ->count();
    }

    private function latestOf(Order $order, string $type): OrderNotification
    {
        return OrderNotification::query()
            ->where('order_id', $order->id)
            ->where('type', $type)
            ->latest('id')
            ->firstOrFail();
    }
}