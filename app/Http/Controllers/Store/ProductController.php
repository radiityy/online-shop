<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $categorySlug = trim((string) $request->query('category', ''));

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'slug',
            ]);

        $products = Product::query()
            ->with([
                'category:id,name,slug',
                'primaryImage:id,product_id,image_path',
            ])
            ->withSum(['variants as stock_total' => function ($query) {
                $query->where('is_active', true);
            }], 'stock')
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('products.name', 'like', '%' . $search . '%')
                        ->orWhere('products.slug', 'like', '%' . $search . '%')
                        ->orWhere('products.description', 'like', '%' . $search . '%')
                        ->orWhereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery
                                ->where('name', 'like', '%' . $search . '%')
                                ->orWhere('slug', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when($categorySlug !== '', function ($query) use ($categorySlug) {
                $query->whereHas('category', function ($categoryQuery) use ($categorySlug) {
                    $categoryQuery->where('slug', $categorySlug);
                });
            })
            ->orderByRaw('CASE WHEN COALESCE(stock_total, 0) > 0 THEN 0 ELSE 1 END')
            ->latest('products.created_at')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Store/Products/Index', [
            'categories' => $categories,
            'products' => $products,
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'category' => $categorySlug !== '' ? $categorySlug : null,
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $product = Product::query()
            ->with([
                'category:id,name,slug',
                'images' => function ($query) {
                    $query->orderByDesc('is_primary')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
                'variants' => function ($query) {
                    $query->where('is_active', true)
                        ->orderByRaw("FIELD(UPPER(size), 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL')")
                        ->orderBy('size');
                },
            ])
            ->where('is_active', true)
            ->where('slug', $slug)
            ->firstOrFail();

        $relatedProducts = Product::query()
            ->with([
                'category:id,name,slug',
                'primaryImage:id,product_id,image_path',
            ])
            ->withSum(['variants as stock_total' => function ($query) {
                $query->where('is_active', true);
            }], 'stock')
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->orderByRaw('CASE WHEN COALESCE(stock_total, 0) > 0 THEN 0 ELSE 1 END')
            ->latest('products.created_at')
            ->take(4)
            ->get([
                'id',
                'category_id',
                'name',
                'slug',
                'price',
                'sale_price',
                'weight',
            ]);

        return Inertia::render('Store/Products/Show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
        ]);
    }
}