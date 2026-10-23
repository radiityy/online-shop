<?php

namespace App\Http\Middleware;

use App\Models\Cart;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();

        return [
            ...parent::share($request),

            'name' => config('app.name'),

            'quote' => [
                'message' => trim($message),
                'author' => trim($author),
            ],

            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'email_verified_at' => $user->email_verified_at,
                ] : null,
            ],

            'cart_count' => $user
                ? Cart::query()
                    ->where('user_id', $user->id)
                    ->withSum('items as total_quantity', 'quantity')
                    ->first()?->total_quantity ?? 0
                : 0,
            'wishlist_count' => $user
                ? $user->wishlists()->count()
                : 0,

            'unreadNotificationsCount' => $user
                ? $user->orderNotifications()
                    ->where('is_read', false)
                    ->count()
                : 0,

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}