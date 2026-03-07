<?php

use Illuminate\Support\Facades\Route;
use Modules\Orders\Http\Controllers\OrderController;

Route::middleware('auth')->group(function () {
    Route::get('/checkout', [OrderController::class, 'showCheckout'])->name('checkout.show');
    Route::post('/checkout', [OrderController::class, 'checkout'])->name('checkout.store');
    Route::get('/mis-pedidos', [OrderController::class, 'myOrders'])->name('orders.mine');
    Route::get('/mis-pedidos/{order}', [OrderController::class, 'show'])->name('orders.show');
});
