<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        $banners = Banner::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->latest()
            ->get()
            ->map(function (Banner $banner) {
                return [
                    'id' => $banner->id,
                    'title' => $banner->title,
                    'subtitle' => $banner->subtitle,
                    'image_path' => $banner->image_path,
                    'link_url' => $banner->link_url ?: '/products',
                ];
            })
            ->values();

        $products = Product::query()
            ->with([
                'category:id,name,slug',
                'primaryImage:id,product_id,image_path',
            ])
            ->select('products.*')
            ->selectSub(function ($query) {
                $query->from('product_variants')
                    ->selectRaw('COALESCE(SUM(stock), 0)')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->where('product_variants.is_active', true);
            }, 'stock_total')
            ->where('is_active', true)
            ->whereHas('variants', function ($query) {
                $query->where('is_active', true)
                    ->where('stock', '>', 0);
            })
            ->latest()
            ->limit(8)
            ->get()
            ->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => $product->price,
                    'sale_price' => $product->sale_price,
                    'final_price' => $product->final_price,
                    'is_on_sale' => $product->is_on_sale,
                    'stock_total' => (int) $product->stock_total,
                    'weight' => $product->weight,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                        'slug' => $product->category->slug,
                    ] : null,
                    'primary_image' => $product->primaryImage ? [
                        'id' => $product->primaryImage->id,
                        'image_path' => $product->primaryImage->image_path,
                    ] : null,
                ];
            })
            ->values();

        return Inertia::render('Store/Home', [
            'banners' => $banners,
            'products' => $products,
        ]);
    }
}