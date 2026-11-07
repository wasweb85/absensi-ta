<?php

namespace App\Services;

use App\Models\PembayaranSpp;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\TagihanSpp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CheckSppPayment
{
    /** @var array<int, string> */
    protected array $bulanMap = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * Ambil transaksi pembayaran SPP valid terakhir milik santri dari tabel pembayaran_spp.
     *
     * @return array<string, mixed>|null
     */
    public function inspectLastPayment(Siswa $siswa): ?array
    {
        SppBillingService::ensureSppSchemaExists();

        $lastPayment = PembayaranSpp::with('tagihan')
            ->where('id_siswa', $siswa->id)
            ->where('status_transaksi', 'valid')
            ->orderByDesc('tanggal_bayar')
            ->orderByDesc('id')
            ->first();

        if (! $lastPayment) {
            return null;
        }

        $tglFormatted = $lastPayment->tanggal_bayar
            ? Carbon::parse($lastPayment->tanggal_bayar)->timezone('Asia/Jakarta')->locale('id')->isoFormat('D MMMM Y')
            : '-';

        $jumlah = (float) $lastPayment->jumlah_bayar;
        $jumlahRp = 'Rp '.number_format($jumlah, 0, ',', '.');

        $periodeTagihan = '-';
        if ($lastPayment->tagihan) {
            $bln = (int) $lastPayment->tagihan->bulan;
            $thn = (int) $lastPayment->tagihan->tahun;
            $namaBln = $this->bulanMap[$bln] ?? "Bulan {$bln}";
            $periodeTagihan = "{$namaBln} {$thn}";
        }

        return [
            'id' => $lastPayment->id,
            'nomor_kuitansi' => $lastPayment->nomor_kuitansi,
            'tanggal_bayar' => $lastPayment->tanggal_bayar?->format('Y-m-d H:i:s'),
            'tanggal_bayar_formatted' => $tglFormatted,
            'jumlah_bayar' => $jumlah,
            'jumlah_bayar_rp' => $jumlahRp,
            'metode_pembayaran' => $lastPayment->metode_pembayaran ?: 'Tunai',
            'periode_tagihan' => $periodeTagihan,
        ];
    }

    /**
     * Periksa status pembayaran SPP santri pada bulan dan tahun tertentu dari tabel tagihan_spp & pembayaran_spp.
     *
     * @return array<string, mixed>
     */
    public function inspectMonthPayment(Siswa $siswa, int $bulan, int $tahun): array
    {
        SppBillingService::ensureSppSchemaExists();

        $namaBulan = $this->bulanMap[$bulan] ?? "Bulan {$bulan}";
        $labelPeriode = "Bulan {$namaBulan} {$tahun}";

        $tagihan = TagihanSpp::with(['pembayaran' => fn ($q) => $q->where('status_transaksi', 'valid')])
            ->where('id_siswa', $siswa->id)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->first();

        if (! $tagihan) {
            return [
                'tercatat_tagihan' => false,
                'bulan' => $bulan,
                'tahun' => $tahun,
                'label_periode' => $labelPeriode,
                'status' => 'belum_terbit',
                'nominal_tagihan' => 0.0,
                'total_dibayar' => 0.0,
                'sisa_tagihan' => 0.0,
            ];
        }

        $nominal = (float) $tagihan->nominal_tagihan;
        $dibayar = (float) $tagihan->pembayaran->sum('jumlah_bayar');
        $sisa = max(0.0, $nominal - $dibayar);
        $isLunas = ($tagihan->status === 'lunas' || $sisa <= 0);

        return [
            'tercatat_tagihan' => true,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'label_periode' => $labelPeriode,
            'status' => $isLunas ? 'lunas' : ($dibayar > 0 ? 'kurang_bayar' : 'belum_lunas'),
            'nominal_tagihan' => $nominal,
            'total_dibayar' => $dibayar,
            'sisa_tagihan' => $sisa,
            'sisa_tagihan_rp' => 'Rp '.number_format($sisa, 0, ',', '.'),
        ];
    }

    /**
     * Deteksi apakah wali menanyakan pembayaran terakhir, status pencatatan pembayaran, atau konfirmasi transfer.
     */
    public function isAskingLastPaymentOrConfirmation(string $message): bool
    {
        return (bool) preg_match(
            '/\b(pembayaran\s+terakhir|bayar\s+terakhir|terakhir\s+bayar|kapan\s+bayar|sudah\s+tercatat|udah\s+tercatat|tercatat\s+belum|sudah\s+masuk\s+belum|sudah\s+transfer|udah\s+transfer|udh\s+tf|sudah\s+tf|belum\s+ada\s+konfirmasi|blm\s+ada\s+konfirmasi)\b/i',
            $message
        );
    }

    /**
     * Deteksi apakah wali menyampaikan bahwa sudah transfer namun belum menerima konfirmasi.
     */
    public function isTransferUnconfirmedQuery(string $message): bool
    {
        return (bool) preg_match(
            '/\b(sudah\s+transfer|udah\s+transfer|udh\s+tf|sudah\s+tf|belum\s+ada\s+konfirmasi|blm\s+ada\s+konfirmasi)\b/i',
            $message
        );
    }

    /**
     * Format ringkasan pembayaran terakhir santri beserta panduan konfirmasi transfer jika diperlukan.
     */
    public function formatLastPaymentSummary(Siswa $siswa, string $originalMessage = ''): string
    {
        $last = $this->inspectLastPayment($siswa);
        $isTransferClaim = $this->isTransferUnconfirmedQuery($originalMessage);
        $lines = [];

        if ($last) {
            $lines[] = '🧾 *Pembayaran Terakhir Tercatat:*';
            $lines[] = "• Tanggal: *{$last['tanggal_bayar_formatted']}*";
            $lines[] = "• Nominal: *{$last['jumlah_bayar_rp']}* (SPP {$last['periode_tagihan']})";
            $lines[] = "• Metode: {$last['metode_pembayaran']} (No. Kuitansi: `{$last['nomor_kuitansi']}`)";
        } else {
            $lines[] = '🧾 *Pembayaran Terakhir Tercatat:* Belum ada riwayat transaksi pembayaran SPP yang tercatat di sistem.';
        }

        if ($isTransferClaim) {
            $bendahara = $this->getBendaharaInfo();
            $lines[] = '';
            $lines[] = "ℹ️ Apabila Bapak/Ibu baru saja melakukan *transfer* namun belum tercatat pada status di atas, silakan kirimkan foto *bukti transfer* ke WhatsApp Bendahara (*{$bendahara['nama']}*: *{$bendahara['kontak']}*) agar segera diverifikasi dan diinput ke sistem.";
        }

        return implode("\n", $lines);
    }

    /**
     * Ambil nama dan kontak resmi Bendahara TPQ.
     *
     * @return array{nama: string, kontak: string, rekening: string}
     */
    public function getBendaharaInfo(): array
    {
        $namaBendahara = User::getNamaBendahara();
        $kontakBendahara = trim((string) (
            Pengaturan::getValue('no_hp_bendahara')
            ?: Pengaturan::getValue('kontak_bendahara')
            ?: '085235567818'
        )) ?: '085235567818';

        $rekeningSpp = trim((string) Pengaturan::getValue('rekening_spp'));

        return [
            'nama' => $namaBendahara,
            'kontak' => $kontakBendahara,
            'rekening' => $rekeningSpp,
        ];
    }

    /**
     * Susun payload terstruktur untuk Gemini Function Calling (`check_spp_payment`).
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
        $targetBulan = (int) ($period['bulan'] ?? Carbon::now('Asia/Jakarta')->month);
        $targetTahun = (int) ($period['tahun'] ?? Carbon::now('Asia/Jakarta')->year);

        if ($selectedSiswa) {
            return [
                'status' => 'ok',
                'santri' => [
                    'id' => $selectedSiswa->id,
                    'nisn' => $selectedSiswa->nisn,
                    'nama_lengkap' => $selectedSiswa->nama_lengkap,
                    'kelas' => $selectedSiswa->kelas?->nama_kelas,
                ],
                'status_bulan_target' => $this->inspectMonthPayment($selectedSiswa, $targetBulan, $targetTahun),
                'pembayaran_terakhir' => $this->inspectLastPayment($selectedSiswa),
                'bendahara' => $this->getBendaharaInfo(),
                'data_spp' => $chatbotService->handleSppNoticeQuery($selectedSiswa, null, $originalMessage),
            ];
        }

        $details = [];
        foreach ($candidates as $cand) {
            $details[] = [
                'nama_lengkap' => $cand->nama_lengkap,
                'status_bulan_target' => $this->inspectMonthPayment($cand, $targetBulan, $targetTahun),
                'pembayaran_terakhir' => $this->inspectLastPayment($cand),
            ];
        }

        return [
            'status' => 'ok',
            'santri' => 'semua_anak_wali',
            'detail_pembayaran' => $details,
            'bendahara' => $this->getBendaharaInfo(),
            'data_spp' => $chatbotService->handleSppNoticeQuery($candidates->first(), $candidates, $originalMessage),
        ];
    }
}
