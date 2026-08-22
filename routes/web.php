<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CartItemController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WompiEventController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Wompi calls this server to server, so it is public, stateless and exempt
// from CSRF verification in bootstrap/app.php.
Route::post('wompi/events', WompiEventController::class)->name('wompi.events');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');

    Route::get('cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('cart/items', [CartItemController::class, 'store'])->name('cart.items.store');
    Route::patch('cart/items/{cartItem}', [CartItemController::class, 'update'])->name('cart.items.update');
    Route::delete('cart/items/{cartItem}', [CartItemController::class, 'destroy'])->name('cart.items.destroy');

    Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('checkout', [CheckoutController::class, 'pay'])->name('checkout.pay');
    Route::get('checkout/result', [CheckoutController::class, 'result'])->name('checkout.result');
});

require __DIR__.'/settings.php';
