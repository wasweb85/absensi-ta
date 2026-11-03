<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SettingController extends Controller
{
    public function getSettings(): JsonResponse
    {
        $settings = Pengaturan::all()->pluck('value', 'key')->toArray();

        if (empty($settings['nama_instansi']) || in_array(strtoupper(trim($settings['nama_instansi'])), ['E ABSENSI', 'E-ABSENSI', 'SISTEM TERPADU ABSENSI SANTRI', 'SISTEM TERPADU PRESENSI & NOTIFIKASI WHATSAPP SANTRI', 'TPQ & LEMBAGA'])) {
            $settings['nama_instansi'] = "TPQ Nurul Mun'im";
        }

        $settings['nama_bendahara'] = User::getNamaBendahara();
        $settings['telegram_notif_agenda_aktif'] = $settings['telegram_notif_agenda_aktif'] ?? '1';
        $settings['telegram_notif_agenda_jam'] = $settings['telegram_notif_agenda_jam'] ?? '07:00';

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    public function getPublicSettings(): JsonResponse
    {
        $publicKeys = [
            'nama_instansi',
            'tahun_ajaran_aktif',
            'alamat_instansi',
            'kontak_instansi',
            'no_hp_bendahara',
            'logo_lembaga',
        ];

        $settings = Pengaturan::whereIn('key', $publicKeys)->pluck('value', 'key')->toArray();

        $namaInstansi = $settings['nama_instansi'] ?? "TPQ Nurul Mun'im";
        if (empty($namaInstansi) || in_array(strtoupper(trim($namaInstansi)), ['E ABSENSI', 'E-ABSENSI', 'SISTEM TERPADU ABSENSI SANTRI', 'SISTEM TERPADU PRESENSI & NOTIFIKASI WHATSAPP SANTRI', 'TPQ & LEMBAGA'])) {
            $namaInstansi = "TPQ Nurul Mun'im";
        }

        return response()->json([
            'success' => true,
            'data' => [
                'nama_instansi' => $namaInstansi,
                'nama_bendahara' => User::getNamaBendahara(),
                'no_hp_bendahara' => $settings['no_hp_bendahara'] ?? '',
                'tahun_ajaran_aktif' => $settings['tahun_ajaran_aktif'] ?? '2026/2027',
                'alamat_instansi' => $settings['alamat_instansi'] ?? '',
                'kontak_instansi' => $settings['kontak_instansi'] ?? '',
                'logo_lembaga' => ! empty($settings['logo_lembaga']) ? $settings['logo_lembaga'] : '/LOGOTPQ.png',
            ],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $settings = $request->input('settings');
        if (! is_array($settings)) {
            $settings = $request->except(['_token']);
        }

        if (! is_array($settings)) {
            return response()->json([
                'success' => false,
                'message' => 'Format pengaturan tidak valid',
            ], 422);
        }

        // Pastikan kolom 'value' di tabel pengaturan mendukung teks panjang (LONGTEXT) agar muat data gambar Base64
        try {
            DB::statement('ALTER TABLE `pengaturan` MODIFY `value` LONGTEXT NULL');
        } catch (\Throwable $e) {
            // Abaikan jika sudah LONGTEXT atau koneksi tidak memiliki wewenang DDL
        }

        foreach ($settings as $key => $value) {
            Pengaturan::setValue((string) $key, is_null($value) ? '' : (string) $value);
        }

        $keysUpdated = implode(', ', array_keys($settings));
        ActivityLog::record(
            modul: 'pengaturan',
            aksi: 'ubah',
            deskripsi: "Memperbarui pengaturan sistem: {$keysUpdated}",
            properties: [
                'keys' => array_keys($settings),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan berhasil disimpan',
            'data' => Pengaturan::all()->pluck('value', 'key'),
        ]);
    }

    public function testSpreadsheetWebhook(Request $request): JsonResponse
    {
        $url = $request->input('webhook_url') ?: Pengaturan::getValue('spreadsheet_absensi_webhook_url');
        if (empty($url)) {
            return response()->json([
                'success' => false,
                'message' => 'URL Webhook Google Apps Script belum diisi.',
            ], 422);
        }

        try {
            $now = Carbon::now('Asia/Jakarta');
            $response = Http::timeout(8)->post($url, [
                'action' => 'syncAbsensi',
                'tanggal' => $now->format('m/d/Y'),
                'nisn' => 'TEST-01',
                'nama' => 'Santri Uji Coba',
                'kelas' => 'JILID Test',
                'jam_datang' => $now->format('H:i:s'),
                'jam_pulang' => '-',
                'keterangan_waktu' => 'Tepat Waktu',
                'status' => 'Hadir',
            ]);

            $json = $response->json();
            if ($response->successful() && is_array($json) && ($json['success'] ?? false) === true) {
                return response()->json([
                    'success' => true,
                    'message' => 'Berhasil terhubung! Baris uji coba telah berhasil masuk ke spreadsheet.',
                    'response' => $json,
                ]);
            }

            $errorDetail = is_array($json) ? ($json['message'] ?? $json['error'] ?? 'Respons bukan sukses') : 'Respons bukan JSON yang valid';

            return response()->json([
                'success' => false,
                'message' => 'Google Apps Script merespons: "'.$errorDetail.'". Pastikan Anda telah menempelkan kode handleSyncAbsensi dan memilih "Versi Baru" (New Version) saat Deploy.',
                'response' => $json ?: $response->body(),
            ], 422);

            return response()->json([
                'success' => false,
                'message' => 'Google Apps Script merespons status HTTP '.$response->status().'. Pastikan web app dideploy dengan akses "Anyone" (Siapa saja).',
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghubungi Google Apps Script: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Uji koneksi bot Telegram dengan mengirim pesan tes
     */
    public function testTelegramConnection(Request $request, TelegramService $telegramService): JsonResponse
    {
        $botToken = $request->input('bot_token') ?: Pengaturan::getValue('telegram_bot_token');
        $chatId = $request->input('chat_id') ?: Pengaturan::getValue('telegram_chat_id');

        if (empty($botToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Token Bot Telegram belum diisi. Silakan masukkan token bot dari @BotFather.',
            ], 422);
        }

        if (empty($chatId)) {
            return response()->json([
                'success' => false,
                'message' => 'Chat ID Telegram belum diisi. Silakan masukkan Chat ID tujuan.',
            ], 422);
        }

        $result = $telegramService->testConnection($chatId, $botToken);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * Picu pengiriman notifikasi pengingat kegiatan H-1 secara manual
     */
    public function triggerAgendaReminder(Request $request, TelegramService $telegramService): JsonResponse
    {
        $force = $request->boolean('force', true);
        $result = $telegramService->sendAgendaReminderHMinus1($force);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * Menjalankan migrasi database yang tertunda (khusus remote server/cloud)
     */
    public function runMigrations(): JsonResponse
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'message' => 'Migrasi database berhasil dijalankan.',
                'output' => trim($output),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menjalankan migrasi: '.$e->getMessage(),
            ], 500);
        }
    }
}
