<?php

namespace App\Services;

use App\Exceptions\ShippingException;
use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShippingQuoteService
{
    public function __construct(private readonly BiteshipService $biteship)
    {
    }

    public function forCart(Cart $cart, string $destinationAreaId): array
    {
        $origin = StoreSetting::current();

        if (! $origin->hasOrigin()) {
            Log::error('Titik asal pengiriman belum diatur.');

            throw new ShippingException('Pengiriman belum tersedia. Silakan hubungi toko.');
        }

        $items = $this->itemsFromCart($cart);

        $insuranceValue = (int) collect($items)
            ->sum(fn (array $item) => $item['value'] * $item['quantity']);

        return $this->biteship->rates(
            $origin->origin_area_id,
            $destinationAreaId,
            $items,
            $insuranceValue
        );
    }

    public function itemsFromCart(Cart $cart): array
    {
        $cart->loadMissing(['items.product', 'items.variant']);

        if ($cart->items->isEmpty()) {
            throw new ShippingException('Bag masih kosong.', 422);
        }

        return $cart->items->map(function ($item) {
            $product = $item->product;
            $variant = $item->variant;

            if (! $product || ! $variant) {
                throw new ShippingException('Ada produk yang tidak ditemukan. Silakan periksa bag kamu.', 422);
            }

            if ((int) $product->weight < 1) {
                Log::warning('Produk tanpa berat tidak bisa dihitung ongkirnya.', [
                    'product_id' => $product->id,
                ]);

                throw new ShippingException('Ada produk yang beratnya belum diatur. Silakan hubungi toko.', 422);
            }

            return [
                'name' => $this->itemName($product->name, $variant),
                'value' => (int) round((float) $product->final_price + (float) $variant->additional_price),
                'quantity' => (int) $item->quantity,
                'weight' => (int) $product->weight,
            ];
        })->values()->all();
    }

    private function itemName(string $productName, ProductVariant $variant): string
    {
        $parts = array_filter([$productName, $variant->size, $variant->color]);

        return Str::limit(implode(' ', $parts), 100, '');
    }
}
