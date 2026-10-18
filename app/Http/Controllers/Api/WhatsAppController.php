<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\TagihanSpp;
use App\Services\SppBillingService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function __construct(
        protected WhatsAppService $waService,
        protected SppBillingService $billingService
    ) {}

    /**
     * Preview pesan WhatsApp dan buat link wa.me
     */
    public function preview(Request $request): JsonResponse
    {
        $type = $request->input('type'); // 'japri', 'grup', 'wali_jilid'
        $bulan = (int) $request->input('bulan', Carbon::now('Asia/Jakarta')->month);
        $tahun = (int) $request->input('tahun', Carbon::now('Asia/Jakarta')->year);
        $namaBulanTahun = (SppBillingService::NAMA_BULAN[$bulan] ?? "Bulan {$bulan}")." {$tahun}";

        if ($type === 'japri') {
            $idSiswa = (int) $request->input('id_siswa');
            $siswa = Siswa::with('kelas')->findOrFail($idSiswa);
            $tunggakan = $this->billingService->hitungTunggakanSantri($idSiswa);

            if ($tunggakan['total_bulan'] > 1) {
                $daftarStr = implode(', ', array_column($tunggakan['daftar_tunggakan'], 'label'));
                $message = $this->waService->buildPesanJapriAkumulasi(
                    $siswa->nama_lengkap,
                    $tunggakan['total_tunggakan'],
                    $tunggakan['total_bulan'],
                    $daftarStr
                );
            } else {
                $nominal = $tunggakan['total_tunggakan'] > 0 ? $tunggakan['total_tunggakan'] : ($siswa->kelas?->nominal_spp ?: 0);
                $message = $this->waService->buildPesanJapriOrtu($siswa->nama_lengkap, $nominal, $namaBulanTahun);
            }

            $waMeLink = $siswa->no_hp_ortu ? $this->waService->generateWaMeLink($siswa->no_hp_ortu, $message) : null;

            return response()->json([
                'success' => true,
                'target_phone' => $siswa->no_hp_ortu,
                'message' => $message,
                'wa_me_link' => $waMeLink,
            ]);
        }

        if ($type === 'grup') {
            $idKelas = (int) $request->input('id_kelas');
            $kelas = Kelas::findOrFail($idKelas);
            $message = $this->waService->buildPesanGrupJilid($kelas->nama_kelas, $kelas->nominal_spp, $namaBulanTahun);

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        if ($type === 'wali_jilid') {
            $idKelas = (int) $request->input('id_kelas');
            $kelas = Kelas::with(['siswa' => function ($q) {
                $q->where('status', 'aktif');
            }])->findOrFail($idKelas);

            $totalSantri = $kelas->siswa->count();
            $menunggakList = [];

            foreach ($kelas->siswa as $santri) {
                $tagihanUnpaid = TagihanSpp::where('id_siswa', $santri->id)
                    ->where('bulan', $bulan)
                    ->where('tahun', $tahun)
                    ->whereIn('status', ['belum_lunas', 'kurang_bayar'])
                    ->first();

                if ($tagihanUnpaid) {
                    $menunggakList[] = [
                        'nama' => $santri->nama_lengkap,
                        'nominal' => (float) $tagihanUnpaid->nominal_tagihan,
                    ];
                }
            }

            $message = $this->waService->buildPesanWaliJilid(
                $kelas->nama_kelas,
                $kelas->wali_kelas ?: 'Wali Kelas',
                $totalSantri,
                $menunggakList,
                $namaBulanTahun
            );

            $waMeLink = $kelas->no_hp_wali ? $this->waService->generateWaMeLink($kelas->no_hp_wali, $message) : null;

            return response()->json([
                'success' => true,
                'target_phone' => $kelas->no_hp_wali,
                'message' => $message,
                'wa_me_link' => $waMeLink,
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Tipe pengingat tidak valid.'], 400);
    }

    /**
     * Kirim pesan langsung via Fonnte Gateway API
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $phone = $request->input('phone');
        $message = $request->input('message');

        $result = $this->waService->sendViaFonnte($phone, $message);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Cek token Fonnte
     */
    public function testKoneksi(Request $request): JsonResponse
    {
        $token = $request->input('token') ?: Pengaturan::getValue('fonnte_token');

        if (empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Token Fonnte kosong.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token terkonfigurasi. Silakan uji coba kirim ke nomor ponsel.',
        ]);
    }
}
