<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Services\MidtransService;
use App\Services\PaymentSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class OrderController extends Controller
{
    public function __construct(
        private readonly MidtransService $midtrans,
        private readonly PaymentSettlementService $settlement,
    ) {
    }

    public function index(): Response
    {
        $orders = Order::query()
            ->with(['payment', 'shipment'])
            ->withCount('items')
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Store/Orders/Index', [
            'orders' => $orders,
        ]);
    }

    public function show(string $orderCode): Response
    {
        $order = Order::query()
            ->with([
                'items.product.primaryImage',
                'payment',
                'shipment',
            ])
            ->where('order_code', $orderCode)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $paymentMethods = [];

        if ($order->payment_expires_at !== null) {
            // total sudah memuat biaya metode yang terpilih, jadi biaya per metode
            // dihitung dari dasar (total - payment_fee) agar tidak terhitung dua kali.
            $base = max(
                0,
                (int) round((float) $order->total) - (int) round((float) $order->payment_fee),
            );

            $paymentMethods = collect($this->midtrans->methods())
                ->map(function (array $method, string $key) use ($base) {
                    $fee = $this->midtrans->feeFor($key, $base);

                    $isVirtualAccount = filled($method['bank'] ?? null)
                        || in_array($method['payment_type'], ['bank_transfer', 'echannel', 'permata'], true);

                    return [
                        'key' => $key,
                        'label' => $method['label'],
                        'payment_type' => $method['payment_type'],
                        'bank' => $method['bank'] ?? null,
                        'group' => $isVirtualAccount ? 'virtual_account' : 'other',
                        'fee_flat' => (int) ($method['fee_flat'] ?? 0),
                        'fee_percent' => (float) ($method['fee_percent'] ?? 0),
                        'fee' => $fee,
                        'amount' => $base + $fee,
                    ];
                })
                ->values()
                ->all();
        }

        return Inertia::render('Store/Orders/Show', [
            'order' => $order,
            'paymentMethods' => $paymentMethods,
            'customerEmail' => Auth::user()?->email,
            'canReorder' => $this->canReorder($order),
            'serverNow' => now()->toIso8601String(),
        ]);
    }

    /**
     * Pelanggan membatalkan order yang belum dibayar.
     *
     * Urutan sengaja hati-hati: cek status ke Midtrans dulu (siapa tahu uang sudah masuk),
     * tutup transaksi di Midtrans, baru batalkan order (stok dilepas oleh hook model Order).
     * Kalau Midtrans gagal ditutup, order TIDAK dibatalkan supaya tidak ada VA/QR yang
     * masih bisa dibayar untuk order yang sudah batal.
     */
    public function cancel(string $orderCode): RedirectResponse
    {
        $order = Order::query()
            ->where('order_code', $orderCode)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $result = DB::transaction(function () use ($order) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->order_status === 'cancelled') {
                return ['info' => 'This order is already cancelled.'];
            }

            if ($locked->payment_status === 'paid') {
                return ['error' => 'This order has already been paid, so it can no longer be cancelled here. Please contact us.'];
            }

            if (
                $locked->payment_status !== 'pending'
                || $locked->stock_released
                || $locked->shipping_status !== 'not_shipped'
            ) {
                return ['error' => 'This order can no longer be cancelled.'];
            }

            $latest = Payment::query()
                ->where('order_id', $locked->id)
                ->orderByDesc('id')
                ->first();

            if ($latest && $latest->gateway && $latest->isPending()) {
                // Pastikan pembayaran belum masuk.
                try {
                    $this->settlement->apply($latest, $this->midtrans->status($latest->gateway_order_id));
                } catch (RuntimeException) {
                }

                $locked->refresh();
                $latest->refresh();

                if ($locked->payment_status === 'paid') {
                    return ['error' => 'Your payment just arrived, so this order cannot be cancelled. We are processing it now.'];
                }

                if ($locked->payment_status !== 'pending') {
                    return ['info' => 'This order has already been closed.'];
                }

                if ($latest->isPending()) {
                    try {
                        $this->midtrans->cancel($latest->gateway_order_id);
                    } catch (RuntimeException) {
                        return ['error' => 'We could not close your payment with the payment provider. Please try again in a moment.'];
                    }

                    $latest->update([
                        'status' => Payment::STATUS_CANCELLED,
                        'gateway_status' => 'cancel',
                    ]);
                }
            }

            $locked->update(['order_status' => 'cancelled']);

            return ['cancelled' => true];
        });

        $redirect = redirect()->route('orders.show', $orderCode);

        if (isset($result['error'])) {
            return $redirect->with('error', $result['error']);
        }

        if (isset($result['info'])) {
            return $redirect->with('success', $result['info']);
        }

        return $redirect->with('success', 'Your order has been cancelled.');
    }

    /**
     * Memasukkan kembali item order yang batal/kedaluwarsa/gagal ke bag.
     *
     * Memakai harga dan stok saat ini (bag selalu menghitung harga langsung dari produk).
     * Item yang sudah ada di bag tidak digandakan: jumlahnya hanya dinaikkan sampai
     * sebanyak yang dulu dipesan, dan tidak pernah melebihi stok.
     */
    public function reorder(string $orderCode): RedirectResponse
    {
        $order = Order::query()
            ->with('items.variant.product')
            ->where('order_code', $orderCode)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (! $this->canReorder($order)) {
            return redirect()->route('orders.show', $orderCode)
                ->with('error', 'This order cannot be re-ordered.');
        }

        $summary = DB::transaction(function () use ($order) {
            $cart = Cart::query()->firstOrCreate(['user_id' => Auth::id()]);

            $cart = Cart::query()
                ->whereKey($cart->id)
                ->lockForUpdate()
                ->firstOrFail();

            $added = 0;
            $skipped = 0;
            $reduced = 0;

            foreach ($order->items as $item) {
                $variantId = $item->variant?->id;

                if (! $variantId) {
                    $skipped++;

                    continue;
                }

                $variant = ProductVariant::query()
                    ->with('product')
                    ->whereKey($variantId)
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $variant
                    || ! $variant->is_active
                    || ! $variant->product?->is_active
                    || $variant->stock < 1
                ) {
                    $skipped++;

                    continue;
                }

                $target = min((int) $item->quantity, (int) $variant->stock);

                $cartItem = CartItem::query()
                    ->where('cart_id', $cart->id)
                    ->where('product_variant_id', $variant->id)
                    ->lockForUpdate()
                    ->first();

                if ($cartItem && (int) $cartItem->quantity >= $target) {
                    $added++;

                    continue;
                }

                if ($target < (int) $item->quantity) {
                    $reduced++;
                }

                if ($cartItem) {
                    $cartItem->update(['quantity' => $target]);
                } else {
                    CartItem::query()->create([
                        'cart_id' => $cart->id,
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'quantity' => $target,
                    ]);
                }

                $added++;
            }

            return compact('added', 'skipped', 'reduced');
        });

        if ($summary['added'] === 0) {
            return redirect()->route('orders.show', $orderCode)
                ->with('error', 'None of these items are available right now.');
        }

        $message = $summary['added'].' '.Str::plural('item', $summary['added']).' added back to your bag.';

        if ($summary['skipped'] > 0) {
            $message .= ' '.$summary['skipped'].' unavailable '.Str::plural('item', $summary['skipped']).' skipped.';
        }

        if ($summary['reduced'] > 0) {
            $message .= ' Some quantities were reduced to match current stock.';
        }

        return redirect()->route('cart.index')->with('success', $message);
    }

    private function canReorder(Order $order): bool
    {
        return $order->payment_status !== 'paid'
            && (
                $order->order_status === 'cancelled'
                || in_array($order->payment_status, ['expired', 'failed', 'refund_required', 'refunded'], true)
            );
    }
}