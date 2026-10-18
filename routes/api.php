<?php

use App\Http\Controllers\Api\AbsensiController;
use App\Http\Controllers\Api\AgendaLiburController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KelasController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SiswaController;
use App\Http\Controllers\Api\SppController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WhatsAppController;
use Illuminate\Support\Facades\Route;

// ==========================================
// PUBLIC ROUTES
// ==========================================
Route::post('/login', [AuthController::class, 'login']);

// Endpoint scanner realtime & konfigurasi (bisa diakses oleh scanner kiosk / kamera)
Route::prefix('absensi')->group(function () {
    Route::get('/config', [AbsensiController::class, 'config']);
    Route::post('/scan', [AbsensiController::class, 'scan']);
    Route::get('/monitoring', [AbsensiController::class, 'monitoring']);
});

// ==========================================
// PROTECTED ROUTES (Sanctum Authenticated)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Master Data Kelas
    Route::get('/kelas', [KelasController::class, 'index']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/kelas', [KelasController::class, 'store']);
        Route::put('/kelas/{id}', [KelasController::class, 'update']);
        Route::delete('/kelas/{id}', [KelasController::class, 'destroy']);
    });

    // Master Data Siswa
    Route::get('/siswa', [SiswaController::class, 'index']);
    Route::get('/siswa/{id}', [SiswaController::class, 'show']);
    Route::middleware('role:admin,sekretaris')->group(function () {
        Route::post('/siswa', [SiswaController::class, 'store']);
        Route::put('/siswa/{id}', [SiswaController::class, 'update']);
        Route::delete('/siswa/{id}', [SiswaController::class, 'destroy']);
        Route::post('/siswa/import', [SiswaController::class, 'importBulk']);
    });

    // Modul Absensi (Terbuka untuk semua Ustadzah & Kepala)
    Route::prefix('absensi')->middleware('role:admin,kepala,sekretaris,bendahara,guru')->group(function () {
        Route::post('/status', [AbsensiController::class, 'updateStatus']);
        Route::get('/matrix', [AbsensiController::class, 'matrix']);
        Route::get('/riwayat', [AbsensiController::class, 'riwayat']);
    });

    // Modul SPP - Supervisi & Laporan (Admin, Bendahara, Kepala)
    Route::prefix('spp')->middleware('role:admin,bendahara,kepala')->group(function () {
        Route::get('/dashboard', [SppController::class, 'dashboard']);
        Route::get('/tagihan', [SppController::class, 'tagihanList']);
        Route::get('/santri/{id}/tunggakan', [SppController::class, 'tunggakanSantri']);
        Route::get('/riwayat', [SppController::class, 'riwayatPembayaran']);
    });

    // Modul SPP - Operasional Kasir & Bayar (Khusus Admin & Bendahara)
    Route::prefix('spp')->middleware('role:admin,bendahara')->group(function () {
        Route::post('/generate-tagihan', [SppController::class, 'generateTagihan']);
        Route::post('/bayar', [SppController::class, 'bayar']);
        Route::post('/batal-bayar', [SppController::class, 'batalBayar']);
        Route::post('/input-cepat', [SppController::class, 'inputCepat']);
    });

    // WhatsApp Gateway & Preview (Admin & Bendahara)
    Route::prefix('wa')->middleware('role:admin,bendahara')->group(function () {
        Route::get('/preview', [WhatsAppController::class, 'preview']);
        Route::post('/send', [WhatsAppController::class, 'send']);
        Route::post('/test', [WhatsAppController::class, 'testKoneksi']);
    });

    // Agenda & Hari Libur
    Route::get('/agenda', [AgendaLiburController::class, 'getAgenda']);
    Route::get('/hari-libur', [AgendaLiburController::class, 'getLibur']);
    Route::middleware('role:admin,sekretaris')->group(function () {
        Route::prefix('agenda')->group(function () {
            Route::post('/', [AgendaLiburController::class, 'addAgenda']);
            Route::put('/{id}', [AgendaLiburController::class, 'updateAgenda']);
            Route::delete('/{id}', [AgendaLiburController::class, 'deleteAgenda']);
        });
        Route::prefix('hari-libur')->group(function () {
            Route::post('/', [AgendaLiburController::class, 'addLibur']);
            Route::delete('/{id}', [AgendaLiburController::class, 'deleteLibur']);
        });
    });

    // Pengaturan Sistem & Manajemen Pengguna (Khusus Admin)
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::prefix('pengaturan')->group(function () {
            Route::get('/', [SettingController::class, 'getSettings']);
            Route::post('/', [SettingController::class, 'updateSettings']);
        });
    });
});
