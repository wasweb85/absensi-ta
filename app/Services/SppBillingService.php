<?php

namespace App\Services;

use App\Models\PembayaranSpp;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\TagihanSpp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SppBillingService
{
    private static bool $schemaChecked = false;

    /**
     * Nama-nama bulan dalam bahasa Indonesia
     */
    public const NAMA_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * Awal semester ajaran berjalan (Juli 2026)
     */
    public const SEMESTER_START_BULAN = 7;

    public const SEMESTER_START_TAHUN = 2026;

    /**
     * Mendapatkan awal semester ajaran aktif
     *
     * @return array{0: int, 1: int}
     */
    public static function getSemesterStart(): array
    {
        $startBulan = (int) Pengaturan::getValue('spp_semester_start_bulan', self::SEMESTER_START_BULAN);
        $startTahun = (int) Pengaturan::getValue('spp_semester_start_tahun', self::SEMESTER_START_TAHUN);

        return [$startBulan, $startTahun];
    }

    /**
     * Memastikan kolom nominal_spp, tabel tagihan_spp, dan pembayaran_spp tersedia di database
     * (self-healing untuk remote cloud database jika migrasi belum sempat dijalankan).
     */
    public static function ensureSppSchemaExists(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            if (Schema::hasTable('kelas') && ! Schema::hasColumn('kelas', 'nominal_spp')) {
                Schema::table('kelas', function (Blueprint $table) {
                    $table->decimal('nominal_spp', 12, 2)->default(15000)->after('nama_kelas');
                });
            }

            if (! Schema::hasTable('tagihan_spp')) {
                Schema::create('tagihan_spp', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('id_siswa')->constrained('siswa')->cascadeOnDelete();
                    $table->tinyInteger('bulan')->comment('1 = Januari s/d 12 = Desember');
                    $table->year('tahun');
                    $table->string('tahun_ajaran', 20)->default('2026/2027');
                    $table->decimal('nominal_tagihan', 12, 2);
                    $table->enum('status', ['belum_lunas', 'lunas', 'kurang_bayar'])->default('belum_lunas');
                    $table->date('jatuh_tempo')->nullable();
                    $table->timestamps();
                    $table->softDeletes();

                    $table->unique(['id_siswa', 'bulan', 'tahun'], 'uniq_tagihan_siswa_periode');
                });
            }

            if (! Schema::hasTable('pembayaran_spp')) {
                Schema::create('pembayaran_spp', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('id_tagihan')->constrained('tagihan_spp')->cascadeOnDelete();
                    $table->foreignId('id_siswa')->constrained('siswa')->cascadeOnDelete();
                    $table->string('nomor_kuitansi', 50)->unique();
                    $table->dateTime('tanggal_bayar');
                    $table->decimal('jumlah_bayar', 12, 2);
                    $table->enum('metode_pembayaran', ['Tunai', 'Transfer'])->default('Tunai');
                    $table->foreignId('id_petugas')->nullable()->constrained('users')->nullOnDelete();
                    $table->enum('status_transaksi', ['valid', 'dibatalkan'])->default('valid');
                    $table->text('alasan_pembatalan')->nullable();
                    $table->string('dibatalkan_oleh', 100)->nullable();
                    $table->text('catatan')->nullable();
                    $table->timestamps();
                });
            }
        } catch (\Throwable) {
            // Abaikan jika gagal DDL
        }
    }

    /**
     * Membuat tagihan SPP bulanan otomatis untuk seluruh santri aktif
     *
     * @return array{created: int, skipped: int, total_santri: int}
     */
    public function generateTagihanBulanan(int $bulan, int $tahun): array
    {
        self::ensureSppSchemaExists();

        $tahunAjaran = Pengaturan::getValue('tahun_ajaran_aktif', '2026/2027');
        $santriList = Siswa::with('kelas')->where('status', 'aktif')->get();

        $created = 0;
        $skipped = 0;

        foreach ($santriList as $santri) {
            $nominal = $santri->kelas?->nominal_spp ?: 15000;

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
        self::ensureSppSchemaExists();

        $tagihan = TagihanSpp::where('id_siswa', $idSiswa)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->first();

        if (! $tagihan) {
            $siswa = Siswa::with('kelas')->findOrFail($idSiswa);
            $nominal = $siswa->kelas?->nominal_spp ?: 15000;
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
     * dibatasi sampai bulan dan tahun cut-off (default: bulan sekarang)
     */
    public function hitungTunggakanSantri(int $idSiswa, ?int $cutOffBulan = null, ?int $cutOffTahun = null): array
    {
        self::ensureSppSchemaExists();

        $cutOffBulan = $cutOffBulan ?: (int) Carbon::now('Asia/Jakarta')->month;
        $cutOffTahun = $cutOffTahun ?: (int) Carbon::now('Asia/Jakarta')->year;

        // Pastikan tagihan sampai periode cut-off yang diminta tersedia di database
        $cutOffExists = TagihanSpp::where('tahun', $cutOffTahun)
            ->where('bulan', $cutOffBulan)
            ->exists();

        if (! $cutOffExists) {
            $toTahun = max(2027, $cutOffTahun);
            $toBulan = ($toTahun === 2027) ? max(6, $cutOffBulan) : $cutOffBulan;
            $this->sinkronkanTagihanSemester(7, 2026, $toBulan, $toTahun);
        }

        [$startBulan, $startTahun] = self::getSemesterStart();

        $tagihanUnpaid = TagihanSpp::with(['pembayaran' => function ($q) {
            $q->where('status_transaksi', 'valid');
        }])
            ->where('id_siswa', $idSiswa)
            ->whereIn('status', ['belum_lunas', 'kurang_bayar'])
            ->where(function ($q) use ($startBulan, $startTahun, $cutOffBulan, $cutOffTahun) {
                // Batas awal: mulai awal semester (Juli 2026)
                $q->where(function ($sq) use ($startBulan, $startTahun) {
                    $sq->where('tahun', '>', $startTahun)
                        ->orWhere(function ($sq2) use ($startBulan, $startTahun) {
                            $sq2->where('tahun', $startTahun)
                                ->where('bulan', '>=', $startBulan);
                        });
                });

                // Batas akhir: s/d cut-off bulan & tahun
                $q->where(function ($sq) use ($cutOffBulan, $cutOffTahun) {
                    $sq->where('tahun', '<', $cutOffTahun)
                        ->orWhere(function ($sq2) use ($cutOffBulan, $cutOffTahun) {
                            $sq2->where('tahun', $cutOffTahun)
                                ->where('bulan', '<=', $cutOffBulan);
                        });
                });
            })
            ->orderBy('tahun', 'asc')
            ->orderBy('bulan', 'asc')
            ->get();

        $totalNominal = 0;
        $daftarBulan = [];

        foreach ($tagihanUnpaid as $t) {
            // Hitung sisa bayar (nominal_tagihan - sum valid pembayaran)
            $totalBayarValid = (float) $t->pembayaran->sum('jumlah_bayar');

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
        self::ensureSppSchemaExists();

        return DB::transaction(function () use ($data, $idPetugas) {
            $idTagihan = $data['id_tagihan'];
            $tagihan = TagihanSpp::with('siswa.kelas')->findOrFail($idTagihan);

            $jumlahBayar = (float) $data['jumlah_bayar'];
            $metode = $data['metode_pembayaran'] ?? 'Tunai';
            $catatan = $data['catatan'] ?? null;

            // Generate nomor kuitansi unik: KW-YYYYMM-XXXXX dengan kunci pesimis mencegah duplikasi concurrent
            $prefix = 'KW-'.date('Ym').'-';
            $lastPayment = PembayaranSpp::where('nomor_kuitansi', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();

            $nextNumber = 1;
            if ($lastPayment) {
                $lastNumber = (int) substr($lastPayment->nomor_kuitansi, strlen($prefix), 5);
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
            $periodeStr = "{$namaBulan} {$tagihan->tahun}";

            return [
                'success' => true,
                'message' => "Pembayaran berhasil disimpan. Kuitansi: {$noKuitansi}",
                'kuitansi' => [
                    'nomor_kuitansi' => $noKuitansi,
                    'tanggal_bayar' => $pembayaran->tanggal_bayar->format('d/m/Y H:i'),
                    'nama_santri' => $tagihan->siswa->nama_lengkap,
                    'nisn' => $tagihan->siswa->nisn,
                    'no_hp_ortu' => $tagihan->siswa->no_hp_ortu,
                    'kelas' => $tagihan->siswa->kelas?->nama_kelas,
                    'bulan' => $tagihan->bulan,
                    'tahun' => $tagihan->tahun,
                    'nama_bulan' => $namaBulan,
                    'periode' => $periodeStr,
                    'daftar_bulan_str' => $periodeStr,
                    'bulan_tagihan' => $periodeStr,
                    'pembayaran_untuk' => "SPP {$periodeStr}",
                    'jumlah_bayar' => $jumlahBayar,
                    'metode' => $metode,
                    'status_tagihan' => $tagihan->fresh()->status,
                    'catatan' => $catatan,
                    'nama_bendahara' => User::getNamaBendahara(),
                ],
            ];
        });
    }

    /**
     * Memproses pembayaran multi-bulan untuk satu santri sekaligus
     *
     * @param  array<int>  $tagihanIds
     */
    public function bayarMultiBulan(int $idSiswa, array $tagihanIds, string $metode, ?string $catatan, int $idPetugas): array
    {
        self::ensureSppSchemaExists();

        return DB::transaction(function () use ($idSiswa, $tagihanIds, $metode, $catatan, $idPetugas) {
            $siswa = Siswa::with('kelas')->findOrFail($idSiswa);

            // Ambil tagihan yang dipilih dengan eager loading pembayaran
            $tagihanList = TagihanSpp::with('pembayaran')
                ->whereIn('id', $tagihanIds)
                ->orderBy('tahun', 'asc')
                ->orderBy('bulan', 'asc')
                ->get();

            if ($tagihanList->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'Tidak ada tagihan yang dipilih.',
                ];
            }

            // Validasi kepemilikan tagihan & status
            foreach ($tagihanList as $t) {
                if ($t->id_siswa !== $idSiswa) {
                    return [
                        'success' => false,
                        'message' => "Tagihan #{$t->id} bukan milik santri {$siswa->nama_lengkap}.",
                    ];
                }

                if ($t->status === 'lunas') {
                    $namaBln = self::NAMA_BULAN[$t->bulan] ?? "Bulan {$t->bulan}";

                    return [
                        'success' => false,
                        'message' => "Tagihan {$namaBln} {$t->tahun} sudah lunas sebelumnya.",
                    ];
                }
            }

            // Generate nomor kuitansi unik bersamaan
            $prefix = 'KW-'.date('Ym').'-';
            $lastPayment = PembayaranSpp::where('nomor_kuitansi', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();

            $nextNumber = 1;
            if ($lastPayment) {
                $lastNumber = (int) substr($lastPayment->nomor_kuitansi, strlen($prefix), 5);
                $nextNumber = $lastNumber + 1;
            }
            $baseKuitansi = $prefix.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);

            $now = Carbon::now('Asia/Jakarta');
            $totalBayar = 0;
            $daftarBulan = [];
            $isMulti = count($tagihanList) > 1;
            $idx = 1;

            foreach ($tagihanList as $tagihan) {
                $totalBayarValid = (float) $tagihan->pembayaran->where('status_transaksi', 'valid')->sum('jumlah_bayar');
                $sisaKewajiban = max(0, (float) $tagihan->nominal_tagihan - $totalBayarValid);

                if ($sisaKewajiban <= 0) {
                    $tagihan->update(['status' => 'lunas']);

                    continue;
                }

                $itemKuitansi = $isMulti ? "{$baseKuitansi}-{$idx}" : $baseKuitansi;
                $idx++;

                PembayaranSpp::create([
                    'id_tagihan' => $tagihan->id,
                    'id_siswa' => $idSiswa,
                    'nomor_kuitansi' => $itemKuitansi,
                    'tanggal_bayar' => $now,
                    'jumlah_bayar' => $sisaKewajiban,
                    'metode_pembayaran' => $metode,
                    'id_petugas' => $idPetugas,
                    'status_transaksi' => 'valid',
                    'catatan' => $catatan ?: "Pelunasan Multi-Bulan ({$tagihan->bulan}/{$tagihan->tahun})",
                ]);

                $tagihan->update(['status' => 'lunas']);

                $namaBulan = self::NAMA_BULAN[$tagihan->bulan] ?? "Bulan {$tagihan->bulan}";
                $daftarBulan[] = [
                    'id_tagihan' => $tagihan->id,
                    'bulan' => $tagihan->bulan,
                    'tahun' => $tagihan->tahun,
                    'label' => "{$namaBulan} {$tagihan->tahun}",
                    'nominal' => $sisaKewajiban,
                    'nomor_kuitansi' => $itemKuitansi,
                ];

                $totalBayar += $sisaKewajiban;
            }

            $bulanLabels = array_column($daftarBulan, 'label');
            $bulanStr = implode(', ', $bulanLabels);

            return [
                'success' => true,
                'message' => "Pembayaran {$bulanStr} berhasil disimpan. Kuitansi: {$baseKuitansi}",
                'kuitansi' => [
                    'nomor_kuitansi' => $baseKuitansi,
                    'tanggal_bayar' => $now->format('d/m/Y H:i'),
                    'nama_santri' => $siswa->nama_lengkap,
                    'nisn' => $siswa->nisn,
                    'no_hp_ortu' => $siswa->no_hp_ortu,
                    'kelas' => $siswa->kelas?->nama_kelas ?: '-',
                    'total_bulan' => count($daftarBulan),
                    'daftar_bulan' => $daftarBulan,
                    'daftar_bulan_str' => $bulanStr,
                    'periode' => $bulanStr,
                    'bulan_tagihan' => $bulanStr,
                    'pembayaran_untuk' => "SPP {$bulanStr}",
                    'total_bayar' => $totalBayar,
                    'jumlah_bayar' => $totalBayar,
                    'metode' => $metode,
                    'catatan' => $catatan,
                    'nama_bendahara' => User::getNamaBendahara(),
                ],
            ];
        });
    }

    /**
     * Membatalkan pembayaran dengan pencatatan audit trail alasan
     */
    public function batalkanPembayaran(int $idPembayaran, string $alasan, string $namaPetugas): array
    {
        self::ensureSppSchemaExists();

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
        self::ensureSppSchemaExists();

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

        // 3. Total seluruh tunggakan aktif riil (hanya tagihan yang sudah berjalan s/d bulan & tahun terpilih)
        [$startBulan, $startTahun] = self::getSemesterStart();
        $unpaidTagihanQuery = TagihanSpp::whereIn('status', ['belum_lunas', 'kurang_bayar'])
            ->where(function ($q) use ($startBulan, $startTahun, $bulan, $tahun) {
                // Batas awal: mulai Juli 2026
                $q->where(function ($sq) use ($startBulan, $startTahun) {
                    $sq->where('tahun', '>', $startTahun)
                        ->orWhere(function ($sq2) use ($startBulan, $startTahun) {
                            $sq2->where('tahun', $startTahun)
                                ->where('bulan', '>=', $startBulan);
                        });
                });

                // Batas akhir: s/d bulan & tahun terpilih
                $q->where(function ($sq) use ($bulan, $tahun) {
                    $sq->where('tahun', '<', $tahun)
                        ->orWhere(function ($sq2) use ($bulan, $tahun) {
                            $sq2->where('tahun', $tahun)
                                ->where('bulan', '<=', $bulan);
                        });
                });
            });

        $unpaidTagihanIds = (clone $unpaidTagihanQuery)->pluck('id');
        $totalNominalUnpaid = (float) (clone $unpaidTagihanQuery)->sum('nominal_tagihan');
        $totalBayarParsial = (float) PembayaranSpp::whereIn('id_tagihan', $unpaidTagihanIds)
            ->where('status_transaksi', 'valid')
            ->sum('jumlah_bayar');
        $totalTunggakanAktif = max(0, $totalNominalUnpaid - $totalBayarParsial);
        $totalSantriMenunggakAktif = (clone $unpaidTagihanQuery)->distinct('id_siswa')->count('id_siswa');

        // 4. Grafik pendapatan 12 bulan di tahun terpilih (1 query efisien dengan filter range tanggal)
        $startDate = Carbon::createFromDate($tahun, 1, 1)->startOfDay();
        $endDate = Carbon::createFromDate($tahun, 12, 31)->endOfDay();

        $monthlyPayments = PembayaranSpp::where('status_transaksi', 'valid')
            ->whereBetween('tanggal_bayar', [$startDate, $endDate])
            ->get(['tanggal_bayar', 'jumlah_bayar'])
            ->groupBy(fn ($item) => (int) Carbon::parse($item->tanggal_bayar)->format('n'));

        $grafikBulanan = [];
        for ($m = 1; $m <= 12; $m++) {
            $grafikBulanan[] = [
                'bulan' => $m,
                'nama_bulan' => self::NAMA_BULAN[$m],
                'total' => (float) ($monthlyPayments->get($m)?->sum('jumlah_bayar') ?? 0),
            ];
        }

        return [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'pemasukan_bulan_ini' => $pemasukanBulanIni,
            'total_tagihan_nominal' => $totalTagihanNominal,
            'total_tunggakan_aktif' => $totalTunggakanAktif,
            'santri_lunas' => $totalLunasCount,
            'santri_belum_lunas' => $totalSantriMenunggakAktif, // Jumlah santri yang memiliki tunggakan riil s/d bulan berjalan
            'santri_belum_lunas_bulan_ini' => $totalBelumLunasCount,
            'persentase_lunas' => ($tagihanBulanIni->count() > 0) ? round(($totalLunasCount / $tagihanBulanIni->count()) * 100, 1) : 0,
            'grafik_bulanan' => $grafikBulanan,
        ];
    }

    /**
     * Menyinkronkan tagihan seluruh santri aktif untuk tahun ajaran aktif (Juli 2026 s/d Juni 2027 atau sesuai rentang).
     *
     * @return array{created: int, existing: int, total_santri: int}
     */
    public function sinkronkanTagihanSemester(
        int $fromBulan = 7,
        int $fromTahun = 2026,
        int $toBulan = 6,
        int $toTahun = 2027
    ): array {
        self::ensureSppSchemaExists();

        $santriList = Siswa::with('kelas')
            ->where('status', 'aktif')
            ->get();

        if ($santriList->isEmpty()) {
            return ['created' => 0, 'existing' => 0, 'total_santri' => 0];
        }

        $tahunAjaran = Pengaturan::getValue('tahun_ajaran_aktif', '2026/2027');

        $periods = [];
        for ($y = $fromTahun; $y <= $toTahun; $y++) {
            $mStart = ($y === $fromTahun) ? $fromBulan : 1;
            $mEnd = ($y === $toTahun) ? $toBulan : 12;
            for ($m = $mStart; $m <= $mEnd; $m++) {
                $periods[] = ['bulan' => $m, 'tahun' => $y];
            }
        }

        $existingRecords = TagihanSpp::whereIn('id_siswa', $santriList->pluck('id'))
            ->where(function ($q) use ($periods) {
                foreach ($periods as $p) {
                    $q->orWhere(function ($sub) use ($p) {
                        $sub->where('bulan', $p['bulan'])->where('tahun', $p['tahun']);
                    });
                }
            })
            ->select('id_siswa', 'bulan', 'tahun')
            ->get();

        $existingKeys = [];
        foreach ($existingRecords as $rec) {
            $existingKeys["{$rec->id_siswa}_{$rec->bulan}_{$rec->tahun}"] = true;
        }

        $now = Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s');
        $toInsert = [];
        $createdCount = 0;

        foreach ($santriList as $santri) {
            $nominal = (float) ($santri->kelas?->nominal_spp ?: 15000);
            foreach ($periods as $p) {
                $key = "{$santri->id}_{$p['bulan']}_{$p['tahun']}";
                if (! isset($existingKeys[$key])) {
                    $jatuhTempo = Carbon::createFromDate($p['tahun'], $p['bulan'], 10)->format('Y-m-d');
                    $toInsert[] = [
                        'id_siswa' => $santri->id,
                        'bulan' => $p['bulan'],
                        'tahun' => $p['tahun'],
                        'tahun_ajaran' => $tahunAjaran,
                        'nominal_tagihan' => $nominal,
                        'status' => 'belum_lunas',
                        'jatuh_tempo' => $jatuhTempo,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $createdCount++;
                    $existingKeys[$key] = true;
                }
            }
        }

        if (! empty($toInsert)) {
            foreach (array_chunk($toInsert, 200) as $chunk) {
                TagihanSpp::insert($chunk);
            }
        }

        return [
            'created' => $createdCount,
            'existing' => count($existingKeys) - $createdCount,
            'total_santri' => $santriList->count(),
        ];
    }

    /**
     * Rekap Akumulasi Santri Menunggak dari awal semester berjalan sampai bulan cut-off
     *
     * @return array{summary: array, santri: array}
     */
    public function getRekapTunggakanSantri(
        ?int $idKelas = null,
        ?int $cutOffBulan = null,
        ?int $cutOffTahun = null,
        ?string $search = null
    ): array {
        self::ensureSppSchemaExists();

        $cutOffBulan = $cutOffBulan ?: (int) Carbon::now('Asia/Jakarta')->month;
        $cutOffTahun = $cutOffTahun ?: (int) Carbon::now('Asia/Jakarta')->year;

        // Pastikan tagihan sampai periode cut-off yang diminta tersedia di database
        $cutOffExists = TagihanSpp::where('tahun', $cutOffTahun)
            ->where('bulan', $cutOffBulan)
            ->exists();

        if (! $cutOffExists) {
            $toTahun = max(2027, $cutOffTahun);
            $toBulan = ($toTahun === 2027) ? max(6, $cutOffBulan) : $cutOffBulan;
            $this->sinkronkanTagihanSemester(7, 2026, $toBulan, $toTahun);
        }

        [$startBulan, $startTahun] = self::getSemesterStart();

        // Ambil semua tagihan unpaid mulai semester berjalan (Juli 2026) sampai dengan cut-off bulan & tahun
        $query = TagihanSpp::with([
            'siswa.kelas',
            'pembayaran' => function ($q) {
                $q->where('status_transaksi', 'valid');
            },
        ])
            ->whereIn('status', ['belum_lunas', 'kurang_bayar'])
            ->where(function ($q) use ($startBulan, $startTahun, $cutOffBulan, $cutOffTahun) {
                // Batas awal: mulai Juli 2026 (awal semester ajaran aktif)
                $q->where(function ($sq) use ($startBulan, $startTahun) {
                    $sq->where('tahun', '>', $startTahun)
                        ->orWhere(function ($sq2) use ($startBulan, $startTahun) {
                            $sq2->where('tahun', $startTahun)
                                ->where('bulan', '>=', $startBulan);
                        });
                });

                // Batas akhir: s/d cut-off bulan & tahun
                $q->where(function ($sq) use ($cutOffBulan, $cutOffTahun) {
                    $sq->where('tahun', '<', $cutOffTahun)
                        ->orWhere(function ($sq2) use ($cutOffBulan, $cutOffTahun) {
                            $sq2->where('tahun', $cutOffTahun)
                                ->where('bulan', '<=', $cutOffBulan);
                        });
                });
            })
            ->whereHas('siswa', function ($q) use ($idKelas, $search) {
                $q->where('status', 'aktif');
                if ($idKelas) {
                    $q->where('id_kelas', $idKelas);
                }
                if (! empty($search)) {
                    $q->where(function ($sq) use ($search) {
                        $sq->where('nama_lengkap', 'like', "%{$search}%")
                            ->orWhere('nisn', 'like', "%{$search}%");
                    });
                }
            })
            ->orderBy('tahun', 'asc')
            ->orderBy('bulan', 'asc');

        $tagihanList = $query->get();

        // Group per id_siswa
        $grouped = $tagihanList->groupBy('id_siswa');
        $rekapList = [];
        $totalNominalSemua = 0;

        foreach ($grouped as $idSiswa => $items) {
            $siswa = $items->first()->siswa;
            if (! $siswa) {
                continue;
            }

            $daftarBulan = [];
            $totalSiswa = 0;

            foreach ($items as $t) {
                $totalBayarValid = (float) $t->pembayaran->sum('jumlah_bayar');
                $sisa = max(0, (float) $t->nominal_tagihan - $totalBayarValid);
                if ($sisa > 0) {
                    $totalSiswa += $sisa;
                    $namaBln = self::NAMA_BULAN[$t->bulan] ?? "Bln {$t->bulan}";
                    $daftarBulan[] = [
                        'id_tagihan' => $t->id,
                        'bulan' => $t->bulan,
                        'tahun' => $t->tahun,
                        'nama_bulan' => $namaBln,
                        'label' => "{$namaBln} {$t->tahun}",
                        'nominal_tagihan' => (float) $t->nominal_tagihan,
                        'sisa' => $sisa,
                    ];
                }
            }

            if ($totalSiswa > 0) {
                $totalNominalSemua += $totalSiswa;
                $daftarBulanStr = implode(', ', array_map(fn ($b) => $b['nama_bulan'], $daftarBulan));
                $tahunStr = count($daftarBulan) > 0 ? $daftarBulan[0]['tahun'] : $cutOffTahun;

                $rekapList[] = [
                    'id_siswa' => $siswa->id,
                    'nama_lengkap' => $siswa->nama_lengkap,
                    'nisn' => $siswa->nisn,
                    'id_kelas' => $siswa->id_kelas,
                    'nama_kelas' => $siswa->kelas?->nama_kelas ?: '-',
                    'no_hp_ortu' => $siswa->no_hp_ortu,
                    'total_bulan' => count($daftarBulan),
                    'daftar_bulan' => $daftarBulan,
                    'daftar_bulan_str' => "{$daftarBulanStr} {$tahunStr}",
                    'total_tunggakan' => $totalSiswa,
                ];
            }
        }

        // Urutkan dari jumlah bulan tunggakan terbanyak, lalu nominal terbesar
        usort($rekapList, function ($a, $b) {
            if ($b['total_bulan'] === $a['total_bulan']) {
                return $b['total_tunggakan'] <=> $a['total_tunggakan'];
            }

            return $b['total_bulan'] <=> $a['total_bulan'];
        });

        $totalSantri = count($rekapList);
        $avgBulan = $totalSantri > 0 ? round(array_sum(array_column($rekapList, 'total_bulan')) / $totalSantri, 1) : 0;

        return [
            'summary' => [
                'total_santri_menunggak' => $totalSantri,
                'total_nominal_tunggakan' => $totalNominalSemua,
                'rata_rata_bulan' => $avgBulan,
                'cut_off_bulan' => $cutOffBulan,
                'cut_off_tahun' => $cutOffTahun,
                'nama_bulan_cut_off' => self::NAMA_BULAN[$cutOffBulan] ?? "Bulan {$cutOffBulan}",
            ],
            'santri' => $rekapList,
        ];
    }
}
