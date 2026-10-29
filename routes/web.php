<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// Alias untuk login jika dipanggil tanpa prefix /api
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Rute fallback untuk SPA Vue.js (kecuali prefix api/)
Route::get('/{any?}', function () {
    return view('welcome');
})->where('any', '^(?!api).*$');
