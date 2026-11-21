<?php

/*
|--------------------------------------------------------------------------
| Region Routes (prefix: /api/v1)
|--------------------------------------------------------------------------
|
| Direktori wilayah administrasi Indonesia untuk formulir alamat.
| Publik (tanpa autentikasi) karena hanya data referensi statis.
|
*/

use App\Modules\Region\Http\Controllers\RegionController;
use Illuminate\Support\Facades\Route;

Route::prefix('regions')->group(function () {
    Route::get('provinces', [RegionController::class, 'provinces'])->name('regions.provinces');
    Route::get('regencies', [RegionController::class, 'regencies'])->name('regions.regencies');
    Route::get('districts', [RegionController::class, 'districts'])->name('regions.districts');
    Route::get('villages', [RegionController::class, 'villages'])->name('regions.villages');
});
