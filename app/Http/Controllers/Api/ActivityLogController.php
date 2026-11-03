<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Menampilkan daftar log aktivitas sistem dengan filter dan pencarian
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::with('user:id,name,username,role')->latest('id');

        // Filter berdasarkan modul
        if ($request->filled('modul') && $request->input('modul') !== 'all') {
            $modul = $request->input('modul');
            if ($modul === 'master') {
                $query->whereIn('modul', ['santri', 'guru', 'jilid', 'agenda', 'libur']);
            } else {
                $query->where('modul', $modul);
            }
        }

        // Filter berdasarkan aksi
        if ($request->filled('aksi') && $request->input('aksi') !== 'all') {
            $query->where('aksi', $request->input('aksi'));
        }

        // Filter rentang tanggal
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        // Filter pencarian teks
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('deskripsi', 'like', "%{$search}%")
                    ->orWhere('nama_user', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($qu) use ($search) {
                        $qu->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = max(10, min(100, (int) $request->input('per_page', 20)));
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Statistik ringkas log aktivitas sistem
     */
    public function stats(): JsonResponse
    {
        $today = Carbon::today('Asia/Jakarta');

        $totalHariIni = ActivityLog::whereDate('created_at', $today)->count();
        $totalAuth = ActivityLog::where('modul', 'auth')->count();
        $totalPresensi = ActivityLog::where('modul', 'presensi')->count();
        $totalSpp = ActivityLog::where('modul', 'spp')->count();
        $totalMaster = ActivityLog::whereIn('modul', ['santri', 'guru', 'jilid', 'agenda', 'libur'])->count();
        $totalPengaturan = ActivityLog::where('modul', 'pengaturan')->count();
        $totalWhatsApp = ActivityLog::where('modul', 'whatsapp')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_hari_ini' => $totalHariIni,
                'total_auth' => $totalAuth,
                'total_presensi' => $totalPresensi,
                'total_spp' => $totalSpp,
                'total_master' => $totalMaster,
                'total_pengaturan' => $totalPengaturan,
                'total_whatsapp' => $totalWhatsApp,
                'total_keseluruhan' => ActivityLog::count(),
            ],
        ]);
    }

    /**
     * Hapus log aktivitas lama untuk pembersihan data (opsional admin)
     */
    public function clear(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);
        if ($days < 7) {
            return response()->json([
                'success' => false,
                'message' => 'Pembersihan log minimal menyimpan data 7 hari terakhir.',
            ], 422);
        }

        $cutOff = Carbon::now()->subDays($days);
        $deleted = ActivityLog::where('created_at', '<', $cutOff)->delete();

        ActivityLog::record(
            modul: 'pengaturan',
            aksi: 'hapus',
            deskripsi: "Membersihkan {$deleted} rekaman log aktivitas sistem yang berusia lebih dari {$days} hari",
            properties: ['days' => $days, 'deleted_count' => $deleted]
        );

        return response()->json([
            'success' => true,
            'message' => "Berhasil membersihkan {$deleted} log aktivitas lebih dari {$days} hari.",
            'deleted_count' => $deleted,
        ]);
    }
}
