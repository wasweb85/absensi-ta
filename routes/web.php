<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\PublicInvoiceController;
use Illuminate\Support\Facades\Route;

// Alias untuk login jika dipanggil tanpa prefix /api
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Tautan Publik Invoice / Kuitansi SPP Digital yang Aman
Route::get('/invoice/{token}', [PublicInvoiceController::class, 'show'])->middleware('throttle:60,1');

// Alias Webhook WhatsApp Gateway Fonnte jika pengguna memasukkan URL tanpa prefix /api
Route::match(['get', 'post'], '/webhook/whatsapp', [ChatbotController::class, 'webhook'])->middleware('throttle:120,1');
Route::match(['get', 'post'], '/webhook/whatsapp/status', [ChatbotController::class, 'messageStatusWebhook'])->middleware('throttle:120,1');
Route::match(['get', 'post'], '/webhook/whatsapp/connect', [ChatbotController::class, 'connectWebhook'])->middleware('throttle:60,1');

// Rute fallback untuk SPA Vue.js (kecuali prefix api/, webhook/, dan invoice/)
Route::get('/{any?}', function () {
    return view('welcome');
})->where('any', '^(?!(api|webhook|invoice)).*$');
