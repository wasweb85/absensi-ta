<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengaturan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                'logo_lembaga' => $settings['logo_lembaga'] ?? '',
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
}
