<?php

use App\Modules\Chat\Http\Controllers\AssistantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Assistant (chatbot) Routes (prefix: /api/v1)
|--------------------------------------------------------------------------
|
| Endpoint publik: pembeli belum tentu sudah masuk. Penambahan ke keranjang
| tetap memakai endpoint cart yang mewajibkan autentikasi.
|
*/

Route::post('assistant/chat', [AssistantController::class, 'chat'])
    ->middleware('throttle:assistant')
    ->name('assistant.chat');
