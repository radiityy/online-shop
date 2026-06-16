<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WishlistController extends Controller
{
    public function index(): Response
    {
        $items = Wishlist::query()
            ->with([
                'product.category',
                'product.primaryImage',
            ])
            ->where('user_id', Auth::id())
            ->latest()
            ->get()
            ->filter(fn (Wishlist $wishlist) => $wishlist->product !== null)
            ->map(function (Wishlist $wishlist) {
                $product = $wishlist->product;

                return [
                    'id' => $wishlist->id,
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'price' => $product->price,
                        'sale_price' => $product->sale_price,
                        'final_price' => $product->final_price ?? $product->sale_price ?? $product->price,
                        'is_on_sale' => $product->is_on_sale ?? false,
                        'is_active' => $product->is_active,
                        'category' => $product->category ? [
                            'id' => $product->category->id,
                            'name' => $product->category->name,
                            'slug' => $product->category->slug,
                        ] : null,
                        'primary_image' => $product->primaryImage ? [
                            'id' => $product->primaryImage->id,
                            'image_path' => $product->primaryImage->image_path,
                        ] : null,
                    ],
                    'created_at' => $wishlist->created_at,
                ];
            })
            ->values();

        return Inertia::render('Store/Wishlist/Index', [
            'wishlistItems' => $items,
        ]);
    }

    public function store(Product $product): RedirectResponse
    {
        abort_if(! $product->is_active, 404);

        Wishlist::query()->firstOrCreate([
            'user_id' => Auth::id(),
            'product_id' => $product->id,
        ]);

        return back()->with('success', 'Product added to wishlist.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        Wishlist::query()
            ->where('user_id', Auth::id())
            ->where('product_id', $product->id)
            ->delete();

        return back()->with('success', 'Product removed from wishlist.');
    }

    public function toggle(Product $product): RedirectResponse
    {
        abort_if(! $product->is_active, 404);

        $wishlist = Wishlist::query()
            ->where('user_id', Auth::id())
            ->where('product_id', $product->id)
            ->first();

        if ($wishlist) {
            $wishlist->delete();

            return back()->with('success', 'Product removed from wishlist.');
        }

        Wishlist::query()->create([
            'user_id' => Auth::id(),
            'product_id' => $product->id,
        ]);

        return back()->with('success', 'Product added to wishlist.');
    }
}