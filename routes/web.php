<?php

use App\Http\Controllers\Store\HomeController;
use App\Http\Controllers\Store\ProductController;
use App\Http\Controllers\Store\CartController;
use App\Http\Controllers\Store\CheckoutController;
use App\Http\Controllers\Store\OrderController;
use App\Http\Controllers\Store\PaymentController;
use App\Http\Controllers\Store\AddressController;
use Illuminate\Support\Facades\Route;

use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.items.destroy');

    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{orderCode}', [OrderController::class, 'show'])->name('orders.show');

    Route::post('/orders/{orderCode}/payment-proof', [PaymentController::class, 'uploadProof'])
        ->name('orders.payment-proof');
        Route::get('/account/addresses', [AddressController::class, 'index'])
    ->name('account.addresses.index');

Route::post('/account/addresses', [AddressController::class, 'store'])
    ->name('account.addresses.store');

Route::put('/account/addresses/{address}', [AddressController::class, 'update'])
    ->name('account.addresses.update');

Route::delete('/account/addresses/{address}', [AddressController::class, 'destroy'])
    ->name('account.addresses.destroy');

Route::patch('/account/addresses/{address}/default', [AddressController::class, 'setDefault'])
    ->name('account.addresses.default');
});
require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';