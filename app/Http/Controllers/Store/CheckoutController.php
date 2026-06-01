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
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $cart = Cart::query()
            ->firstOrCreate([
                'user_id' => Auth::id(),
            ]);

        $cart->load([
            'items.product.primaryImage',
            'items.variant',
        ]);

        if ($cart->items->isEmpty()) {
            return redirect()->route('cart.index')
                ->with('error', 'Keranjang masih kosong.');
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
        $shippingCost = 20000;
        $total = $subtotal + $shippingCost;

        return Inertia::render('Store/Checkout/Index', [
            'items' => $items,
            'summary' => [
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'total_items' => $items->sum('quantity'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'province' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'full_address' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'save_address' => ['boolean'],
        ]);

        $cart = Cart::query()
            ->where('user_id', Auth::id())
            ->with(['items.product', 'items.variant'])
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index')
                ->with('error', 'Keranjang masih kosong.');
        }

        try {
            $order = DB::transaction(function () use ($cart, $validated) {
                $subtotal = 0;

                foreach ($cart->items as $item) {
                    $variant = ProductVariant::query()
                        ->where('id', $item->product_variant_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (! $item->product->is_active || ! $variant->is_active) {
                        throw ValidationException::withMessages([
                            'cart' => 'Ada produk yang sudah tidak aktif.',
                        ]);
                    }

                    if ($variant->stock < $item->quantity) {
                        throw ValidationException::withMessages([
                            'cart' => "Stok {$item->product->name} tidak mencukupi.",
                        ]);
                    }

                    $unitPrice = (float) $item->product->price + (float) $variant->additional_price;
                    $subtotal += $unitPrice * $item->quantity;
                }

                $shippingCost = 20000;
                $total = $subtotal + $shippingCost;

                $address = null;

                if ($validated['save_address'] ?? false) {
                    Address::query()
                        ->where('user_id', Auth::id())
                        ->update(['is_default' => false]);

                    $address = Address::query()->create([
                        'user_id' => Auth::id(),
                        'recipient_name' => $validated['recipient_name'],
                        'phone' => $validated['phone'],
                        'province' => $validated['province'],
                        'city' => $validated['city'],
                        'district' => $validated['district'],
                        'postal_code' => $validated['postal_code'] ?? null,
                        'full_address' => $validated['full_address'],
                        'is_default' => true,
                    ]);
                }

                $order = Order::query()->create([
                    'user_id' => Auth::id(),
                    'address_id' => $address?->id,
                    'order_code' => $this->generateOrderCode(),
                    'recipient_name' => $validated['recipient_name'],
                    'phone' => $validated['phone'],
                    'province' => $validated['province'],
                    'city' => $validated['city'],
                    'district' => $validated['district'],
                    'postal_code' => $validated['postal_code'] ?? null,
                    'full_address' => $validated['full_address'],
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingCost,
                    'total' => $total,
                    'payment_status' => 'pending',
                    'order_status' => 'pending',
                    'shipping_status' => 'not_shipped',
                    'notes' => $validated['notes'] ?? null,
                ]);

                foreach ($cart->items as $item) {
                    $variant = ProductVariant::query()
                        ->where('id', $item->product_variant_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $unitPrice = (float) $item->product->price + (float) $variant->additional_price;

                    OrderItem::query()->create([
                        'order_id' => $order->id,
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'product_name' => $item->product->name,
                        'variant_name' => collect([$variant->size, $variant->color])->filter()->join(' / '),
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
                    'status' => 'not_shipped',
                ]);

                $cart->items()->delete();

                return $order;
            });
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return redirect()->route('orders.show', $order->order_code)
            ->with('success', 'Order berhasil dibuat. Silakan lakukan pembayaran.');
    }

    private function generateOrderCode(): string
    {
        do {
            $code = 'NE-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::query()->where('order_code', $code)->exists());

        return $code;
    }
}