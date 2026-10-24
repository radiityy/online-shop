<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\MidtransService;
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

    public function show(string $orderCode, MidtransService $midtrans): Response
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

        $paymentMethods = [];

        if ($order->payment_expires_at !== null) {
            $total = (int) round((float) $order->total);

            $paymentMethods = collect($midtrans->methods())
                ->map(function (array $method, string $key) use ($midtrans, $total) {
                    $fee = $midtrans->feeFor($key, $total);

                    return [
                        'key' => $key,
                        'label' => $method['label'],
                        'payment_type' => $method['payment_type'],
                        'fee' => $fee,
                        'amount' => $total + $fee,
                    ];
                })
                ->values()
                ->all();
        }

        return Inertia::render('Store/Orders/Show', [
            'order' => $order,
            'paymentMethods' => $paymentMethods,
            'serverNow' => now()->toIso8601String(),
        ]);
    }
}