<?php

use App\Modules\Finance\Http\Controllers\Admin\AdminCommissionController;
use App\Modules\Finance\Http\Controllers\Admin\AdminPayoutController;
use App\Modules\Finance\Http\Controllers\Admin\AdminRefundController;
use App\Modules\Finance\Http\Controllers\CustomerRefundController;
use App\Modules\Finance\Http\Controllers\Seller\SellerBankAccountController;
use App\Modules\Finance\Http\Controllers\Seller\SellerCommissionController;
use App\Modules\Finance\Http\Controllers\Seller\SellerPayoutController;
use App\Modules\Finance\Http\Controllers\Seller\SellerWalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Finance Routes (prefix: /api/v1)
|--------------------------------------------------------------------------
*/

Route::middleware('auth.firebase')->group(function () {
    // --- Pembeli: refund pesanan sendiri ---
    Route::get('orders/{order:order_number}/refunds', [CustomerRefundController::class, 'index'])
        ->name('orders.refunds.index');
    Route::post('orders/{order:order_number}/refunds', [CustomerRefundController::class, 'store'])
        ->middleware('throttle:finance-action')
        ->name('orders.refunds.store');

    Route::prefix('seller')->middleware(['seller.exists', 'seller.active'])->group(function () {
        Route::get('wallet', [SellerWalletController::class, 'show'])->name('seller.wallet.show');
        Route::get('wallet/transactions', [SellerWalletController::class, 'transactions'])
            ->name('seller.wallet.transactions');

        Route::get('bank-accounts', [SellerBankAccountController::class, 'index'])
            ->name('seller.bank-accounts.index');
        Route::post('bank-accounts', [SellerBankAccountController::class, 'store'])
            ->middleware('throttle:finance-action')
            ->name('seller.bank-accounts.store');

        Route::get('payouts', [SellerPayoutController::class, 'index'])->name('seller.payouts.index');
        Route::post('payouts', [SellerPayoutController::class, 'store'])
            ->middleware('throttle:finance-action')
            ->name('seller.payouts.store');
        Route::post('payouts/{payout:payout_number}/cancel', [SellerPayoutController::class, 'cancel'])
            ->name('seller.payouts.cancel');

        Route::get('commissions', [SellerCommissionController::class, 'index'])
            ->name('seller.commissions.index');
    });

    Route::prefix('admin')->group(function () {
        Route::get('commissions', [AdminCommissionController::class, 'index'])
            ->middleware('can:manage_commissions')
            ->name('admin.commissions.index');

        Route::get('payouts', [AdminPayoutController::class, 'index'])
            ->middleware('can:manage_payouts')
            ->name('admin.payouts.index');
        Route::post('payouts/{payout}/process', [AdminPayoutController::class, 'process'])
            ->middleware('can:manage_payouts')
            ->name('admin.payouts.process');

        Route::get('refunds', [AdminRefundController::class, 'index'])
            ->middleware('can:manage_refunds')
            ->name('admin.refunds.index');
        Route::post('refunds', [AdminRefundController::class, 'store'])
            ->middleware('can:manage_refunds')
            ->name('admin.refunds.store');
        Route::post('refunds/{refund}/approve', [AdminRefundController::class, 'approve'])
            ->middleware('can:manage_refunds')
            ->name('admin.refunds.approve');
        Route::post('refunds/{refund}/reject', [AdminRefundController::class, 'reject'])
            ->middleware('can:manage_refunds')
            ->name('admin.refunds.reject');
    });
});
