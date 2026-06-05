<?php

use App\Http\Controllers\Store\AccountController;
use App\Http\Controllers\Store\AddressController;
use App\Http\Controllers\Store\CartController;
use App\Http\Controllers\Store\CheckoutController;
use App\Http\Controllers\Store\HomeController;
use App\Http\Controllers\Store\OrderController;
use App\Http\Controllers\Store\PageController;
use App\Http\Controllers\Store\PaymentController;
use App\Http\Controllers\Store\ProductController;
use Illuminate\Support\Facades\Route;

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

    Route::get('/orders', [OrderController::class, 'index'])
        ->name('orders.index');

    Route::get('/orders/{orderCode}', [OrderController::class, 'show'])
        ->name('orders.show');

    Route::post('/orders/{orderCode}/payment-proof', [PaymentController::class, 'uploadProof'])
        ->middleware('throttle:5,1')
        ->name('orders.payment-proof');

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
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';