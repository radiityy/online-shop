<?php

use App\Http\Controllers\Store\AccountController;
use App\Http\Controllers\Store\AddressController;
use App\Http\Controllers\Store\BiteshipWebhookController;
use App\Http\Controllers\Store\CartController;
use App\Http\Controllers\Store\CheckoutController;
use App\Http\Controllers\Store\HomeController;
use App\Http\Controllers\Store\MidtransWebhookController;
use App\Http\Controllers\Store\NotificationController;
use App\Http\Controllers\Store\OrderController;
use App\Http\Controllers\Store\PageController;
use App\Http\Controllers\Store\PaymentController;
use App\Http\Controllers\Store\ProductController;
use App\Http\Controllers\Store\RefundAccountController;
use App\Http\Controllers\Store\ShippingController;
use App\Http\Controllers\Store\WishlistController;
use App\Http\Middleware\BlockAdminFromStore;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/midtrans', MidtransWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.midtrans');

Route::post('/webhooks/biteship', BiteshipWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.biteship');

Route::middleware([BlockAdminFromStore::class])->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');

    Route::get('/faq', [PageController::class, 'faq'])->name('faq');
    Route::get('/shipping', [PageController::class, 'shipping'])->name('shipping');
    Route::get('/returns', [PageController::class, 'returns'])->name('returns');
    Route::get('/about', [PageController::class, 'about'])->name('about');

    Route::middleware(['auth'])->group(function () {
        Route::get('/dashboard', function () {
            return redirect()->route('home');
        })->name('dashboard');

        Route::get('/cart', [CartController::class, 'index'])->name('cart.index');

        Route::post('/cart', [CartController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('cart.store');

        Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])
            ->middleware('throttle:60,1')
            ->name('cart.items.update');

        Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])
            ->middleware('throttle:60,1')
            ->name('cart.items.destroy');

        Route::get('/checkout', [CheckoutController::class, 'index'])
            ->name('checkout.index');

        Route::post('/checkout', [CheckoutController::class, 'store'])
            ->middleware('throttle:8,1')
            ->name('checkout.store');

        Route::get('/shipping/areas', [ShippingController::class, 'areas'])
            ->middleware('throttle:30,1')
            ->name('shipping.areas');

        Route::post('/shipping/rates', [ShippingController::class, 'rates'])
            ->middleware('throttle:20,1')
            ->name('shipping.rates');

        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('notifications.index');

        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
            ->name('notifications.read-all');

        Route::get('/notifications/latest', [NotificationController::class, 'latest'])
            ->middleware('throttle:60,1')
            ->name('notifications.latest');

        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])
            ->name('notifications.read');

        Route::get('/orders', [OrderController::class, 'index'])
            ->name('orders.index');

        Route::get('/orders/{orderCode}', [OrderController::class, 'show'])
            ->name('orders.show');

        Route::post('/orders/{orderCode}/cancel', [OrderController::class, 'cancel'])
            ->middleware('throttle:6,1')
            ->name('orders.cancel');

        Route::post('/orders/{orderCode}/reorder', [OrderController::class, 'reorder'])
            ->middleware('throttle:10,1')
            ->name('orders.reorder');

        Route::post('/orders/{orderCode}/refund-accounts/{payment}', [RefundAccountController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('orders.refund-account.store');

        Route::post('/orders/{orderCode}/pay', [PaymentController::class, 'pay'])
            ->middleware('throttle:6,1')
            ->name('orders.pay');

        Route::post('/orders/{orderCode}/payment/refresh', [PaymentController::class, 'refresh'])
            ->middleware('throttle:12,1')
            ->name('orders.payment.refresh');

        Route::get('/account', [AccountController::class, 'index'])
            ->name('account.index');

        Route::get('/account/addresses', [AddressController::class, 'index'])
            ->name('account.addresses.index');

        Route::post('/account/addresses', [AddressController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('account.addresses.store');

        Route::put('/account/addresses/{address}', [AddressController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('account.addresses.update');

        Route::delete('/account/addresses/{address}', [AddressController::class, 'destroy'])
            ->middleware('throttle:20,1')
            ->name('account.addresses.destroy');

        Route::patch('/account/addresses/{address}/default', [AddressController::class, 'setDefault'])
            ->middleware('throttle:30,1')
            ->name('account.addresses.default');

        Route::get('/wishlist', [WishlistController::class, 'index'])
            ->name('wishlist.index');

        Route::post('/wishlist/{product:slug}', [WishlistController::class, 'store'])
            ->name('wishlist.store');

        Route::delete('/wishlist/{product:slug}', [WishlistController::class, 'destroy'])
            ->name('wishlist.destroy');

        Route::post('/wishlist/{product:slug}/toggle', [WishlistController::class, 'toggle'])
            ->name('wishlist.toggle');
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';