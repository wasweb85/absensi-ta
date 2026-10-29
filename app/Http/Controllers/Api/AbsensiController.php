<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScanAbsensiRequest;
use App\Models\Absensi;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Services\AbsensiScannerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbsensiController extends Controller
{
    public function __construct(
        protected AbsensiScannerService $scannerService
    ) {}

    /**
     * Endpoint pemindaian absensi (QR Kamera / Barcode Scanner / RFID)
     */
    public function scan(ScanAbsensiRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $nisn = $validated['nisn'];
        $metode = $validated['metode_scan'] ?? 'qr_kamera';
        $restrictKelas = $request->boolean('restrict_kelas', false);

        // Jika user login di web, gunakan role dan kelas terdaftar di server
        $user = $request->user();
        if ($user) {
            $role = $user->role;
            $kelas = ($restrictKelas && $user->role === 'guru' && $user->kelas)
                ? $user->kelas->nama_kelas
                : ($request->input('scanner_kelas') ?: null);
        } else {
            $role = $request->input('scanner_role', 'admin');
            $kelas = $restrictKelas ? $request->input('scanner_kelas') : null;
        }

        $result = $this->scannerService->scan($nisn, $role, $kelas, $metode, $restrictKelas);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Endpoint monitoring realtime kehadiran hari ini
     */
    public function monitoring(Request $request): JsonResponse
    {
        $idKelas = $request->input('id_kelas') ? (int) $request->input('id_kelas') : null;
        $tanggal = $request->input('tanggal');

        $data = $this->scannerService->getMonitoring($idKelas, $tanggal);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Endpoint laporan matrix kehadiran 1 - 31 hari
     */
    public function matrix(Request $request): JsonResponse
    {
        $bulan = (int) $request->input('bulan', Carbon::now('Asia/Jakarta')->month);
        $tahun = (int) $request->input('tahun', Carbon::now('Asia/Jakarta')->year);
        $idKelas = $request->input('id_kelas') ? (int) $request->input('id_kelas') : null;

        // Selain admin, kepala, dan sekretaris, wajib dibatasi hanya ke kelas/jilid yang diampunya
        $user = $request->user();
        if ($user && ! in_array($user->role, ['admin', 'kepala', 'sekretaris'], true)) {
            $idKelas = $user->id_kelas ?: -1;
        }

        $matrix = $this->scannerService->getMatrixReport($bulan, $tahun, $idKelas);

        return response()->json([
            'success' => true,
            'data' => $matrix,
        ]);
    }

    /**
     * Update manual status absensi siswa oleh Guru/Admin (Hadir, Terlambat, Sakit, Izin, Alpa)
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_siswa' => 'required|exists:siswa,id',
            'tanggal' => 'required|date',
            'status' => 'required|in:Hadir,Terlambat,Sakit,Izin,Alpa',
            'keterangan' => 'nullable|string|max:255',
        ]);

        $absensi = Absensi::updateOrCreate(
            [
                'id_siswa' => $validated['id_siswa'],
                'tanggal' => $validated['tanggal'],
            ],
            [
                'status' => $validated['status'],
                'metode_scan' => 'manual',
                'keterangan' => $validated['keterangan'] ?? null,
                'waktu_masuk' => ($validated['status'] === 'Hadir' || $validated['status'] === 'Terlambat')
                    ? Carbon::now('Asia/Jakarta')->format('H:i:s')
                    : null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Status absensi berhasil diperbarui',
            'data' => $absensi->load('siswa'),
        ]);
    }

    /**
     * Mengambil riwayat absensi dengan berbagai filter
     */
    public function riwayat(Request $request): JsonResponse
    {
        $query = Absensi::with(['siswa.kelas']);

        if ($request->filled('tanggal_mulai') && $request->filled('tanggal_selesai')) {
            $query->whereBetween('tanggal', [$request->input('tanggal_mulai'), $request->input('tanggal_selesai')]);
        } elseif ($request->filled('tanggal')) {
            $query->where('tanggal', $request->input('tanggal'));
        }

        if ($request->filled('id_kelas')) {
            $idKelas = $request->input('id_kelas');
            $query->whereHas('siswa', function ($q) use ($idKelas) {
                $q->where('id_kelas', $idKelas);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('nisn')) {
            $nisn = $request->input('nisn');
            $query->whereHas('siswa', function ($q) use ($nisn) {
                $q->where('nisn', $nisn);
            });
        }

        $riwayat = $query->orderBy('tanggal', 'desc')->orderBy('waktu_masuk', 'desc')->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $riwayat,
        ]);
    }

    /**
     * Konfigurasi jadwal dan waktu server realtime
     */
    public function config(): JsonResponse
    {
        $now = Carbon::now('Asia/Jakarta');

        return response()->json([
            'success' => true,
            'server_time' => $now->format('H:i:s'),
            'server_time_short' => $now->format('H:i'),
            'server_date' => $now->format('Y-m-d'),
            'server_date_formatted' => $now->translatedFormat('l, d F Y'),
            'jam_masuk' => Pengaturan::getValue('jam_masuk', '07:00'),
            'jam_terlambat' => Pengaturan::getValue('jam_terlambat', '07:15'),
            'jam_pulang' => Pengaturan::getValue('jam_pulang', '12:00'),
            'nama_instansi' => Pengaturan::getValue('nama_instansi', 'Sekolah / Lembaga'),
        ]);
    }
}
