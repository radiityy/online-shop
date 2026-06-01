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
        $search = $request->query('search');
        $categorySlug = $request->query('category');

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
            ->where('is_active', true)
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->when($categorySlug, function ($query) use ($categorySlug) {
                $query->whereHas('category', function ($categoryQuery) use ($categorySlug) {
                    $categoryQuery->where('slug', $categorySlug);
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Store/Products/Index', [
            'categories' => $categories,
            'products' => $products,
            'filters' => [
                'search' => $search,
                'category' => $categorySlug,
            ],
        ]);
    }
}