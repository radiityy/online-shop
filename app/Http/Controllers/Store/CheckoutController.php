<?php

namespace App\Http\Controllers\Store;

use App\Exceptions\ShippingException;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Services\MidtransService;
use App\Services\ShippingQuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly ShippingQuoteService $quotes,
        private readonly MidtransService $midtrans,
    ) {
    }

    public function index(): Response|RedirectResponse
    {
        $cart = Cart::query()
            ->where('user_id', Auth::id())
            ->with([
                'items.product.primaryImage',
                'items.variant',
            ])
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index')
                ->with('error', 'Bag masih kosong.');
        }

        foreach ($cart->items as $item) {
            if (! $item->product?->is_active || ! $item->variant?->is_active) {
                return redirect()->route('cart.index')
                    ->with('error', 'Ada produk yang sudah tidak aktif. Silakan periksa bag kamu.');
            }

            if ($item->quantity > $item->variant->stock) {
                return redirect()->route('cart.index')
                    ->with('error', 'Ada produk dengan jumlah melebihi stok tersedia. Silakan update bag kamu.');
            }
        }

        $items = $cart->items->map(function ($item) {
            $unitPrice = (float) $item->product->final_price + (float) $item->variant->additional_price;
            $subtotal = $unitPrice * $item->quantity;

            return [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'product' => [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'slug' => $item->product->slug,
                    'price' => $item->product->price,
                    'sale_price' => $item->product->sale_price,
                    'final_price' => $item->product->final_price,
                    'is_on_sale' => $item->product->is_on_sale,
                    'image_path' => $item->product->primaryImage?->image_path,
                ],
                'variant' => [
                    'id' => $item->variant->id,
                    'size' => $item->variant->size,
                    'color' => $item->variant->color,
                    'sku' => $item->variant->sku,
                    'stock' => $item->variant->stock,
                    'additional_price' => $item->variant->additional_price,
                ],
            ];
        })->values();

        $subtotal = $items->sum('subtotal');

        $totalWeight = (int) $cart->items->sum(
            fn ($item) => (int) ($item->product?->weight ?? 0) * (int) $item->quantity
        );

        $addresses = Address::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('is_default')
            ->latest()
            ->get()
            ->map(function (Address $address) {
                return [
                    'id' => $address->id,
                    'recipient_name' => $address->recipient_name,
                    'phone' => $address->phone,
                    'province' => $address->province,
                    'city' => $address->city,
                    'district' => $address->district,
                    'area_id' => $address->area_id,
                    'area_name' => $address->area_name,
                    'postal_code' => $address->postal_code,
                    'full_address' => $address->full_address,
                    'is_default' => $address->is_default,
                ];
            })
            ->values();

        $defaultAddress = $addresses->firstWhere('is_default', true) ?? $addresses->first();

        return Inertia::render('Store/Checkout/Index', [
            'items' => $items,
            'summary' => [
                'subtotal' => $subtotal,
                'total_items' => $items->sum('quantity'),
                'total_weight' => $totalWeight,
            ],
            'addresses' => $addresses,
            'defaultAddress' => $defaultAddress,
            'paymentMethods' => $this->paymentMethods(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = (int) Auth::id();

        $validated = $request->validate([
            'address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')->where('user_id', $userId),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
            'shipping_courier_code' => ['required', 'string', 'max:32'],
            'shipping_service_code' => ['required', 'string', 'max:32'],
            'payment_method' => ['required', 'string', Rule::in(array_keys($this->midtrans->methods()))],
            'agree_terms' => ['accepted'],
        ], [
            'address_id.required' => 'Pilih alamat pengiriman untuk melanjutkan pesanan.',
            'address_id.exists' => 'Alamat tidak ditemukan. Silakan pilih ulang alamat.',
            'payment_method.required' => 'Pilih metode pembayaran untuk melanjutkan pesanan.',
            'payment_method.in' => 'Metode pembayaran tidak tersedia. Silakan pilih ulang.',
            'agree_terms.accepted' => 'Setujui Syarat & Ketentuan untuk melanjutkan pesanan.',
        ]);

        $methodKey = $validated['payment_method'];

        $destination = $this->resolveDestination((int) $validated['address_id'], $userId);

        try {
            $cart = Cart::query()
                ->where('user_id', $userId)
                ->with('items')
                ->first();

            if (! $cart || $cart->items->isEmpty()) {
                return back()->with('error', 'Bag masih kosong.');
            }

            $quotes = $this->quotes->forCart($cart, $destination['area_id']);
        } catch (ShippingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $option = collect($quotes)->first(
            fn (array $quote) => $quote['courier_code'] === strtolower($validated['shipping_courier_code'])
                && $quote['service_code'] === $validated['shipping_service_code']
        );

        if ($option === null) {
            throw ValidationException::withMessages([
                'shipping_option' => 'Pilihan pengiriman tidak tersedia lagi. Silakan pilih ulang.',
            ]);
        }

        $signature = $this->cartSignature($cart->items);

        $result = DB::transaction(function () use ($validated, $userId, $destination, $option, $signature, $methodKey) {
            $cart = Cart::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $cart) {
                return [
                    'error' => 'Bag masih kosong.',
                ];
            }

            $cart->load([
                'items.product',
                'items.variant',
            ]);

            if ($cart->items->isEmpty()) {
                return [
                    'error' => 'Bag masih kosong.',
                ];
            }

            if ($this->cartSignature($cart->items) !== $signature) {
                return [
                    'error' => 'Isi bag berubah. Silakan pilih ulang pengiriman.',
                ];
            }

            $variantIds = $cart->items
                ->pluck('product_variant_id')
                ->unique()
                ->values();

            $lockedVariants = ProductVariant::query()
                ->with('product')
                ->whereIn('id', $variantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $requiredByVariant = $cart->items
                ->groupBy('product_variant_id')
                ->map(fn ($items) => (int) $items->sum('quantity'));

            foreach ($requiredByVariant as $variantId => $requiredQuantity) {
                $variant = $lockedVariants->get($variantId);

                if (! $variant || ! $variant->product) {
                    return [
                        'error' => 'Ada produk yang tidak ditemukan. Silakan periksa bag kamu.',
                    ];
                }

                if (! $variant->product->is_active || ! $variant->is_active) {
                    return [
                        'error' => 'Ada produk yang sudah tidak aktif. Silakan periksa bag kamu.',
                    ];
                }

                if ($requiredQuantity > $variant->stock) {
                    return [
                        'error' => 'Stok ' . $variant->product->name . ' tidak cukup. Silakan update bag kamu.',
                    ];
                }
            }

            $subtotal = $cart->items->sum(function ($item) use ($lockedVariants) {
                $variant = $lockedVariants->get($item->product_variant_id);
                $unitPrice = (float) $variant->product->final_price + (float) $variant->additional_price;

                return $unitPrice * $item->quantity;
            });

            $shippingCost = (int) $option['shipping_cost'];
            $insuranceFee = (int) $option['insurance_fee'];
            $totalBeforeFee = $subtotal + $shippingCost + $insuranceFee;
            $paymentFee = $this->midtrans->feeFor($methodKey, (int) round($totalBeforeFee));
            $total = $totalBeforeFee + $paymentFee;

            $order = Order::query()->create([
                'user_id' => $userId,
                'address_id' => $destination['address_id'],
                'order_code' => $this->generateOrderCode(),
                'recipient_name' => $destination['recipient_name'],
                'phone' => $destination['phone'],
                'province' => $destination['province'],
                'city' => $destination['city'],
                'district' => $destination['district'],
                'area_id' => $destination['area_id'],
                'postal_code' => $destination['postal_code'],
                'full_address' => $destination['full_address'],
                'notes' => $validated['notes'] ?? null,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'shipping_insurance_fee' => $insuranceFee,
                'payment_fee' => $paymentFee,
                'shipping_courier_code' => $option['courier_code'],
                'shipping_courier_name' => $option['courier_name'],
                'shipping_service_code' => $option['service_code'],
                'shipping_service_name' => $option['service_name'],
                'shipping_duration' => $option['duration'] !== '' ? $option['duration'] : null,
                'total' => $total,
                'payment_expires_at' => now()->addMinutes((int) config('services.midtrans.expiry_minutes', 60)),
                'payment_status' => 'pending',
                'order_status' => 'pending',
                'shipping_status' => 'not_shipped',
                'stock_released' => false,
            ]);

            foreach ($cart->items as $item) {
                $variant = $lockedVariants->get($item->product_variant_id);
                $product = $variant->product;
                $unitPrice = (float) $product->final_price + (float) $variant->additional_price;

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant->size ?? $variant->sku ?? 'Default',
                    'sku' => $variant->sku,
                    'price' => $unitPrice,
                    'quantity' => $item->quantity,
                    'subtotal' => $unitPrice * $item->quantity,
                ]);

                $variant->decrement('stock', $item->quantity);
            }

            Shipment::query()->create([
                'order_id' => $order->id,
                'courier' => $option['courier_name'],
                'courier_code' => $option['courier_code'],
                'service' => $option['service_name'],
                'tracking_number' => null,
                'status' => 'not_shipped',
            ]);

            $cart->items()->delete();

            return [
                'order' => $order,
                'total_before_fee' => $totalBeforeFee,
            ];
        }, 3);

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        $order = $result['order'];

        try {
            $this->createGatewayPayment($order, $methodKey, $request->user()?->email);
        } catch (RuntimeException $exception) {
            $order->update([
                'payment_fee' => 0,
                'total' => $result['total_before_fee'],
            ]);

            return redirect()->route('orders.show', $order->order_code)
                ->with('error', 'Pesanan sudah dibuat, tetapi pembayaran belum bisa disiapkan. ' . $exception->getMessage() . ' Silakan pilih ulang metode pembayaran.');
        }

        return redirect()->route('orders.show', $order->order_code)
            ->with('success', 'Order berhasil dibuat. Selesaikan pembayaran sebelum waktu habis.');
    }

    private function createGatewayPayment(Order $order, string $methodKey, ?string $email): void
    {
        $method = $this->midtrans->method($methodKey);

        if (! $method) {
            throw new RuntimeException('Metode pembayaran tidak tersedia.');
        }

        $gatewayOrderId = $order->order_code . '-1';
        $amount = (int) round((float) $order->total);
        $fee = (int) round((float) $order->payment_fee);
        $minutes = max(1, (int) floor(now()->diffInMinutes($order->payment_expires_at, false)));

        $charge = $this->midtrans->charge(
            $methodKey,
            $gatewayOrderId,
            $amount,
            [
                'name' => $order->recipient_name,
                'email' => $email,
                'phone' => $order->phone,
            ],
            route('orders.show', $order->order_code),
            $minutes,
        );

        Payment::query()->create([
            'order_id' => $order->id,
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
            'expires_at' => $order->payment_expires_at,
            'raw_response' => $charge['raw'],
        ]);
    }

    private function paymentMethods(): array
    {
        return collect($this->midtrans->methods())
            ->map(function (array $method, string $key) {
                return [
                    'key' => $key,
                    'label' => $method['label'],
                    'payment_type' => $method['payment_type'],
                    'bank' => $method['bank'],
                    'group' => in_array($method['payment_type'], ['bank_transfer', 'permata'], true)
                        ? 'virtual_account'
                        : 'other',
                    'fee_flat' => (int) $method['fee_flat'],
                    'fee_percent' => (float) $method['fee_percent'],
                ];
            })
            ->values()
            ->all();
    }

    private function resolveDestination(int $addressId, int $userId): array
    {
        $address = Address::query()
            ->where('user_id', $userId)
            ->findOrFail($addressId);

        if (! $address->area_id) {
            throw ValidationException::withMessages([
                'address_id' => 'Alamat ini belum memiliki area pengiriman. Perbarui alamat dan pilih areanya.',
            ]);
        }

        return [
            'address_id' => $address->id,
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'province' => $address->province,
            'city' => $address->city,
            'district' => $address->district,
            'area_id' => $address->area_id,
            'area_name' => $address->area_name,
            'postal_code' => $address->postal_code,
            'full_address' => $address->full_address,
        ];
    }

    private function cartSignature(iterable $items): string
    {
        return sha1(
            collect($items)
                ->groupBy('product_variant_id')
                ->map(fn ($group, $variantId) => $variantId . ':' . (int) $group->sum('quantity'))
                ->sortKeys()
                ->implode('|')
        );
    }

    private function generateOrderCode(): string
    {
        do {
            $code = 'NE-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::query()->where('order_code', $code)->exists());

        return $code;
    }
}