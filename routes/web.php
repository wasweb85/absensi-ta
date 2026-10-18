<?php

use Illuminate\Support\Facades\Route;

// Rute fallback untuk SPA Vue.js (kecuali prefix api/)
Route::get('/{any?}', function () {
    return view('welcome');
})->where('any', '^(?!api).*$');
