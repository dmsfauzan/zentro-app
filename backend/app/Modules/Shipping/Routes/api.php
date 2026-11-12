<?php

use App\Modules\Shipping\Http\Controllers\DevShipmentSimulatorController;
use App\Modules\Shipping\Http\Controllers\OrderDeliveryController;
use App\Modules\Shipping\Http\Controllers\Seller\SellerOrderController;
use App\Modules\Shipping\Http\Controllers\Seller\SellerShipmentController;
use App\Modules\Shipping\Http\Controllers\ShipmentTrackingController;
use App\Modules\Shipping\Http\Controllers\ShippingQuoteController;
use App\Modules\Shipping\Http\Controllers\ShippingWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Shipping Routes (prefix: /api/v1)
|--------------------------------------------------------------------------
*/

Route::middleware('auth.firebase')->group(function () {
    Route::get('shipping/quotes', [ShippingQuoteController::class, 'index'])->name('shipping.quotes');

    Route::prefix('seller')->group(function () {
        Route::middleware('seller.exists')->group(function () {
            Route::get('orders', [SellerOrderController::class, 'index'])->name('seller.orders.index');
            Route::get('orders/{sellerOrder}', [SellerOrderController::class, 'show'])->name('seller.orders.show');
            Route::get('shipments', [SellerShipmentController::class, 'index'])->name('seller.shipments.index');
            Route::get('shipments/{shipment}', [SellerShipmentController::class, 'show'])->name('seller.shipments.show');

            Route::middleware('seller.active')->group(function () {
                Route::post('orders/{sellerOrder}/confirm', [SellerOrderController::class, 'confirm'])
                    ->middleware('throttle:shipping-action')
                    ->name('seller.orders.confirm');

                Route::post('orders/{sellerOrder}/shipments', [SellerOrderController::class, 'createShipment'])
                    ->middleware('throttle:shipping-action')
                    ->name('seller.orders.shipments.store');

                Route::post('shipments/{shipment}/dispatch', [SellerShipmentController::class, 'dispatch'])
                    ->middleware('throttle:shipping-action')
                    ->name('seller.shipments.dispatch');
            });
        });
    });

    Route::get('shipments/{shipment}/tracking', [ShipmentTrackingController::class, 'show'])
        ->name('shipments.tracking');

    Route::post('orders/{order:order_number}/confirm-received', [OrderDeliveryController::class, 'confirmReceived'])
        ->name('orders.confirm-received');
});

Route::post('webhooks/shipping/{provider}', ShippingWebhookController::class)
    ->middleware('throttle:webhook')
    ->name('webhooks.shipping');

if (app()->environment('local')) {
    Route::post('dev/shipments/{shipment}/track', DevShipmentSimulatorController::class)
        ->name('dev.shipments.track');
}
