<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\OrderNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(): Response
    {
        $notifications = OrderNotification::query()
            ->with('order:id,order_code')
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $unreadCount = OrderNotification::query()
            ->where('user_id', Auth::id())
            ->where('is_read', false)
            ->count();

        return Inertia::render('Store/Notifications/Index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function read(OrderNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === Auth::id(), 403);

        $notification->markAsRead();

        if ($notification->order?->order_code) {
            return redirect("/orders/{$notification->order->order_code}");
        }

        return redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        OrderNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return back();
    }
}