<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\MidtransService;
use App\Services\PaymentSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly MidtransService $midtrans,
        private readonly PaymentSettlementService $settlement,
    ) {
    }

    /**
     * Pilih atau ganti metode bayar.
     *
     * Dasar hitung biaya adalah (total - payment_fee) supaya biaya tidak
     * terhitung dua kali. Waktu bayar TIDAK diperpanjang: transaksi baru
     * memakai sisa waktu dari payment_expires_at.
     */
    public function pay(Request $request, string $orderCode): RedirectResponse
    {
        $validated = $request->validate([
            'method' => ['required', 'string', Rule::in(array_keys($this->midtrans->methods()))],
        ], [
            'method.required' => 'Please choose a payment method.',
            'method.in' => 'That payment method is not available.',
        ]);

        $methodKey = $validated['method'];

        $order = Order::query()
            ->where('order_code', $orderCode)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($order->payment_expires_at === null) {
            return back()->with('error', 'This order cannot be paid online.');
        }

        try {
            $result = DB::transaction(function () use ($order, $methodKey, $request) {
                $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                if ($error = $this->payableError($locked)) {
                    return ['error' => $error];
                }

                $previous = Payment::query()
                    ->where('order_id', $locked->id)
                    ->orderByDesc('id')
                    ->first();

                if ($previous && $previous->gateway && $previous->isPending()) {
                    if ($previous->payment_method === $methodKey) {
                        return ['info' => 'You have already selected this payment method.'];
                    }

                    // Pastikan pembayaran sebelumnya belum masuk sebelum diganti.
                    try {
                        $this->settlement->apply(
                            $previous,
                            $this->midtrans->status($previous->gateway_order_id),
                        );
                    } catch (RuntimeException) {
                    }

                    $locked->refresh();
                    $previous->refresh();

                    if ($locked->payment_status === 'paid') {
                        return ['info' => 'We have already received your payment. Thank you!'];
                    }

                    if ($error = $this->payableError($locked)) {
                        return ['error' => $error];
                    }
                }

                // total sudah memuat biaya metode sebelumnya, jadi dasar hitung = total - payment_fee.
                $total = (int) round((float) $locked->total);
                $currentFee = (int) round((float) $locked->payment_fee);
                $base = max(0, $total - $currentFee);

                $fee = $this->midtrans->feeFor($methodKey, $base);
                $amount = $base + $fee;

                $attempt = Payment::query()->where('order_id', $locked->id)->count() + 1;
                $gatewayOrderId = $locked->order_code.'-'.$attempt;
                $minutes = max(1, (int) floor(now()->diffInMinutes($locked->payment_expires_at, false)));

                $charge = $this->midtrans->charge(
                    $methodKey,
                    $gatewayOrderId,
                    $amount,
                    [
                        'name' => $locked->recipient_name,
                        'email' => $request->user()->email,
                        'phone' => $locked->phone,
                    ],
                    route('orders.show', $locked->order_code),
                    $minutes,
                );

                $method = $this->midtrans->method($methodKey);

                Payment::query()->create([
                    'order_id' => $locked->id,
                    'payment_method' => $methodKey,
                    'gateway' => 'midtrans',
                    'payment_type' => $method['payment_type'],
                    'bank' => $method['bank'],
                    'gateway_order_id' => $gatewayOrderId,
                    'transaction_id' => $charge['transaction_id'],
                    'va_number' => $charge['va_number'],
                    'qr_string' => $charge['qr_string'],
                    'qr_url' => $charge['qr_url'],
                    'deeplink_url' => $charge['deeplink_url'],
                    'amount' => $amount,
                    'fee' => $fee,
                    'status' => Payment::STATUS_PENDING,
                    'gateway_status' => $charge['gateway_status'],
                    'fraud_status' => $charge['fraud_status'],
                    'expires_at' => $locked->payment_expires_at,
                    'raw_response' => $charge['raw'],
                ]);

                // Order mengikuti metode terbaru: fee dan total diperbarui.
                $locked->update([
                    'payment_fee' => $fee,
                    'total' => $amount,
                ]);

                if ($previous && $previous->gateway && $previous->isPending()) {
                    try {
                        $this->midtrans->cancel($previous->gateway_order_id);
                    } catch (RuntimeException) {
                    }

                    $previous->update([
                        'status' => Payment::STATUS_CANCELLED,
                        'gateway_status' => 'cancel',
                    ]);
                }

                return ['created' => true];
            });
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        if (isset($result['info'])) {
            return back()->with('success', $result['info']);
        }

        return back()->with('success', 'Payment method selected. Complete your payment before the timer runs out.');
    }

    /**
     * Cek status ke Midtrans. Dengan silent=1 (polling otomatis dari halaman)
     * tidak ada pesan flash, supaya pelanggan tidak diganggu notifikasi berulang.
     */
    public function refresh(Request $request, string $orderCode): RedirectResponse
    {
        $silent = $request->boolean('silent');

        $order = Order::query()
            ->where('order_code', $orderCode)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $payment = $order->payments()->first();

        if (! $payment || ! $payment->gateway) {
            return $silent ? back() : back()->with('error', 'There is no payment to check yet.');
        }

        if (! $payment->isPending()) {
            return $silent ? back() : back()->with('success', 'Payment status is up to date.');
        }

        try {
            $this->settlement->apply($payment, $this->midtrans->status($payment->gateway_order_id));
        } catch (RuntimeException $exception) {
            return $silent ? back() : back()->with('error', $exception->getMessage());
        }

        return $silent ? back() : back()->with('success', 'Payment status updated.');
    }

    private function payableError(Order $order): ?string
    {
        if ($order->payment_status === 'paid') {
            return 'This order has already been paid.';
        }

        if (
            $order->payment_status !== 'pending'
            || $order->order_status === 'cancelled'
            || $order->shipping_status === 'returned'
            || $order->stock_released
        ) {
            return 'This order can no longer be paid.';
        }

        if (! $order->payment_expires_at || $order->payment_expires_at->isPast()) {
            return 'The payment time has ended. Please place a new order.';
        }

        if ($order->payment_expires_at->lte(now()->addMinutes(2))) {
            return 'There is too little time left to start a new payment. If you already chose a method, please complete that payment.';
        }

        return null;
    }
}