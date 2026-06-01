<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(): Response
    {
        $cart = Cart::query()
            ->firstOrCreate([
                'user_id' => Auth::id(),
            ]);

        $cart->load([
            'items.product.primaryImage',
            'items.variant',
        ]);

        $items = $cart->items->map(function (CartItem $item) {
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
                    'price' => $item->product->price,
                    'image_path' => $item->product->primaryImage?->image_path,
                ],
                'variant' => [
                    'id' => $item->variant->id,
                    'size' => $item->variant->size,
                    'sku' => $item->variant->sku,
                    'stock' => $item->variant->stock,
                    'additional_price' => $item->variant->additional_price,
                ],
            ];
        })->values();

        return Inertia::render('Store/Cart/Index', [
            'items' => $items,
            'summary' => [
                'subtotal' => $items->sum('subtotal'),
                'total_items' => $items->sum('quantity'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'buy_now' => ['nullable', 'boolean'],
        ]);

        $variant = ProductVariant::query()
            ->with('product')
            ->where('id', $validated['product_variant_id'])
            ->where('is_active', true)
            ->firstOrFail();

        if (! $variant->product->is_active) {
            return back()->withErrors([
                'product_variant_id' => 'Produk tidak aktif.',
            ]);
        }

        if ($variant->stock < 1) {
            return back()->withErrors([
                'product_variant_id' => 'Stok produk habis.',
            ]);
        }

        $cart = Cart::query()
            ->firstOrCreate([
                'user_id' => Auth::id(),
            ]);

        $cartItem = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('product_variant_id', $variant->id)
            ->first();

        $currentQuantity = $cartItem?->quantity ?? 0;
        $newQuantity = $currentQuantity + (int) $validated['quantity'];

        if ($newQuantity > $variant->stock) {
            return back()->withErrors([
                'quantity' => 'Jumlah melebihi stok yang tersedia.',
            ]);
        }

        if ($cartItem) {
            $cartItem->update([
                'quantity' => $newQuantity,
            ]);
        } else {
            CartItem::query()->create([
                'cart_id' => $cart->id,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'quantity' => $validated['quantity'],
            ]);
        }

        if ($request->boolean('buy_now')) {
            return redirect()->route('checkout.index')
                ->with('success', 'Produk berhasil ditambahkan. Lanjutkan checkout.');
        }

        return redirect()->route('cart.index')
            ->with('success', 'Produk berhasil ditambahkan ke bag.');
    }

    public function update(Request $request, CartItem $cartItem): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cartItem->load(['cart', 'variant']);

        if ($cartItem->cart->user_id !== Auth::id()) {
            abort(403);
        }

        if ((int) $validated['quantity'] > $cartItem->variant->stock) {
            return back()->withErrors([
                'quantity' => 'Jumlah melebihi stok yang tersedia.',
            ]);
        }

        $cartItem->update([
            'quantity' => $validated['quantity'],
        ]);

        return back()->with('success', 'Bag berhasil diperbarui.');
    }

    public function destroy(CartItem $cartItem): RedirectResponse
    {
        $cartItem->load('cart');

        if ($cartItem->cart->user_id !== Auth::id()) {
            abort(403);
        }

        $cartItem->delete();

        return back()->with('success', 'Produk berhasil dihapus dari bag.');
    }
}