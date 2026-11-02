<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BayarSppRequest;
use App\Models\PembayaranSpp;
use App\Models\Siswa;
use App\Models\TagihanSpp;
use App\Models\User;
use App\Services\SppBillingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SppController extends Controller
{
    public function __construct(
        protected SppBillingService $billingService
    ) {
        SppBillingService::ensureSppSchemaExists();
    }

    /**
     * Dashboard statistik keuangan SPP
     */
    public function dashboard(Request $request): JsonResponse
    {
        $bulan = (int) $request->input('bulan', Carbon::now('Asia/Jakarta')->month);
        $tahun = (int) $request->input('tahun', Carbon::now('Asia/Jakarta')->year);

        $data = $this->billingService->getDashboardFinansial($bulan, $tahun);

        return response()->json([
            'success' => true,
            'data' => $data,
            'nama_bendahara' => User::getNamaBendahara(),
        ]);
    }

    /**
     * Rekapitulasi daftar santri yang menunggak beserta rincian bulan dan total nominal
     */
    public function rekapTunggakan(Request $request): JsonResponse
    {
        $idKelas = $request->input('id_kelas') ? (int) $request->input('id_kelas') : null;
        $bulan = (int) $request->input('bulan', Carbon::now('Asia/Jakarta')->month);
        $tahun = (int) $request->input('tahun', Carbon::now('Asia/Jakarta')->year);
        $search = $request->input('search');

        $data = $this->billingService->getRekapTunggakanSantri($idKelas, $bulan, $tahun, $search);

        return response()->json([
            'success' => true,
            'data' => $data,
            'nama_bendahara' => User::getNamaBendahara(),
        ]);
    }

    /**
     * Daftar tagihan SPP dengan filter
     */
    public function tagihanList(Request $request): JsonResponse
    {
        $bulan = $request->filled('bulan') ? (int) $request->input('bulan') : null;
        $tahun = $request->filled('tahun') ? (int) $request->input('tahun') : null;

        if ($bulan && $tahun) {
            $exists = TagihanSpp::where('bulan', $bulan)->where('tahun', $tahun)->exists();
            if (! $exists) {
                $toTahun = max(2027, $tahun);
                $toBulan = ($toTahun === 2027) ? max(6, $bulan) : $bulan;
                $this->billingService->sinkronkanTagihanSemester(7, 2026, $toBulan, $toTahun);
            }
        } elseif (TagihanSpp::count() === 0) {
            $this->billingService->sinkronkanTagihanSemester(7, 2026, 6, 2027);
        }

        $query = TagihanSpp::with(['siswa.kelas']);

        if ($request->filled('bulan')) {
            $query->where('bulan', (int) $request->input('bulan'));
        }

        if ($request->filled('tahun')) {
            $query->where('tahun', (int) $request->input('tahun'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('id_kelas')) {
            $idKelas = $request->input('id_kelas');
            $query->whereHas('siswa', function ($q) use ($idKelas) {
                $q->where('id_kelas', $idKelas);
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('siswa', function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        $tagihan = $query->orderBy('tahun', 'desc')
            ->orderBy('bulan', 'desc')
            ->orderBy('id_siswa', 'asc')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $tagihan,
        ]);
    }

    /**
     * Generate tagihan massal untuk bulan dan tahun yang dipilih
     */
    public function generateTagihan(Request $request): JsonResponse
    {
        $request->validate([
            'bulan' => 'nullable|integer|between:1,12',
            'tahun' => 'required|integer|min:2020|max:2050',
            'mode' => 'nullable|string|in:single,semester_1,semester_2,full_year',
        ]);

        $mode = $request->input('mode', 'single');
        $tahun = (int) $request->input('tahun');

        $periods = match ($mode) {
            'semester_1' => array_map(fn ($m) => ['bulan' => $m, 'tahun' => $tahun], [7, 8, 9, 10, 11, 12]),
            'semester_2' => array_map(fn ($m) => ['bulan' => $m, 'tahun' => ($tahun === 2026 ? 2027 : $tahun)], [1, 2, 3, 4, 5, 6]),
            'full_year' => array_merge(
                array_map(fn ($m) => ['bulan' => $m, 'tahun' => $tahun], [7, 8, 9, 10, 11, 12]),
                array_map(fn ($m) => ['bulan' => $m, 'tahun' => ($tahun === 2026 ? 2027 : $tahun + 1)], [1, 2, 3, 4, 5, 6])
            ),
            default => [['bulan' => (int) $request->input('bulan', Carbon::now('Asia/Jakarta')->month), 'tahun' => $tahun]],
        };

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($periods as $p) {
            $result = $this->billingService->generateTagihanBulanan($p['bulan'], $p['tahun']);
            $totalCreated += $result['created'];
            $totalSkipped += $result['skipped'];
        }

        return response()->json([
            'success' => true,
            'message' => "Proses generate selesai: {$totalCreated} tagihan baru dibuat, {$totalSkipped} sudah ada sebelumnya.",
            'data' => [
                'created' => $totalCreated,
                'skipped' => $totalSkipped,
            ],
        ]);
    }

    /**
     * Sinkronkan tagihan SPP untuk tahun ajaran aktif (Juli 2026 s/d Juni 2027)
     */
    public function sinkronkanSemester(Request $request): JsonResponse
    {
        $fromBulan = (int) $request->input('from_bulan', 7);
        $fromTahun = (int) $request->input('from_tahun', 2026);
        $toBulan = (int) $request->input('to_bulan', 6);
        $toTahun = (int) $request->input('to_tahun', 2027);

        $result = $this->billingService->sinkronkanTagihanSemester($fromBulan, $fromTahun, $toBulan, $toTahun);

        return response()->json([
            'success' => true,
            'message' => "Sinkronisasi tagihan berhasil: {$result['created']} tagihan baru dibuat untuk {$result['total_santri']} santri aktif.",
            'data' => $result,
        ]);
    }

    /**
     * Mengambil daftar tunggakan seorang santri
     */
    public function tunggakanSantri(int $idSiswa, Request $request): JsonResponse
    {
        $siswa = Siswa::with('kelas')->findOrFail($idSiswa);
        $cutOffBulan = $request->filled('bulan') ? (int) $request->input('bulan') : 6;
        $cutOffTahun = $request->filled('tahun') ? (int) $request->input('tahun') : 2027;

        $tunggakan = $this->billingService->hitungTunggakanSantri($idSiswa, $cutOffBulan, $cutOffTahun);

        return response()->json([
            'success' => true,
            'siswa' => $siswa,
            'data' => $tunggakan,
        ]);
    }

    /**
     * Transaksi pembayaran SPP
     */
    public function bayar(BayarSppRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $petugasId = $request->user()->id;

        $result = $this->billingService->prosesPembayaran($validated, $petugasId);
        if (isset($result['kuitansi'])) {
            $result['kuitansi']['petugas'] = $request->user()->name;
        }

        return response()->json($result);
    }

    /**
     * Transaksi pembayaran multi-bulan untuk satu santri sekaligus
     */
    public function bayarMulti(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_siswa' => 'required|exists:siswa,id',
            'tagihan_ids' => 'required|array|min:1',
            'tagihan_ids.*' => 'required|exists:tagihan_spp,id',
            'metode_pembayaran' => 'required|string|in:Tunai,Transfer',
            'catatan' => 'nullable|string|max:255',
        ]);

        $petugasId = $request->user()->id;

        $result = $this->billingService->bayarMultiBulan(
            (int) $validated['id_siswa'],
            $validated['tagihan_ids'],
            $validated['metode_pembayaran'],
            $validated['catatan'] ?? null,
            $petugasId
        );

        if (isset($result['kuitansi'])) {
            $result['kuitansi']['petugas'] = $request->user()->name;
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Batalkan pembayaran dengan audit trail
     */
    public function batalBayar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_pembayaran' => 'required|exists:pembayaran_spp,id',
            'alasan' => 'required|string|min:5|max:255',
        ]);

        $namaPetugas = $request->user()->name;

        $result = $this->billingService->batalkanPembayaran(
            $validated['id_pembayaran'],
            $validated['alasan'],
            $namaPetugas
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Input Cepat SPP (Batch pembayaran untuk beberapa santri sekaligus)
     */
    public function inputCepat(Request $request): JsonResponse
    {
        $request->validate([
            'bulan' => 'required|integer|between:1,12',
            'tahun' => 'required|integer',
            'id_kelas' => 'nullable|exists:kelas,id',
            'items' => 'required|array|min:1',
            'items.*.id_siswa' => 'required|exists:siswa,id',
            'items.*.nominal' => 'required|numeric|min:1',
            'items.*.metode' => 'nullable|string',
        ]);

        $bulan = (int) $request->input('bulan');
        $tahun = (int) $request->input('tahun');
        $idKelas = $request->input('id_kelas');
        $items = $request->input('items');
        $petugasId = $request->user()->id;
        $namaPetugas = $request->user()->name;

        $sukses = 0;
        $kuitansiList = [];

        DB::transaction(function () use ($items, $bulan, $tahun, $idKelas, $petugasId, $namaPetugas, &$sukses, &$kuitansiList) {
            if ($idKelas) {
                $allSiswaKelas = Siswa::where('id_kelas', $idKelas)->where('status', 'aktif')->get();
                foreach ($allSiswaKelas as $s) {
                    $this->billingService->ensureTagihan($s->id, $bulan, $tahun);
                }
            }

            foreach ($items as $item) {
                $idSiswa = (int) $item['id_siswa'];
                $nominal = (float) $item['nominal'];
                $metode = $item['metode'] ?? 'Tunai';

                // Pastikan tagihan ada
                $tagihan = $this->billingService->ensureTagihan($idSiswa, $bulan, $tahun);

                // Bayar
                $res = $this->billingService->prosesPembayaran([
                    'id_tagihan' => $tagihan->id,
                    'jumlah_bayar' => $nominal,
                    'metode_pembayaran' => $metode,
                    'catatan' => 'Input Cepat SPP',
                ], $petugasId);

                $kw = $res['kuitansi'];
                $kw['petugas'] = $namaPetugas;
                $kuitansiList[] = $kw;
                $sukses++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Berhasil memproses pembayaran untuk {$sukses} santri.",
            'kuitansi_list' => $kuitansiList,
        ]);
    }

    /**
     * Riwayat transaksi pembayaran SPP
     */
    public function riwayatPembayaran(Request $request): JsonResponse
    {
        $query = PembayaranSpp::with(['tagihan', 'siswa.kelas', 'petugas']);

        if ($request->filled('tanggal_mulai') && $request->filled('tanggal_selesai')) {
            $query->whereBetween('tanggal_bayar', [
                $request->input('tanggal_mulai').' 00:00:00',
                $request->input('tanggal_selesai').' 23:59:59',
            ]);
        }

        if ($request->filled('status_transaksi')) {
            $query->where('status_transaksi', $request->input('status_transaksi'));
        }

        if ($request->filled('id_kelas')) {
            $idKelas = $request->input('id_kelas');
            $query->whereHas('siswa', function ($q) use ($idKelas) {
                $q->where('id_kelas', $idKelas);
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nomor_kuitansi', 'like', "%{$search}%")
                    ->orWhereHas('siswa', function ($sq) use ($search) {
                        $sq->where('nama_lengkap', 'like', "%{$search}%")
                            ->orWhere('nisn', 'like', "%{$search}%");
                    });
            });
        }

        $pembayaran = $query->orderBy('id', 'desc')->paginate(30);

        return response()->json([
            'success' => true,
            'data' => $pembayaran,
            'nama_bendahara' => User::getNamaBendahara(),
        ]);
    }
}
