<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScanAbsensiRequest;
use App\Models\Absensi;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\User;
use App\Services\AbsensiScannerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use function Illuminate\Support\defer;

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

        $user = $request->user();
        $userGenderBinaan = ($user && array_key_exists('gender_binaan', $user->getAttributes()))
            ? $user->gender_binaan
            : null;

        $jenisKelamin = $request->input('jenis_kelamin');
        if (! $request->filled('jenis_kelamin') && $user && $user->role === 'guru' && in_array($userGenderBinaan, ['L', 'P'], true)) {
            $jenisKelamin = $userGenderBinaan;
        }

        if (! in_array($jenisKelamin, ['L', 'P'], true)) {
            $jenisKelamin = null;
        }

        $data = $this->scannerService->getMonitoring($idKelas, $tanggal, $jenisKelamin);

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

        $userGenderBinaan = ($user && array_key_exists('gender_binaan', $user->getAttributes()))
            ? $user->gender_binaan
            : null;

        $jenisKelamin = $request->input('jenis_kelamin');
        if (! $request->filled('jenis_kelamin') && $user && $user->role === 'guru' && in_array($userGenderBinaan, ['L', 'P'], true)) {
            $jenisKelamin = $userGenderBinaan;
        }

        if (! in_array($jenisKelamin, ['L', 'P'], true)) {
            $jenisKelamin = null;
        }

        $matrix = $this->scannerService->getMatrixReport($bulan, $tahun, $idKelas, $jenisKelamin);

        $waliInfo = null;
        if ($idKelas && $idKelas !== -1) {
            $teachers = User::where('id_kelas', $idKelas)->get();
            $waliInfo = [
                'wali_putra' => $teachers->firstWhere('gender_binaan', 'L')?->name,
                'wali_putri' => $teachers->firstWhere('gender_binaan', 'P')?->name,
                'wali_umum' => $teachers->firstWhere('gender_binaan', 'all')?->name,
            ];
        }

        $matrix['wali_info'] = $waliInfo;

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

        // Sinkronkan perubahan status manual ke Google Spreadsheet secara deferred di background
        $siswa = Siswa::with('kelas')->find($validated['id_siswa']);
        if ($siswa) {
            $currentTime = Carbon::now('Asia/Jakarta')->format('H:i:s');
            $status = $validated['status'];
            $waktuMasuk = $absensi->waktu_masuk ?: $currentTime;
            $tanggal = $validated['tanggal'];
            $waktuPulang = $absensi->waktu_pulang ?: '-';
            $keterangan = ($validated['keterangan'] ?? null) ?: ($status === 'Hadir' ? 'Tepat Waktu' : '-');

            defer(function () use ($siswa, $status, $waktuMasuk, $tanggal, $waktuPulang, $keterangan) {
                try {
                    $this->scannerService->syncToGoogleSheet(
                        $siswa,
                        $status,
                        $waktuMasuk,
                        $tanggal,
                        $waktuPulang,
                        $keterangan
                    );
                } catch (\Throwable $e) {
                    Log::warning('Gagal sinkronisasi Google Sheet manual update: '.$e->getMessage());
                }
            });
        }

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

    /**
     * Endpoint API penyedia data kehadiran harian untuk ditarik oleh Google Apps Script
     */
    public function sheetData(Request $request): JsonResponse
    {
        $tanggal = $request->input('tanggal', Carbon::now('Asia/Jakarta')->format('Y-m-d'));
        $absensi = Absensi::with(['siswa.kelas'])
            ->where('tanggal', $tanggal)
            ->get();

        $rows = $absensi->map(function ($a) {
            return [
                'tanggal' => Carbon::parse($a->tanggal)->format('m/d/Y'),
                'nisn' => (string) ($a->siswa?->nisn ?? ''),
                'nama' => (string) ($a->siswa?->nama_lengkap ?? ''),
                'kelas' => (string) ($a->siswa?->kelas?->nama_kelas ?? '-'),
                'jam_datang' => $a->waktu_masuk ? substr((string) $a->waktu_masuk, 0, 8) : '-',
                'jam_pulang' => $a->waktu_pulang ? substr((string) $a->waktu_pulang, 0, 8) : '-',
                'keterangan_waktu' => $a->keterangan ?: ($a->status === 'Terlambat' ? 'Terlambat' : ($a->status === 'Hadir' ? 'Tepat Waktu' : '-')),
                'status' => $a->status,
            ];
        });

        return response()->json([
            'success' => true,
            'tanggal' => $tanggal,
            'total' => $rows->count(),
            'data' => $rows,
        ]);
    }
}
