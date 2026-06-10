<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(): Response
    {
        $orders = Order::query()
            ->with(['payment', 'shipment'])
            ->withCount('items')
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Store/Orders/Index', [
            'orders' => $orders,
        ]);
    }

    public function show(string $orderCode): Response
    {
        $order = Order::query()
            ->with([
                'items.product.primaryImage',
                'payment',
                'shipment',
            ])
            ->where('order_code', $orderCode)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return Inertia::render('Store/Orders/Show', [
            'order' => $order,
        ]);
    }
}