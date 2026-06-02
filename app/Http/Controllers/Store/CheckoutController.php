<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Shipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
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
            $unitPrice = (float) $item->product->price + (float) $item->variant->additional_price;
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
        $shippingCost = 15000;
        $total = $subtotal + $shippingCost;

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
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'total_items' => $items->sum('quantity'),
            ],
            'addresses' => $addresses,
            'defaultAddress' => $defaultAddress,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'address_id' => ['nullable', 'exists:addresses,id'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'province' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'full_address' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'save_address' => ['nullable', 'boolean'],
        ]);

        $userId = Auth::id();

        if (! empty($validated['address_id'])) {
            $addressBelongsToUser = Address::query()
                ->where('id', $validated['address_id'])
                ->where('user_id', $userId)
                ->exists();

            if (! $addressBelongsToUser) {
                abort(403);
            }
        }

        $result = DB::transaction(function () use ($validated, $userId) {
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

            $variantIds = $cart->items
                ->pluck('product_variant_id')
                ->unique()
                ->values();

            $lockedVariants = ProductVariant::query()
                ->with('product')
                ->whereIn('id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($cart->items as $item) {
                $variant = $lockedVariants->get($item->product_variant_id);

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

                if ($item->quantity > $variant->stock) {
                    return [
                        'error' => 'Stok ' . $variant->product->name . ' tidak cukup. Silakan update bag kamu.',
                    ];
                }
            }

            $subtotal = $cart->items->sum(function ($item) use ($lockedVariants) {
                $variant = $lockedVariants->get($item->product_variant_id);
                $unitPrice = (float) $variant->product->price + (float) $variant->additional_price;

                return $unitPrice * $item->quantity;
            });

            $shippingCost = 15000;
            $total = $subtotal + $shippingCost;

            if ($validated['save_address'] ?? false) {
                $hasAddress = Address::query()
                    ->where('user_id', $userId)
                    ->exists();

                Address::query()->create([
                    'user_id' => $userId,
                    'recipient_name' => $validated['recipient_name'],
                    'phone' => $validated['phone'],
                    'province' => $validated['province'],
                    'city' => $validated['city'],
                    'district' => $validated['district'],
                    'postal_code' => $validated['postal_code'] ?? null,
                    'full_address' => $validated['full_address'],
                    'is_default' => ! $hasAddress,
                ]);
            }

            $order = Order::query()->create([
                'user_id' => $userId,
                'order_code' => $this->generateOrderCode(),
                'recipient_name' => $validated['recipient_name'],
                'phone' => $validated['phone'],
                'province' => $validated['province'],
                'city' => $validated['city'],
                'district' => $validated['district'],
                'postal_code' => $validated['postal_code'] ?? null,
                'full_address' => $validated['full_address'],
                'notes' => $validated['notes'] ?? null,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'payment_status' => 'pending',
                'order_status' => 'pending',
                'shipping_status' => 'not_shipped',
                'stock_released' => false,
            ]);

            foreach ($cart->items as $item) {
                $variant = $lockedVariants->get($item->product_variant_id);
                $product = $variant->product;
                $unitPrice = (float) $product->price + (float) $variant->additional_price;

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $product->name,
                    'variant_name' => $variant->size ?? $variant->sku ?? 'Default',
                    'sku' => $variant->sku,
                    'price' => $unitPrice,
                    'quantity' => $item->quantity,
                    'subtotal' => $unitPrice * $item->quantity,
                ]);

                $variant->decrement('stock', $item->quantity);
            }

            Payment::query()->create([
                'order_id' => $order->id,
                'payment_method' => 'manual_transfer',
                'amount' => $total,
                'status' => 'pending',
            ]);

            Shipment::query()->create([
                'order_id' => $order->id,
                'courier' => null,
                'service' => null,
                'tracking_number' => null,
                'status' => 'not_shipped',
            ]);

            $cart->items()->delete();

            return [
                'order' => $order,
            ];
        });

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return redirect()->route('orders.show', $result['order']->order_code)
            ->with('success', 'Order berhasil dibuat. Silakan upload bukti pembayaran.');
    }

    private function generateOrderCode(): string
    {
        do {
            $code = 'NE-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::query()->where('order_code', $code)->exists());

        return $code;
    }
}