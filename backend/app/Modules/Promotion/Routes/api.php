<?php

use App\Modules\Promotion\Http\Controllers\Admin\VoucherController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Promotion Routes (prefix: /api/v1)
|--------------------------------------------------------------------------
*/

Route::middleware('auth.firebase')->prefix('admin')->group(function () {
    Route::get('vouchers', [VoucherController::class, 'index'])
        ->middleware('can:manage_vouchers')
        ->name('admin.vouchers.index');

    Route::post('vouchers', [VoucherController::class, 'store'])
        ->middleware('can:manage_vouchers')
        ->name('admin.vouchers.store');

    Route::patch('vouchers/{voucher}', [VoucherController::class, 'update'])
        ->middleware('can:manage_vouchers')
        ->name('admin.vouchers.update');
});
