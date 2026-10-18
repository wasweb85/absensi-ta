<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PembayaranSpp;
use App\Models\Siswa;
use App\Models\TagihanSpp;
use App\Services\SppBillingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SppController extends Controller
{
    public function __construct(
        protected SppBillingService $billingService
    ) {}

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
        ]);
    }

    /**
     * Daftar tagihan SPP dengan filter
     */
    public function tagihanList(Request $request): JsonResponse
    {
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
            'bulan' => 'required|integer|between:1,12',
            'tahun' => 'required|integer|min:2020|max:2050',
        ]);

        $bulan = (int) $request->input('bulan');
        $tahun = (int) $request->input('tahun');

        $result = $this->billingService->generateTagihanBulanan($bulan, $tahun);

        return response()->json([
            'success' => true,
            'message' => "Tagihan bulan {$bulan}/{$tahun} berhasil digenerate: {$result['created']} dibuat, {$result['skipped']} sudah ada sebelumnya.",
            'data' => $result,
        ]);
    }

    /**
     * Mengambil daftar tunggakan seorang santri
     */
    public function tunggakanSantri(int $idSiswa): JsonResponse
    {
        $siswa = Siswa::with('kelas')->findOrFail($idSiswa);
        $tunggakan = $this->billingService->hitungTunggakanSantri($idSiswa);

        return response()->json([
            'success' => true,
            'siswa' => $siswa,
            'data' => $tunggakan,
        ]);
    }

    /**
     * Transaksi pembayaran SPP
     */
    public function bayar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_tagihan' => 'required|exists:tagihan_spp,id',
            'jumlah_bayar' => 'required|numeric|min:1',
            'metode_pembayaran' => 'nullable|in:Tunai,Transfer',
            'catatan' => 'nullable|string|max:255',
        ]);

        $petugasId = $request->user()->id;

        $result = $this->billingService->prosesPembayaran($validated, $petugasId);

        return response()->json($result);
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
            'items' => 'required|array|min:1',
            'items.*.id_siswa' => 'required|exists:siswa,id',
            'items.*.nominal' => 'required|numeric|min:1',
            'items.*.metode' => 'nullable|string',
        ]);

        $bulan = (int) $request->input('bulan');
        $tahun = (int) $request->input('tahun');
        $items = $request->input('items');
        $petugasId = $request->user()->id;

        $sukses = 0;
        $kuitansiList = [];

        DB::transaction(function () use ($items, $bulan, $tahun, $petugasId, &$sukses, &$kuitansiList) {
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

                $kuitansiList[] = $res['kuitansi'];
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

        $pembayaran = $query->orderBy('id', 'desc')->paginate(30);

        return response()->json([
            'success' => true,
            'data' => $pembayaran,
        ]);
    }
}
