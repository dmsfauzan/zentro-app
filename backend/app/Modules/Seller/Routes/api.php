<?php

use App\Modules\Seller\Http\Controllers\Admin\SellerController as AdminSellerController;
use App\Modules\Seller\Http\Controllers\Admin\SellerDocumentController as AdminSellerDocumentController;
use App\Modules\Seller\Http\Controllers\SellerApplicationController;
use App\Modules\Seller\Http\Controllers\SellerDashboardController;
use App\Modules\Seller\Http\Controllers\SellerProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Seller Routes (prefix: /api/v1)
|--------------------------------------------------------------------------
*/

Route::middleware('auth.firebase')->group(function () {
    // --- Pendaftaran seller (semua user terautentikasi) ---
    Route::post('seller-applications', [SellerApplicationController::class, 'store'])
        ->name('seller-applications.store');
    Route::get('seller-applications/me', [SellerApplicationController::class, 'show'])
        ->name('seller-applications.me');
    Route::post('seller-applications/{seller}/documents', [SellerApplicationController::class, 'uploadDocument'])
        ->name('seller-applications.documents.store');

    // --- Area seller ---
    Route::prefix('seller')->group(function () {
        Route::middleware('seller.exists')->group(function () {
            Route::get('profile', [SellerProfileController::class, 'show'])->name('seller.profile.show');
            Route::patch('profile', [SellerProfileController::class, 'update'])->name('seller.profile.update');
        });

        Route::middleware(['seller.exists', 'seller.active'])->group(function () {
            Route::get('dashboard', [SellerDashboardController::class, 'show'])->name('seller.dashboard');
        });
    });

    // --- Area admin ---
    Route::prefix('admin')->group(function () {
        Route::get('sellers', [AdminSellerController::class, 'index'])
            ->middleware('can:manage_sellers')
            ->name('admin.sellers.index');

        Route::post('sellers/{seller}/approve', [AdminSellerController::class, 'approve'])
            ->middleware('can:approve_sellers')
            ->name('admin.sellers.approve');

        Route::post('sellers/{seller}/reject', [AdminSellerController::class, 'reject'])
            ->middleware('can:approve_sellers')
            ->name('admin.sellers.reject');

        Route::post('sellers/{seller}/suspend', [AdminSellerController::class, 'suspend'])
            ->middleware('can:suspend_sellers')
            ->name('admin.sellers.suspend');

        Route::post('sellers/{seller}/reactivate', [AdminSellerController::class, 'reactivate'])
            ->middleware('can:suspend_sellers')
            ->name('admin.sellers.reactivate');

        Route::get('seller-documents/{document}', [AdminSellerDocumentController::class, 'download'])
            ->middleware('can:manage_sellers')
            ->name('admin.seller-documents.download');
    });
});
