<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
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
            ->with([
                'order' => fn ($query) => $query
                    ->select('id', 'order_code', 'created_at')
                    ->withCount('items')
                    ->with([
                        'items' => fn ($items) => $items
                            ->orderBy('id')
                            ->with('product.primaryImage'),
                    ]),
            ])
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (OrderNotification $notification) => $this->present($notification));

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

    private function present(OrderNotification $notification): array
    {
        $order = $notification->order;
        $item = $order?->items->first();

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'is_read' => (bool) $notification->is_read,
            'created_at' => $notification->created_at?->toIso8601String(),
            'order' => $order ? [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'ordered_at' => $order->created_at?->toIso8601String(),
                'extra_items' => max(0, (int) $order->items_count - 1),
                'product' => $item ? [
                    'name' => $item->product_name,
                    'size' => $this->sizeLabel($item),
                    'image' => $this->imageUrl($item),
                ] : null,
            ] : null,
        ];
    }

    private function sizeLabel(OrderItem $item): ?string
    {
        if (blank($item->variant_name)) {
            return null;
        }

        $first = trim(explode('/', $item->variant_name)[0]);

        return $first !== '' ? $first : $item->variant_name;
    }

    private function imageUrl(OrderItem $item): string
    {
        $path = $item->product?->primaryImage?->image_path;

        return $path ? '/storage/'.$path : '/placeholder-product.jpg';
    }
}