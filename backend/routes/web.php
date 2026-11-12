<?php

use App\Modules\Platform\Http\Controllers\ServiceDescriptorController;
use Illuminate\Support\Facades\Route;

// Root bukan halaman: backend ZENTRO adalah API-only.
// Lihat /api/v1 untuk API dan /docs/api untuk dokumentasi (environment local).
Route::get('/', ServiceDescriptorController::class)->name('service.descriptor');
