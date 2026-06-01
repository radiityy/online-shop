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
            ->get([
                'id',
                'title',
                'subtitle',
                'image_path',
                'link_url',
            ]);

        $products = Product::query()
            ->with(['category:id,name,slug', 'primaryImage:id,product_id,image_path'])
            ->where('is_active', true)
            ->latest()
            ->take(8)
            ->get([
                'id',
                'category_id',
                'name',
                'slug',
                'price',
                'weight',
            ]);

        return Inertia::render('Store/Home', [
            'banners' => $banners,
            'products' => $products,
        ]);
    }
}