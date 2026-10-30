<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengaturan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SettingController extends Controller
{
    public function getSettings(): JsonResponse
    {
        $settings = Pengaturan::all()->pluck('value', 'key');

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
            'logo_lembaga',
        ];

        $settings = Pengaturan::whereIn('key', $publicKeys)->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data' => [
                'nama_instansi' => $settings['nama_instansi'] ?? 'TPQ & Madrasah Diniyah',
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

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Berhasil terhubung ke Google Apps Script! Baris uji coba telah dikirim ke spreadsheet.',
                    'response' => $response->json() ?: $response->body(),
                ]);
            }

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
}
