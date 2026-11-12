<?php

use App\Modules\Platform\Http\Controllers\Admin\AuditLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform Routes (prefix: /api/v1)
|--------------------------------------------------------------------------
*/

Route::middleware('auth.firebase')->prefix('admin')->group(function () {
    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->middleware('can:view_reports')
        ->name('admin.audit-logs.index');
});
