<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();

        $baseOrderQuery = Order::query()
            ->where('user_id', $user->id);

        $totalOrders = (clone $baseOrderQuery)->count();

        $pendingPaymentOrders = (clone $baseOrderQuery)
            ->where('payment_status', 'pending')
            ->count();

        $paidOrders = (clone $baseOrderQuery)
            ->where('payment_status', 'paid')
            ->count();

        $completedOrders = (clone $baseOrderQuery)
            ->whereIn('order_status', ['completed', 'delivered'])
            ->count();

        $totalSpent = (clone $baseOrderQuery)
            ->where('payment_status', 'paid')
            ->sum('total');

        $defaultAddress = Address::query()
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->first();

        if (! $defaultAddress) {
            $defaultAddress = Address::query()
                ->where('user_id', $user->id)
                ->latest()
                ->first();
        }

        $recentOrders = Order::query()
            ->with(['payment', 'shipment'])
            ->withCount('items')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'order_code' => $order->order_code,
                    'total' => $order->total,
                    'payment_status' => $order->payment_status,
                    'order_status' => $order->order_status,
                    'shipping_status' => $order->shipping_status,
                    'created_at' => $order->created_at,
                    'items_count' => $order->items_count,
                    'payment' => $order->payment ? [
                        'payment_method' => $order->payment->payment_method,
                        'status' => $order->payment->status,
                    ] : null,
                    'shipment' => $order->shipment ? [
                        'courier' => $order->shipment->courier,
                        'service' => $order->shipment->service,
                        'tracking_number' => $order->shipment->tracking_number,
                        'status' => $order->shipment->status,
                    ] : null,
                ];
            })
            ->values();

        return Inertia::render('Store/Account/Index', [
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
            ],
            'stats' => [
                'total_orders' => $totalOrders,
                'pending_payment_orders' => $pendingPaymentOrders,
                'paid_orders' => $paidOrders,
                'completed_orders' => $completedOrders,
                'total_spent' => (float) $totalSpent,
            ],
            'defaultAddress' => $defaultAddress ? [
                'id' => $defaultAddress->id,
                'recipient_name' => $defaultAddress->recipient_name,
                'phone' => $defaultAddress->phone,
                'province' => $defaultAddress->province,
                'city' => $defaultAddress->city,
                'district' => $defaultAddress->district,
                'postal_code' => $defaultAddress->postal_code,
                'full_address' => $defaultAddress->full_address,
                'is_default' => $defaultAddress->is_default,
            ] : null,
            'recentOrders' => $recentOrders,
        ]);
    }
}