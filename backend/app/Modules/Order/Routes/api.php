<?php

use App\Modules\Order\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Modules\Order\Http\Controllers\CheckoutController;
use App\Modules\Order\Http\Controllers\InvoiceController;
use App\Modules\Order\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Checkout & Order Routes (prefix: /api/v1)
|--------------------------------------------------------------------------
*/

Route::middleware('auth.firebase')->group(function () {
    Route::post('checkout/preview', [CheckoutController::class, 'preview'])->name('checkout.preview');
    Route::post('checkout', [CheckoutController::class, 'store'])
        ->middleware('throttle:checkout')
        ->name('checkout.store');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order:order_number}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('orders/{order:order_number}/invoice', [InvoiceController::class, 'show'])->name('orders.invoice');
    Route::post('orders/{order:order_number}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    Route::get('admin/orders', [AdminOrderController::class, 'index'])
        ->middleware('can:manage_orders')
        ->name('admin.orders.index');
});
