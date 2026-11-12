<?php

use App\Modules\Review\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Modules\Review\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Review Routes (prefix: /api/v1)
|--------------------------------------------------------------------------
*/

Route::get('products/{slug}/reviews', [ReviewController::class, 'index'])
    ->name('products.reviews.index');

Route::middleware('auth.firebase')->group(function () {
    Route::post('orders/{order:order_number}/reviews', [ReviewController::class, 'store'])
        ->name('orders.reviews.store');
});

Route::middleware(['auth.firebase', 'can:manage_reviews'])->prefix('admin')->group(function () {
    Route::get('reviews', [AdminReviewController::class, 'index'])->name('admin.reviews.index');
    Route::patch('reviews/{review}', [AdminReviewController::class, 'update'])->name('admin.reviews.update');
});
