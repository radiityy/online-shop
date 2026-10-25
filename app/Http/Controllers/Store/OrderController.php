<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Services\MidtransService;
use App\Services\OrderNotificationService;
use App\Services\PaymentSettlementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class OrderController extends Controller
{
    private const STATUS_LABELS = [
        'unpaid' => 'Unpaid',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'refund' => 'Refund',
        'returned' => 'Returned',
    ];

    public function __construct(
        private readonly MidtransService $midtrans,
        private readonly PaymentSettlementService $settlement,
        private readonly OrderNotificationService $notifications,
    ) {
    }

    public function index(Request $request): Response
    {
        $status = $this->resolveStatus($request->query('status'));

        $orders = Order::query()
            ->with([
                'items' => fn ($items) => $items
                    ->orderBy('id')
                    ->with('product.primaryImage'),
            ])
            ->withCount('items')
            ->where('user_id', Auth::id())
            ->when($status !== null, fn (Builder $query) => $this->applyStatusFilter($query, $status))
            ->latest()
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Order $order) => $this->present($order));

        return Inertia::render('Store/Orders/Index', [
            'orders' => $orders,
            'status' => $status ?? 'all',
            'statusOptions' => $this->statusOptions(),
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
                return ['error' => 'This order has already been paid, so it can no longer be cancelled. See our FAQ for returns and refunds.'];
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

        if (isset($result['cancelled'])) {
            try {
                $this->notifications->notifyOrderCancelled($order);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $redirect = redirect()->route('orders.show', $orderCode);

        if (isset($result['error'])) {
            return $redirect->with('error', $result['error']);
        }

        if (isset($result['info'])) {
            return $redirect->with('success', $result['info']);
        }

        return $redirect->with('success', 'Your order has been cancelled.');
    }

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

    private function present(Order $order): array
    {
        $item = $order->items->first();
        $status = $this->displayStatus($order);

        return [
            'id' => $order->id,
            'order_code' => $order->order_code,
            'created_at' => $order->created_at?->toIso8601String(),
            'total' => (float) $order->total,
            'items_count' => (int) $order->items_count,
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status],
            'product' => $item ? [
                'name' => $item->product_name,
                'size' => $item->variant_name,
                'price' => (float) $item->price,
                'quantity' => (int) $item->quantity,
                'image' => $this->imageUrl($item),
            ] : null,
        ];
    }

    private function displayStatus(Order $order): string
    {
        if (in_array($order->payment_status, ['refund_required', 'refunded'], true)) {
            return 'refund';
        }

        if ($order->order_status === 'cancelled' || in_array($order->payment_status, ['failed', 'expired'], true)) {
            return 'cancelled';
        }

        if ($order->shipping_status === 'returned') {
            return 'returned';
        }

        if (in_array($order->order_status, ['delivered', 'completed'], true) || $order->shipping_status === 'delivered') {
            return 'completed';
        }

        if ($order->order_status === 'shipped' || $order->shipping_status === 'shipped') {
            return 'shipped';
        }

        if (in_array($order->payment_status, ['pending', 'waiting_confirmation'], true)) {
            return 'unpaid';
        }

        return 'processing';
    }

    private function statusRules(): array
    {
        return [
            'refund' => fn (Builder $query) => $query
                ->whereIn('payment_status', ['refund_required', 'refunded']),

            'cancelled' => fn (Builder $query) => $query
                ->where(fn (Builder $group) => $group
                    ->where('order_status', 'cancelled')
                    ->orWhereIn('payment_status', ['failed', 'expired'])),

            'returned' => fn (Builder $query) => $query
                ->where('shipping_status', 'returned'),

            'completed' => fn (Builder $query) => $query
                ->where(fn (Builder $group) => $group
                    ->whereIn('order_status', ['delivered', 'completed'])
                    ->orWhere('shipping_status', 'delivered')),

            'shipped' => fn (Builder $query) => $query
                ->where(fn (Builder $group) => $group
                    ->where('order_status', 'shipped')
                    ->orWhere('shipping_status', 'shipped')),

            'unpaid' => fn (Builder $query) => $query
                ->whereIn('payment_status', ['pending', 'waiting_confirmation']),

            'processing' => fn (Builder $query) => $query
                ->whereNotIn('payment_status', ['pending', 'waiting_confirmation']),
        ];
    }

    private function applyStatusFilter(Builder $query, string $status): void
    {
        foreach ($this->statusRules() as $key => $rule) {
            if ($key === $status) {
                $query->where(fn (Builder $group) => $rule($group));

                return;
            }

            $query->whereNot(fn (Builder $group) => $rule($group));
        }
    }

    private function resolveStatus(mixed $value): ?string
    {
        return is_string($value) && array_key_exists($value, self::STATUS_LABELS) ? $value : null;
    }

    private function statusOptions(): array
    {
        $options = [['value' => 'all', 'label' => 'All status']];

        foreach (self::STATUS_LABELS as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    private function imageUrl(OrderItem $item): string
    {
        $path = $item->product?->primaryImage?->image_path;

        return $path ? '/storage/'.$path : '/placeholder-product.jpg';
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