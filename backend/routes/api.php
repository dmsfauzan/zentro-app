<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| Semua route API berada di bawah prefix /api (lihat bootstrap/app.php) dan
| /v1 untuk versi. Route per modul dimuat dari masing-masing folder modul
| agar batas domain tetap jelas (modular monolith).
|
*/

Route::prefix('v1')->group(function () {
    require app_path('Modules/Identity/Routes/api.php');
    require app_path('Modules/Region/Routes/api.php');
    require app_path('Modules/Seller/Routes/api.php');
    require app_path('Modules/Catalog/Routes/api.php');
    require app_path('Modules/Chat/Routes/api.php');
    require app_path('Modules/Cart/Routes/api.php');
    require app_path('Modules/Order/Routes/api.php');
    require app_path('Modules/Payment/Routes/api.php');
    require app_path('Modules/Shipping/Routes/api.php');
    require app_path('Modules/Finance/Routes/api.php');
    require app_path('Modules/Promotion/Routes/api.php');
    require app_path('Modules/Review/Routes/api.php');
    require app_path('Modules/Notification/Routes/api.php');
    require app_path('Modules/Platform/Routes/api.php');
});
