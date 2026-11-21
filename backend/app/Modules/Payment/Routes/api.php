<?php

use App\Modules\Payment\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Modules\Payment\Http\Controllers\DevPaymentSimulatorController;
use App\Modules\Payment\Http\Controllers\PaymentController;
use App\Modules\Payment\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payment Routes (prefix: /api/v1)
|--------------------------------------------------------------------------
*/

Route::middleware('auth.firebase')->group(function () {
    Route::get('orders/{order:order_number}/payment', [PaymentController::class, 'show'])
        ->name('orders.payment.show');

    Route::post('orders/{order:order_number}/payment/retry', [PaymentController::class, 'retry'])
        ->middleware('throttle:payment-retry')
        ->name('orders.payment.retry');

    Route::post('orders/{order:order_number}/payment/sync', [PaymentController::class, 'sync'])
        ->middleware('throttle:payment-sync')
        ->name('orders.payment.sync');

    Route::get('admin/payments', [AdminPaymentController::class, 'index'])
        ->middleware('can:manage_payments')
        ->name('admin.payments.index');
});

// Webhook provider → Laravel. Tanpa auth bearer; signature diverifikasi di dalam.
Route::post('webhooks/payments/{provider}', PaymentWebhookController::class)
    ->middleware('throttle:webhook')
    ->name('webhooks.payments');

if (app()->environment('local')) {
    Route::post('dev/payments/{order:order_number}/simulate', DevPaymentSimulatorController::class)
        ->name('dev.payments.simulate');
}
