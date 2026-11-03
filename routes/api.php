<?php

use App\Http\Controllers\Api\AbsensiController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AgendaLiburController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatbotController;
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
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/pengaturan/public', [SettingController::class, 'getPublicSettings']);

// Webhook WhatsApp Gateway (Fonnte): Chatbot, Message Status (Centang ✓✓), dan Device Connect
Route::match(['get', 'post'], '/webhook/whatsapp', [ChatbotController::class, 'webhook'])->middleware('throttle:120,1');
Route::match(['get', 'post'], '/webhook/whatsapp/status', [ChatbotController::class, 'messageStatusWebhook'])->middleware('throttle:120,1');
Route::match(['get', 'post'], '/webhook/whatsapp/connect', [ChatbotController::class, 'connectWebhook'])->middleware('throttle:60,1');

// Endpoint scanner realtime & konfigurasi (bisa diakses oleh scanner kiosk / kamera)
Route::prefix('absensi')->group(function () {
    Route::get('/config', [AbsensiController::class, 'config']);
    Route::post('/scan', [AbsensiController::class, 'scan'])->middleware('throttle:120,1');
    Route::get('/monitoring', [AbsensiController::class, 'monitoring']);
    Route::get('/sheet-data', [AbsensiController::class, 'sheetData']);
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

    // Master Data Siswa: Baca (Admin, Kepala, Sekretaris, Bendahara)
    Route::middleware('role:admin,kepala,sekretaris,bendahara')->group(function () {
        Route::get('/siswa', [SiswaController::class, 'index']);
        Route::get('/siswa/{id}', [SiswaController::class, 'show'])->whereNumber('id');
    });

    // Master Data Siswa: Tulis & Sinkronisasi (Hanya Admin dan Sekretaris)
    Route::middleware('role:admin,sekretaris')->group(function () {
        Route::get('/siswa/spreadsheet-info', [SiswaController::class, 'getSpreadsheetInfo']);
        Route::post('/siswa/sync-spreadsheet', [SiswaController::class, 'syncSpreadsheet']);
        Route::post('/siswa/import', [SiswaController::class, 'importBulk']);
        Route::post('/siswa', [SiswaController::class, 'store']);
        Route::put('/siswa/{id}', [SiswaController::class, 'update'])->whereNumber('id');
        Route::delete('/siswa/{id}', [SiswaController::class, 'destroy'])->whereNumber('id');
    });

    // Modul Absensi (Lihat: Semua Staff & Kepala; Ubah Status: Pengajar, Sekretaris, Admin)
    Route::prefix('absensi')->group(function () {
        Route::post('/status', [AbsensiController::class, 'updateStatus'])->middleware('role:admin,sekretaris,guru');
        Route::get('/matrix', [AbsensiController::class, 'matrix'])->middleware('role:admin,kepala,sekretaris,bendahara,guru');
        Route::get('/riwayat', [AbsensiController::class, 'riwayat'])->middleware('role:admin,kepala,sekretaris,bendahara,guru');
    });

    // Modul SPP - Supervisi & Laporan (Admin, Bendahara, Sekretaris, Kepala)
    Route::prefix('spp')->middleware('role:admin,bendahara,sekretaris,kepala')->group(function () {
        Route::get('/dashboard', [SppController::class, 'dashboard']);
        Route::get('/rekap-tunggakan', [SppController::class, 'rekapTunggakan']);
        Route::get('/tagihan', [SppController::class, 'tagihanList']);
        Route::get('/santri/{id}/tunggakan', [SppController::class, 'tunggakanSantri']);
        Route::get('/riwayat', [SppController::class, 'riwayatPembayaran']);
    });

    // Modul SPP - Operasional Kasir & Bayar (Admin, Bendahara, Sekretaris)
    Route::prefix('spp')->middleware('role:admin,bendahara,sekretaris')->group(function () {
        Route::post('/generate-tagihan', [SppController::class, 'generateTagihan']);
        Route::post('/sinkronkan-semester', [SppController::class, 'sinkronkanSemester']);
        Route::post('/bayar', [SppController::class, 'bayar']);
        Route::post('/bayar-multi', [SppController::class, 'bayarMulti']);
        Route::post('/batal-bayar', [SppController::class, 'batalBayar']);
        Route::post('/input-cepat', [SppController::class, 'inputCepat']);
    });

    // WhatsApp Gateway & Delivery Logs
    Route::prefix('wa')->group(function () {
        // Monitoring Log Pengiriman: Admin, Kepala, Sekretaris, Bendahara
        Route::get('/logs', [WhatsAppController::class, 'logs'])->middleware('role:admin,kepala,sekretaris,bendahara');

        // Preview, Kirim Japri/Pengingat SPP, dan Tes Koneksi: Admin, Sekretaris, Bendahara
        Route::middleware('role:admin,sekretaris,bendahara')->group(function () {
            Route::get('/preview', [WhatsAppController::class, 'preview']);
            Route::post('/send', [WhatsAppController::class, 'send']);
            Route::post('/send-kuitansi-image', [WhatsAppController::class, 'sendKuitansiImage']);
            Route::post('/test', [WhatsAppController::class, 'testKoneksi']);
            Route::post('/logs/sync-status', [WhatsAppController::class, 'syncStatus']);
            Route::post('/logs/{id}/sync-status', [WhatsAppController::class, 'syncSingleStatus']);
            Route::post('/logs/retry-failed', [WhatsAppController::class, 'retryFailed']);
            Route::post('/logs/{id}/retry', [WhatsAppController::class, 'retry']);
            Route::delete('/logs/{id}', [WhatsAppController::class, 'deleteLog']);
        });

        // Broadcast Pengumuman Massal: Khusus Admin dan Sekretaris
        Route::middleware('role:admin,sekretaris')->group(function () {
            Route::post('/broadcast', [WhatsAppController::class, 'broadcast']);
        });
    });

    // Agenda & Hari Libur
    Route::get('/agenda', [AgendaLiburController::class, 'getAgenda']);
    Route::get('/hari-libur', [AgendaLiburController::class, 'getLibur']);
    Route::middleware('role:admin')->group(function () {
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

    // Data Guru & Pengurus: Baca (Admin & Kepala TPQ)
    Route::middleware('role:admin,kepala')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show'])->whereNumber('user');
    });

    // Pengaturan Sistem & Manajemen Pengguna Tulis (Khusus Admin)
    Route::middleware('role:admin')->group(function () {
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update'])->whereNumber('user');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->whereNumber('user');
        Route::prefix('pengaturan')->group(function () {
            Route::get('/', [SettingController::class, 'getSettings']);
            Route::post('/', [SettingController::class, 'updateSettings']);
            Route::post('/test-wa', [WhatsAppController::class, 'testKoneksi']);
            Route::post('/test-telegram', [SettingController::class, 'testTelegramConnection']);
            Route::post('/trigger-agenda-telegram', [SettingController::class, 'triggerAgendaReminder']);
            Route::post('/test-spreadsheet', [SettingController::class, 'testSpreadsheetWebhook']);
            Route::post('/migrate', [SettingController::class, 'runMigrations']);
        });
    });

    // Modul Chatbot WhatsApp (Monitoring Log & Simulator - Khusus Admin)
    Route::middleware('role:admin')->prefix('chatbot')->group(function () {
        Route::post('/simulate', [ChatbotController::class, 'simulate']);
        Route::get('/logs', [ChatbotController::class, 'logs']);
        Route::get('/stats', [ChatbotController::class, 'stats']);
    });

    // Log Aktivitas Sistem (Khusus Administrator)
    Route::middleware('role:admin')->prefix('activity-logs')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index']);
        Route::get('/stats', [ActivityLogController::class, 'stats']);
        Route::delete('/clear', [ActivityLogController::class, 'clear']);
    });
});
