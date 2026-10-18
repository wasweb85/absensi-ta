<?php

namespace App\Services;

use App\Models\PembayaranSpp;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\TagihanSpp;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SppBillingService
{
    /**
     * Nama-nama bulan dalam bahasa Indonesia
     */
    public const NAMA_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * Membuat tagihan SPP bulanan otomatis untuk seluruh santri aktif
     *
     * @return array{created: int, skipped: int, total_santri: int}
     */
    public function generateTagihanBulanan(int $bulan, int $tahun): array
    {
        $tahunAjaran = Pengaturan::getValue('tahun_ajaran_aktif', '2026/2027');
        $santriList = Siswa::with('kelas')->where('status', 'aktif')->get();

        $created = 0;
        $skipped = 0;

        foreach ($santriList as $santri) {
            $nominal = $santri->kelas?->nominal_spp ?: 0;

            $exists = TagihanSpp::where('id_siswa', $santri->id)
                ->where('bulan', $bulan)
                ->where('tahun', $tahun)
                ->exists();

            if (! $exists) {
                TagihanSpp::create([
                    'id_siswa' => $santri->id,
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                    'tahun_ajaran' => $tahunAjaran,
                    'nominal_tagihan' => $nominal,
                    'status' => 'belum_lunas',
                    'jatuh_tempo' => Carbon::createFromDate($tahun, $bulan, 10)->format('Y-m-d'),
                ]);
                $created++;
            } else {
                $skipped++;
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'total_santri' => $santriList->count(),
        ];
    }

    /**
     * Memastikan tagihan santri tersedia saat akan bertransaksi
     */
    public function ensureTagihan(int $idSiswa, int $bulan, int $tahun): TagihanSpp
    {
        $tagihan = TagihanSpp::where('id_siswa', $idSiswa)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->first();

        if (! $tagihan) {
            $siswa = Siswa::with('kelas')->findOrFail($idSiswa);
            $nominal = $siswa->kelas?->nominal_spp ?: 0;
            $tahunAjaran = Pengaturan::getValue('tahun_ajaran_aktif', '2026/2027');

            $tagihan = TagihanSpp::create([
                'id_siswa' => $idSiswa,
                'bulan' => $bulan,
                'tahun' => $tahun,
                'tahun_ajaran' => $tahunAjaran,
                'nominal_tagihan' => $nominal,
                'status' => 'belum_lunas',
                'jatuh_tempo' => Carbon::createFromDate($tahun, $bulan, 10)->format('Y-m-d'),
            ]);
        }

        return $tagihan;
    }

    /**
     * Menghitung total tunggakan dan rincian bulan belum lunas seorang santri
     */
    public function hitungTunggakanSantri(int $idSiswa): array
    {
        $tagihanUnpaid = TagihanSpp::where('id_siswa', $idSiswa)
            ->whereIn('status', ['belum_lunas', 'kurang_bayar'])
            ->orderBy('tahun', 'asc')
            ->orderBy('bulan', 'asc')
            ->get();

        $totalNominal = 0;
        $daftarBulan = [];

        foreach ($tagihanUnpaid as $t) {
            // Hitung sisa bayar (nominal_tagihan - sum valid pembayaran)
            $totalBayarValid = PembayaranSpp::where('id_tagihan', $t->id)
                ->where('status_transaksi', 'valid')
                ->sum('jumlah_bayar');

            $sisa = max(0, $t->nominal_tagihan - $totalBayarValid);
            $totalNominal += $sisa;

            $namaBulan = self::NAMA_BULAN[$t->bulan] ?? "Bulan {$t->bulan}";
            $daftarBulan[] = [
                'id_tagihan' => $t->id,
                'bulan' => $t->bulan,
                'tahun' => $t->tahun,
                'label' => "{$namaBulan} {$t->tahun}",
                'nominal_tagihan' => (float) $t->nominal_tagihan,
                'sudah_dibayar' => (float) $totalBayarValid,
                'sisa' => (float) $sisa,
            ];
        }

        return [
            'total_tunggakan' => (float) $totalNominal,
            'total_bulan' => count($daftarBulan),
            'daftar_tunggakan' => $daftarBulan,
        ];
    }

    /**
     * Memproses transaksi pembayaran SPP dengan transaksi database aman
     */
    public function prosesPembayaran(array $data, int $idPetugas): array
    {
        return DB::transaction(function () use ($data, $idPetugas) {
            $idTagihan = $data['id_tagihan'];
            $tagihan = TagihanSpp::with('siswa.kelas')->findOrFail($idTagihan);

            $jumlahBayar = (float) $data['jumlah_bayar'];
            $metode = $data['metode_pembayaran'] ?? 'Tunai';
            $catatan = $data['catatan'] ?? null;

            // Generate nomor kuitansi unik: KW-YYYYMM-XXXXX
            $prefix = 'KW-'.date('Ym').'-';
            $lastPayment = PembayaranSpp::where('nomor_kuitansi', 'like', $prefix.'%')
                ->orderBy('id', 'desc')
                ->first();

            $nextNumber = 1;
            if ($lastPayment) {
                $lastNumber = (int) substr($lastPayment->nomor_kuitansi, -5);
                $nextNumber = $lastNumber + 1;
            }
            $noKuitansi = $prefix.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);

            // Simpan data pembayaran
            $pembayaran = PembayaranSpp::create([
                'id_tagihan' => $tagihan->id,
                'id_siswa' => $tagihan->id_siswa,
                'nomor_kuitansi' => $noKuitansi,
                'tanggal_bayar' => Carbon::now('Asia/Jakarta'),
                'jumlah_bayar' => $jumlahBayar,
                'metode_pembayaran' => $metode,
                'id_petugas' => $idPetugas,
                'status_transaksi' => 'valid',
                'catatan' => $catatan,
            ]);

            // Hitung ulang akumulasi pembayaran valid untuk tagihan ini
            $totalBayarValid = PembayaranSpp::where('id_tagihan', $tagihan->id)
                ->where('status_transaksi', 'valid')
                ->sum('jumlah_bayar');

            if ($totalBayarValid >= $tagihan->nominal_tagihan) {
                $tagihan->update(['status' => 'lunas']);
            } else {
                $tagihan->update(['status' => 'kurang_bayar']);
            }

            $namaBulan = self::NAMA_BULAN[$tagihan->bulan] ?? "Bulan {$tagihan->bulan}";

            return [
                'success' => true,
                'message' => "Pembayaran berhasil disimpan. Kuitansi: {$noKuitansi}",
                'kuitansi' => [
                    'nomor_kuitansi' => $noKuitansi,
                    'tanggal_bayar' => $pembayaran->tanggal_bayar->format('d/m/Y H:i'),
                    'nama_santri' => $tagihan->siswa->nama_lengkap,
                    'nisn' => $tagihan->siswa->nisn,
                    'kelas' => $tagihan->siswa->kelas?->nama_kelas,
                    'pembayaran_untuk' => "SPP {$namaBulan} {$tagihan->tahun}",
                    'jumlah_bayar' => $jumlahBayar,
                    'metode' => $metode,
                    'status_tagihan' => $tagihan->fresh()->status,
                    'catatan' => $catatan,
                ],
            ];
        });
    }

    /**
     * Membatalkan pembayaran dengan pencatatan audit trail alasan
     */
    public function batalkanPembayaran(int $idPembayaran, string $alasan, string $namaPetugas): array
    {
        return DB::transaction(function () use ($idPembayaran, $alasan, $namaPetugas) {
            $pembayaran = PembayaranSpp::with('tagihan')->findOrFail($idPembayaran);

            if ($pembayaran->status_transaksi === 'dibatalkan') {
                return [
                    'success' => false,
                    'message' => 'Transaksi pembayaran ini sudah dibatalkan sebelumnya.',
                ];
            }

            $pembayaran->update([
                'status_transaksi' => 'dibatalkan',
                'alasan_pembatalan' => $alasan,
                'dibatalkan_oleh' => $namaPetugas,
            ]);

            // Hitung ulang sisa pembayaran valid untuk tagihan
            $tagihan = $pembayaran->tagihan;
            $totalValid = PembayaranSpp::where('id_tagihan', $tagihan->id)
                ->where('status_transaksi', 'valid')
                ->sum('jumlah_bayar');

            if ($totalValid >= $tagihan->nominal_tagihan) {
                $tagihan->update(['status' => 'lunas']);
            } elseif ($totalValid > 0) {
                $tagihan->update(['status' => 'kurang_bayar']);
            } else {
                $tagihan->update(['status' => 'belum_lunas']);
            }

            return [
                'success' => true,
                'message' => "Pembayaran [{$pembayaran->nomor_kuitansi}] berhasil dibatalkan. Status tagihan disesuaikan.",
            ];
        });
    }

    /**
     * Ringkasan Dashboard Keuangan SPP & Grafik Bulanan
     */
    public function getDashboardFinansial(int $bulan, int $tahun): array
    {
        // 1. Pemasukan bulan ini
        $pemasukanBulanIni = (float) PembayaranSpp::where('status_transaksi', 'valid')
            ->whereMonth('tanggal_bayar', $bulan)
            ->whereYear('tanggal_bayar', $tahun)
            ->sum('jumlah_bayar');

        // 2. Tagihan bulan ini & status lunas
        $tagihanBulanIni = TagihanSpp::where('bulan', $bulan)->where('tahun', $tahun)->get();
        $totalTagihanNominal = (float) $tagihanBulanIni->sum('nominal_tagihan');
        $totalLunasCount = $tagihanBulanIni->where('status', 'lunas')->count();
        $totalBelumLunasCount = $tagihanBulanIni->whereIn('status', ['belum_lunas', 'kurang_bayar'])->count();

        // 3. Total seluruh tunggakan aktif
        $totalTunggakanAktif = (float) TagihanSpp::whereIn('status', ['belum_lunas', 'kurang_bayar'])
            ->sum('nominal_tagihan');

        // 4. Grafik pendapatan 12 bulan di tahun terpilih
        $grafikBulanan = [];
        for ($m = 1; $m <= 12; $m++) {
            $totalM = (float) PembayaranSpp::where('status_transaksi', 'valid')
                ->whereMonth('tanggal_bayar', $m)
                ->whereYear('tanggal_bayar', $tahun)
                ->sum('jumlah_bayar');

            $grafikBulanan[] = [
                'bulan' => $m,
                'nama_bulan' => self::NAMA_BULAN[$m],
                'total' => $totalM,
            ];
        }

        return [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'pemasukan_bulan_ini' => $pemasukanBulanIni,
            'total_tagihan_nominal' => $totalTagihanNominal,
            'total_tunggakan_aktif' => $totalTunggakanAktif,
            'santri_lunas' => $totalLunasCount,
            'santri_belum_lunas' => $totalBelumLunasCount,
            'persentase_lunas' => ($tagihanBulanIni->count() > 0) ? round(($totalLunasCount / $tagihanBulanIni->count()) * 100, 1) : 0,
            'grafik_bulanan' => $grafikBulanan,
        ];
    }
}
