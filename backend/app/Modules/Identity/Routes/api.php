<?php

use App\Modules\Identity\Http\Controllers\AddressController;
use App\Modules\Identity\Http\Controllers\Admin\UserController as AdminUserController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\MeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Identity & Access Routes (prefix: /api/v1)
|--------------------------------------------------------------------------
*/

Route::post('auth/firebase', [AuthController::class, 'firebase'])
    ->middleware('throttle:auth')
    ->name('auth.firebase');

Route::middleware('auth.firebase')->group(function () {
    Route::get('me', [MeController::class, 'show'])->name('me.show');
    Route::patch('me', [MeController::class, 'update'])->name('me.update');

    Route::get('me/addresses', [AddressController::class, 'index'])->name('me.addresses.index');
    Route::post('me/addresses', [AddressController::class, 'store'])->name('me.addresses.store');
    Route::patch('me/addresses/{address}', [AddressController::class, 'update'])->name('me.addresses.update');
    Route::delete('me/addresses/{address}', [AddressController::class, 'destroy'])->name('me.addresses.destroy');

    // --- Admin: manajemen pengguna & role (permission `manage_users`, hanya super admin) ---
    Route::middleware('can:manage_users')->prefix('admin')->group(function () {
        Route::get('users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
    });
});
