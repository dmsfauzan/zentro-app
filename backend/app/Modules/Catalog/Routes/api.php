<?php

use App\Modules\Catalog\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Modules\Catalog\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\Seller\ProductController as SellerProductController;
use App\Modules\Catalog\Http\Controllers\Seller\ProductImageController;
use App\Modules\Catalog\Http\Controllers\Seller\ProductVariantController;
use App\Modules\Inventory\Http\Controllers\Seller\InventoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Catalog & Inventory Routes (prefix: /api/v1)
|--------------------------------------------------------------------------
*/

// --- Publik ---
Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('products/{slug}', [ProductController::class, 'show'])->name('products.show');

// --- Seller (harus aktif) ---
Route::middleware(['auth.firebase', 'seller.exists', 'seller.active'])->group(function () {
    Route::get('seller/products', [SellerProductController::class, 'index'])->name('seller.products.index');
    Route::post('seller/products', [SellerProductController::class, 'store'])->name('seller.products.store');
    Route::get('seller/products/{product}', [SellerProductController::class, 'show'])->name('seller.products.show');
    Route::patch('seller/products/{product}', [SellerProductController::class, 'update'])->name('seller.products.update');
    Route::delete('seller/products/{product}', [SellerProductController::class, 'destroy'])->name('seller.products.destroy');

    Route::post('seller/products/{product}/images', [ProductImageController::class, 'store'])->name('seller.products.images.store');
    Route::delete('seller/products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->name('seller.products.images.destroy');

    Route::post('seller/products/{product}/variants', [ProductVariantController::class, 'store'])->name('seller.products.variants.store');
    Route::patch('seller/variants/{variant}', [ProductVariantController::class, 'update'])->name('seller.variants.update');
    Route::patch('seller/variants/{variant}/inventory', [InventoryController::class, 'update'])->name('seller.variants.inventory.update');
});

// --- Admin ---
Route::middleware(['auth.firebase', 'can:manage_products'])->group(function () {
    Route::get('admin/products', [AdminProductController::class, 'index'])->name('admin.products.index');
    Route::patch('admin/products/{product}/status', [AdminProductController::class, 'updateStatus'])->name('admin.products.status');
});

// --- Admin: kategori ---
Route::middleware(['auth.firebase', 'can:manage_categories'])->prefix('admin')->group(function () {
    Route::get('categories', [AdminCategoryController::class, 'index'])->name('admin.categories.index');
    Route::post('categories', [AdminCategoryController::class, 'store'])->name('admin.categories.store');
    Route::patch('categories/{category}', [AdminCategoryController::class, 'update'])->name('admin.categories.update');
    Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy'])->name('admin.categories.destroy');
});
