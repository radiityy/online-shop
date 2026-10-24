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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly MidtransService $midtrans,
        private readonly PaymentSettlementService $settlement,
    ) {
    }

    public function pay(Request $request, string $orderCode): RedirectResponse
    {
        $validated = $request->validate([
            'method' => ['required', 'string', Rule::in(array_keys($this->midtrans->methods()))],
        ], [
            'method.required' => 'Pilih metode pembayaran.',
            'method.in' => 'Metode pembayaran tidak tersedia.',
        ]);

        $methodKey = $validated['method'];

        $order = Order::query()
            ->where('order_code', $orderCode)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($order->payment_expires_at === null) {
            return back()->with('error', 'Pesanan ini memakai transfer manual.');
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
                        return ['info' => 'Metode pembayaran ini sudah dipilih.'];
                    }

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
                        return ['info' => 'Pembayaran sudah kami terima. Terima kasih!'];
                    }

                    if ($error = $this->payableError($locked)) {
                        return ['error' => $error];
                    }
                }

                $total = (int) round((float) $locked->total);
                $fee = $this->midtrans->feeFor($methodKey, $total);
                $amount = $total + $fee;
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

        return back()->with('success', 'Metode pembayaran dipilih. Selesaikan pembayaran sebelum waktu habis.');
    }

    public function refresh(string $orderCode): RedirectResponse
    {
        $order = Order::query()
            ->where('order_code', $orderCode)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $payment = $order->payments()->first();

        if (! $payment || ! $payment->gateway) {
            return back()->with('error', 'Belum ada transaksi pembayaran untuk pesanan ini.');
        }

        try {
            $this->settlement->apply($payment, $this->midtrans->status($payment->gateway_order_id));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Status pembayaran diperbarui.');
    }

    public function uploadProof(Request $request, string $orderCode): RedirectResponse
    {
        $validated = $request->validate([
            'proof_image' => [
                'required',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(2048),
                'dimensions:max_width=6000,max_height=6000',
            ],
        ]);

        $order = Order::query()
            ->with('payment')
            ->where('order_code', $orderCode)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (! $order->payment || $order->payment->gateway || $order->payment_expires_at !== null) {
            return back()->withErrors([
                'proof_image' => 'Payment proof upload is not available for this order.',
            ]);
        }

        if (in_array($order->payment_status, ['paid', 'failed', 'expired', 'refunded'], true)) {
            return back()->withErrors([
                'proof_image' => 'Payment proof upload is no longer available for this order.',
            ]);
        }

        if ($order->order_status === 'cancelled') {
            return back()->withErrors([
                'proof_image' => 'This order has been cancelled and cannot receive payment proof.',
            ]);
        }

        if ($order->shipping_status === 'returned') {
            return back()->withErrors([
                'proof_image' => 'This order has been returned and cannot receive payment proof.',
            ]);
        }

        $oldProofImage = $order->payment->proof_image;
        $newProofImage = $validated['proof_image']->store('payment-proofs', 'public');

        try {
            $order->payment->update([
                'proof_image' => $newProofImage,
                'status' => 'waiting_confirmation',
            ]);

            $order->update([
                'payment_status' => 'waiting_confirmation',
            ]);

            if ($oldProofImage) {
                Storage::disk('public')->delete($oldProofImage);
            }
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newProofImage);

            throw $exception;
        }

        return back()->with('success', 'Payment proof uploaded. We are checking your payment now.');
    }

    private function payableError(Order $order): ?string
    {
        if ($order->payment_status === 'paid') {
            return 'Pesanan ini sudah dibayar.';
        }

        if (
            $order->payment_status !== 'pending'
            || $order->order_status === 'cancelled'
            || $order->shipping_status === 'returned'
            || $order->stock_released
        ) {
            return 'Pesanan ini tidak lagi dapat dibayar.';
        }

        if (! $order->payment_expires_at || $order->payment_expires_at->isPast()) {
            return 'Waktu pembayaran sudah habis. Silakan buat pesanan baru.';
        }

        if ($order->payment_expires_at->lte(now()->addMinutes(2))) {
            return 'Waktu pembayaran hampir habis, jadi metode baru tidak bisa dibuat. Selesaikan pembayaran yang sudah dipilih.';
        }

        return null;
    }
}