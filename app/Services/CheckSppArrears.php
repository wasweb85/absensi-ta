<?php

namespace App\Services;

use App\Models\Siswa;
use App\Models\TagihanSpp;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CheckSppArrears
{
    /** @var array<int, string> */
    protected array $bulanMap = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * Periksa data tunggakan SPP dan riwayat pembayaran 1 santri secara langsung dari database.
     *
     * @param  array<string, mixed>|null  $period
     * @return array{
     *     text: string,
     *     has_tunggakan: bool,
     *     total_bulan: int,
     *     total_tunggakan: float,
     *     daftar_tunggakan: array<int, array<string, mixed>>,
     *     pembayaran_terakhir: array<string, mixed>|null
     * }
     */
    public function inspectStudentArrears(Siswa $siswa, ?array $period = null, string $originalMessage = ''): array
    {
        SppBillingService::ensureSppSchemaExists();

        $sppService = app(SppBillingService::class);
        $paymentChecker = app(CheckSppPayment::class);

        $now = Carbon::now('Asia/Jakarta');
        $cutOffBulan = (int) $now->month;
        $cutOffTahun = (int) $now->year;
        $namaBulanIni = $this->bulanMap[$cutOffBulan] ?? 'Bulan Ini';
        $namaKelas = $siswa->kelas?->nama_kelas ?: 'Jilid';

        if (! TagihanSpp::where('id_siswa', $siswa->id)->exists()) {
            $toTahun = max(2027, $cutOffTahun);
            $toBulan = ($toTahun === 2027) ? max(6, $cutOffBulan) : $cutOffBulan;
            $sppService->sinkronkanTagihanSemester(7, 2026, $toBulan, $toTahun);
        }

        $rekap = $sppService->hitungTunggakanSantri($siswa->id, $cutOffBulan, $cutOffTahun);
        $totalBulan = (int) ($rekap['total_bulan'] ?? 0);
        $totalTunggakan = (float) ($rekap['total_tunggakan'] ?? 0);
        $daftarTunggakan = $rekap['daftar_tunggakan'] ?? [];
        $hasTunggakan = ($totalBulan > 0 && $totalTunggakan > 0);

        $statusBulanSpesifik = '';
        if ($period && ! empty($period['is_previous_month'])) {
            $monthInfo = $paymentChecker->inspectMonthPayment($siswa, (int) $period['bulan'], (int) $period['tahun']);
            if ($monthInfo['tercatat_tagihan']) {
                if ($monthInfo['status'] === 'lunas') {
                    $statusBulanSpesifik = "• SPP {$period['label_periode']}: ✅ *LUNAS*\n";
                } else {
                    $statusBulanSpesifik = "• SPP {$period['label_periode']}: ⚠️ *BELUM LUNAS* (Sisa {$monthInfo['sisa_tagihan_rp']})\n";
                }
            }
        }

        $lastPayment = $paymentChecker->inspectLastPayment($siswa);
        $includeLastPaymentSection = $paymentChecker->isAskingLastPaymentOrConfirmation($originalMessage);
        $lastPaymentBlock = $includeLastPaymentSection
            ? "\n\n".$paymentChecker->formatLastPaymentSummary($siswa, $originalMessage)
            : '';

        if (! $hasTunggakan) {
            $text = "💳 *SPP ananda {$siswa->nama_lengkap}* ({$namaKelas}):\n"
                .$statusBulanSpesifik
                ."• Status: ✅ *LUNAS* (Tidak ada tunggakan s/d {$namaBulanIni} {$cutOffTahun})\n"
                .'• Total Tunggakan: *Rp 0*'
                .$lastPaymentBlock;

            return [
                'text' => $text,
                'has_tunggakan' => false,
                'total_bulan' => 0,
                'total_tunggakan' => 0.0,
                'daftar_tunggakan' => [],
                'pembayaran_terakhir' => $lastPayment,
            ];
        }

        $totalRp = 'Rp '.number_format($totalTunggakan, 0, ',', '.');
        $rincianLines = [];
        foreach ($daftarTunggakan as $item) {
            $sisaRp = 'Rp '.number_format((float) $item['sisa'], 0, ',', '.');
            if ((float) ($item['sudah_dibayar'] ?? 0) > 0) {
                $rincianLines[] = "• {$item['label']}: Sisa {$sisaRp} (Kurang Bayar)";
            } else {
                $rincianLines[] = "• {$item['label']}: {$sisaRp}";
            }
        }

        $text = "💳 *SPP ananda {$siswa->nama_lengkap}* ({$namaKelas}):\n"
            .$statusBulanSpesifik
            ."• Status Keseluruhan: ⚠️ *Menunggak {$totalBulan} bulan*\n"
            ."• Total Tunggakan: *{$totalRp}*\n\n"
            ."*Rincian Bulan Belum Lunas:*\n"
            .implode("\n", $rincianLines)
            .$lastPaymentBlock;

        return [
            'text' => $text,
            'has_tunggakan' => true,
            'total_bulan' => $totalBulan,
            'total_tunggakan' => $totalTunggakan,
            'daftar_tunggakan' => $daftarTunggakan,
            'pembayaran_terakhir' => $lastPayment,
        ];
    }

    /**
     * Susun payload terstruktur untuk Gemini Function Calling (`check_spp_arrears`).
     *
     * @param  Collection<int, Siswa>  $candidates
     * @param  array<string, mixed>  $period
     * @return array<string, mixed>
     */
    public function buildToolPayload(
        ?Siswa $selectedSiswa,
        Collection $candidates,
        array $period,
        string $originalMessage = ''
    ): array {
        $chatbotService = app(ChatbotService::class);
        $paymentChecker = app(CheckSppPayment::class);

        if ($selectedSiswa) {
            $arrears = $this->inspectStudentArrears($selectedSiswa, $period, $originalMessage);

            return [
                'status' => 'ok',
                'santri' => [
                    'id' => $selectedSiswa->id,
                    'nisn' => $selectedSiswa->nisn,
                    'nama_lengkap' => $selectedSiswa->nama_lengkap,
                    'kelas' => $selectedSiswa->kelas?->nama_kelas,
                ],
                'has_tunggakan' => $arrears['has_tunggakan'],
                'total_bulan_tunggakan' => $arrears['total_bulan'],
                'total_nominal_tunggakan' => $arrears['total_tunggakan'],
                'daftar_bulan_belum_lunas' => $arrears['daftar_tunggakan'],
                'pembayaran_terakhir' => $arrears['pembayaran_terakhir'],
                'bendahara' => $paymentChecker->getBendaharaInfo(),
                'data_spp' => $chatbotService->handleSppNoticeQuery($selectedSiswa, null, $originalMessage),
            ];
        }

        $details = [];
        foreach ($candidates as $cand) {
            $arrears = $this->inspectStudentArrears($cand, $period, $originalMessage);
            $details[] = [
                'nama_lengkap' => $cand->nama_lengkap,
                'has_tunggakan' => $arrears['has_tunggakan'],
                'total_bulan_tunggakan' => $arrears['total_bulan'],
                'total_nominal_tunggakan' => $arrears['total_tunggakan'],
                'daftar_bulan_belum_lunas' => $arrears['daftar_tunggakan'],
            ];
        }

        return [
            'status' => 'ok',
            'santri' => 'semua_anak_wali',
            'detail_tunggakan' => $details,
            'bendahara' => $paymentChecker->getBendaharaInfo(),
            'data_spp' => $chatbotService->handleSppNoticeQuery($candidates->first(), $candidates, $originalMessage),
        ];
    }
}
