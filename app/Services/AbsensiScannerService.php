<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AbsensiScannerService
{
    public function __construct(
        protected WhatsAppService $waService
    ) {}

    /**
     * Memproses pemindaian absensi (QR / Barcode / NISN)
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function scan(string $nisn, string $scannerRole = 'guru', ?string $scannerKelas = null, string $metodeScan = 'qr_kamera', bool $restrictKelas = false): array
    {
        $timezone = 'Asia/Jakarta';
        $now = Carbon::now($timezone);
        $todayDate = $now->format('Y-m-d');
        $currentTime = $now->format('H:i:s');
        $currentTimeShort = $now->format('H:i');

        // Normalisasi NISN (buang karakter non-printable, prefix NISN, atau URL)
        $cleanNisn = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $nisn));
        if (preg_match('/nisn[:\s]+([a-zA-Z0-9_-]+)/i', $cleanNisn, $matches)) {
            $cleanNisn = $matches[1];
        } elseif (str_contains($cleanNisn, '/')) {
            $parts = explode('/', $cleanNisn);
            $cleanNisn = trim(end($parts));
        }

        // 1. Cari Siswa berdasarkan NISN atau ID
        $siswa = Siswa::with('kelas')
            ->where('nisn', $cleanNisn)
            ->orWhere('id', $cleanNisn)
            ->first();

        if (! $siswa) {
            return [
                'success' => false,
                'message' => "Siswa dengan NISN/Barcode [{$cleanNisn}] tidak ditemukan.",
            ];
        }

        // 2. Periksa status keaktifan siswa
        if ($siswa->status !== 'aktif') {
            return [
                'success' => false,
                'message' => "Siswa [{$siswa->nama_lengkap}] tidak aktif (Status: {$siswa->status}).",
            ];
        }

        // 3. Validasi akses kelas hanya jika scanner meminta pembatasan kelas jilidnya saja
        if ($restrictKelas && $scannerRole === 'guru' && ! empty($scannerKelas) && $scannerKelas !== 'semua') {
            if ($siswa->kelas && strtoupper(trim($siswa->kelas->nama_kelas)) !== strtoupper(trim($scannerKelas))) {
                return [
                    'success' => false,
                    'message' => "Siswa terdaftar di {$siswa->kelas->nama_kelas}, bukan di kelas Anda ({$scannerKelas}).",
                ];
            }
        }

        // 4. Periksa apakah hari ini adalah Hari Libur
        $isHoliday = HariLibur::where('tanggal_mulai', '<=', $todayDate)
            ->where('tanggal_selesai', '>=', $todayDate)
            ->first();

        if ($isHoliday) {
            return [
                'success' => false,
                'message' => "Hari ini libur: {$isHoliday->keterangan}.",
            ];
        }

        // 5. Ambil konfigurasi jam operasional
        $jamMasuk = Pengaturan::getValue('jam_masuk', '07:00');
        $jamTerlambat = Pengaturan::getValue('jam_terlambat', '07:15');

        // 6. Cek absensi siswa hari ini
        $absensi = Absensi::where('id_siswa', $siswa->id)
            ->where('tanggal', $todayDate)
            ->first();

        // Kasus A: Siswa belum ada catatan absensi hari ini (Absen Masuk)
        if (! $absensi) {
            $status = ($currentTimeShort <= $jamTerlambat) ? 'Hadir' : 'Terlambat';

            $absensi = Absensi::create([
                'id_siswa' => $siswa->id,
                'tanggal' => $todayDate,
                'waktu_masuk' => $currentTime,
                'status' => $status,
                'metode_scan' => $metodeScan,
            ]);

            // Kirim notifikasi WhatsApp otomatis ke orang tua dengan perlindungan anti-spam
            $waResult = $this->sendPresensiNotification($siswa, $status, $currentTimeShort, $todayDate);

            // Sinkronkan ke Google Spreadsheet sheet 'absensi' jika webhook dikonfigurasi
            $this->syncToGoogleSheet($siswa, $status, $currentTime, $todayDate);

            $waNoteSuffix = '';
            if (! empty($waResult['note'])) {
                $waNoteSuffix = " ({$waResult['note']})";
            }

            return [
                'success' => true,
                'type' => 'masuk',
                'status' => $status,
                'message' => "Absen masuk berhasil. Status: {$status} pada {$currentTimeShort}.{$waNoteSuffix}",
                'data' => [
                    'nisn' => $siswa->nisn,
                    'nama_lengkap' => $siswa->nama_lengkap,
                    'kelas' => $siswa->kelas?->nama_kelas,
                    'waktu_masuk' => $currentTimeShort,
                    'status' => $status,
                    'wa_status' => $waResult['status'],
                    'wa_message' => $waResult['message'],
                    'wa_target' => $waResult['target'] ?? null,
                ],
            ];
        }

        // Kasus B: Siswa sudah melakukan presensi hari ini (TPQ hanya presensi masuk)
        return [
            'success' => false,
            'message' => "Santri [{$siswa->nama_lengkap}] sudah melakukan presensi hari ini pada ".substr((string) $absensi->waktu_masuk, 0, 5)." WIB (Status: {$absensi->status}).",
            'data' => [
                'nisn' => $siswa->nisn,
                'nama_lengkap' => $siswa->nama_lengkap,
                'kelas' => $siswa->kelas?->nama_kelas,
                'waktu_masuk' => substr((string) $absensi->waktu_masuk, 0, 5),
                'status' => $absensi->status,
            ],
        ];
    }

    /**
     * Mengirim notifikasi WhatsApp otomatis ke orang tua santri dengan jeda anti-spam cerdas
     *
     * @return array{status: string, message: string, note: ?string, target: ?string}
     */
    protected function sendPresensiNotification(Siswa $siswa, string $status, string $waktuShort, string $tanggal): array
    {
        // 1. Periksa apakah fitur notifikasi WA aktif di pengaturan
        $isWaEnabled = Pengaturan::getValue('wa_notif_presensi', '1') !== '0';
        if (! $isWaEnabled) {
            return [
                'status' => 'disabled',
                'message' => 'Notifikasi WhatsApp otomatis dinonaktifkan di pengaturan.',
                'note' => null,
                'target' => null,
            ];
        }

        // 2. Periksa apakah token Fonnte sudah dikonfigurasi
        $fonnteToken = Pengaturan::getValue('fonnte_token');
        if (empty($fonnteToken)) {
            return [
                'status' => 'no_token',
                'message' => 'Token WhatsApp Fonnte belum dikonfigurasi.',
                'note' => null,
                'target' => null,
            ];
        }

        // 3. Periksa nomor telepon orang tua
        $noHpOrtu = trim((string) ($siswa->no_hp_ortu ?? ''));
        if (empty($noHpOrtu)) {
            return [
                'status' => 'no_phone',
                'message' => 'Nomor WhatsApp orang tua belum terdaftar.',
                'note' => 'No. WA wali belum ada',
                'target' => null,
            ];
        }

        // 4. Hitung Jeda Waktu (Anti-Spam Delay) bertingkat menggunakan Cache
        // Mencegah deteksi spam oleh WhatsApp & Fonnte saat puluhan santri scan berurutan
        try {
            $nowTs = time();
            $lastScheduledTs = (int) Cache::get('fonnte_last_presensi_scheduled_time', 0);

            // Jeda dasar per pesan (misal: 3-5 detik)
            $configuredDelayRange = Pengaturan::getValue('wa_notif_delay', '3-5');
            $minDelay = 3;
            $maxDelay = 5;
            if (str_contains($configuredDelayRange, '-')) {
                $parts = explode('-', $configuredDelayRange);
                $minDelay = max(1, (int) trim($parts[0]));
                $maxDelay = max($minDelay, (int) trim($parts[1] ?? (string) $minDelay));
            } elseif (is_numeric($configuredDelayRange)) {
                $minDelay = max(1, (int) $configuredDelayRange);
                $maxDelay = $minDelay;
            }

            $stepInterval = random_int($minDelay, $maxDelay);

            if ($lastScheduledTs < $nowTs) {
                // Belum ada antrean pesan berjalan, berikan jeda awal 1-2 detik
                $scheduledTs = $nowTs + random_int(1, 2);
            } else {
                // Ada santri lain yang baru saja scan beberapa saat lalu!
                // Geser jadwal santri ini setelah santri sebelumnya + interval acak
                $scheduledTs = $lastScheduledTs + $stepInterval;
            }

            // Simpan jadwal santri ini ke cache selama 10 menit
            Cache::put('fonnte_last_presensi_scheduled_time', $scheduledTs, 600);

            $delaySeconds = max(1, $scheduledTs - $nowTs);

            // 5. Susun template pesan
            $formattedDate = Carbon::parse($tanggal)->locale('id')->isoFormat('D MMMM YYYY');
            $pesan = $this->waService->buildPesanPresensi(
                $siswa->nama_lengkap,
                $status,
                $waktuShort,
                $formattedDate
            );

            // 6. Kirim ke Fonnte dengan parameter delay anti-spam
            $metadata = [
                'nama_penerima' => $siswa->nama_ortu ?: ('Wali '.$siswa->nama_lengkap),
                'tipe_pesan' => 'presensi',
                'delay' => (string) $delaySeconds,
            ];

            $res = $this->waService->sendViaFonnte($noHpOrtu, $pesan, $fonnteToken, $metadata);

            if ($res['success'] ?? false) {
                return [
                    'status' => 'sent',
                    'message' => "Notifikasi WA berhasil dijadwalkan (Jeda {$delaySeconds} detik).",
                    'note' => "WA terjadwal (jeda {$delaySeconds}s)",
                    'target' => $noHpOrtu,
                ];
            }

            return [
                'status' => 'failed',
                'message' => $res['message'] ?? 'Gagal mengirim notifikasi WA ke gateway.',
                'note' => 'Gagal WA: '.($res['message'] ?? 'Error gateway'),
                'target' => $noHpOrtu,
            ];
        } catch (\Throwable $e) {
            Log::warning('Error saat proses notifikasi WA presensi santri: '.$e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Kendala gateway WA: '.$e->getMessage(),
                'note' => null,
                'target' => $noHpOrtu,
            ];
        }
    }

    /**
     * Mengirim baris data absensi ke Google Spreadsheet via Apps Script Web App
     */
    public function syncToGoogleSheet(
        Siswa $siswa,
        string $status,
        string $currentTime,
        string $todayDate,
        ?string $jamPulang = '-',
        ?string $keteranganWaktu = null
    ): void {
        $webhookUrl = Pengaturan::getValue('spreadsheet_absensi_webhook_url');
        if (empty($webhookUrl)) {
            return;
        }

        try {
            $jamTerlambat = Pengaturan::getValue('jam_terlambat', '07:15');
            $currentTimeShort = substr($currentTime, 0, 5);

            if (! $keteranganWaktu) {
                if ($status === 'Hadir') {
                    $keteranganWaktu = ($currentTimeShort <= $jamTerlambat) ? 'Tepat Waktu' : 'Terlambat';
                } elseif ($status === 'Terlambat') {
                    $keteranganWaktu = 'Terlambat';
                } else {
                    $keteranganWaktu = '-';
                }
            }

            // Format tanggal persis m/d/Y seperti pada Apps Script user (misal 10/28/2026)
            $formattedDate = Carbon::parse($todayDate)->format('m/d/Y');

            $payload = [
                'action' => 'syncAbsensi',
                'tanggal' => $formattedDate,
                'nisn' => (string) $siswa->nisn,
                'nama' => (string) $siswa->nama_lengkap,
                'kelas' => (string) ($siswa->kelas?->nama_kelas ?: '-'),
                'jam_datang' => in_array($status, ['Hadir', 'Terlambat'], true) ? $currentTime : '-',
                'jam_pulang' => $jamPulang ?: '-',
                'keterangan_waktu' => $keteranganWaktu,
                'status' => $status,
            ];

            // Kirim secara non-blocking dengan timeout singkat (3 detik) agar scan tetap super cepat
            Http::timeout(3)->post($webhookUrl, $payload);
        } catch (\Throwable $e) {
            Log::warning('Gagal sinkronisasi data presensi ke Google Sheet: '.$e->getMessage());
        }
    }

    /**
     * Mengambil data pemantauan kehadiran realtime hari ini
     */
    public function getMonitoring(?int $idKelas = null, ?string $targetDate = null): array
    {
        $date = $targetDate ?: Carbon::now('Asia/Jakarta')->format('Y-m-d');

        $query = Siswa::with(['kelas', 'absensi' => function ($q) use ($date) {
            $q->where('tanggal', $date);
        }])->where('status', 'aktif');

        if ($idKelas) {
            $query->where('id_kelas', $idKelas);
        }

        $siswaList = $query->orderBy('id_kelas')->orderBy('nama_lengkap')->get();

        $stats = [
            'total' => $siswaList->count(),
            'hadir' => 0,
            'terlambat' => 0,
            'sakit' => 0,
            'izin' => 0,
            'alpa' => 0,
            'belum_absen' => 0,
        ];

        $items = $siswaList->map(function ($s) use (&$stats) {
            $absen = $s->absensi->first();
            $status = $absen ? $absen->status : 'Belum Absen';

            if ($absen) {
                match ($absen->status) {
                    'Hadir' => $stats['hadir']++,
                    'Terlambat' => $stats['terlambat']++,
                    'Sakit' => $stats['sakit']++,
                    'Izin' => $stats['izin']++,
                    'Alpa' => $stats['alpa']++,
                    default => null,
                };
            } else {
                $stats['belum_absen']++;
            }

            return [
                'id_siswa' => $s->id,
                'nisn' => $s->nisn,
                'nama_lengkap' => $s->nama_lengkap,
                'jenis_kelamin' => $s->jenis_kelamin,
                'id_kelas' => $s->id_kelas,
                'nama_kelas' => $s->kelas?->nama_kelas,
                'warna_label' => $s->kelas?->warna_label ?: '#3b82f6',
                'status' => $status,
                'waktu_masuk' => $absen?->waktu_masuk ? substr((string) $absen->waktu_masuk, 0, 5) : null,
                'waktu_pulang' => $absen?->waktu_pulang ? substr((string) $absen->waktu_pulang, 0, 5) : null,
                'keterangan' => $absen?->keterangan,
            ];
        });

        return [
            'tanggal' => $date,
            'stats' => $stats,
            'students' => $items,
        ];
    }

    /**
     * Menghasilkan Rekap Matrix Absensi (Hari 1 s/d 31 dalam 1 bulan)
     */
    public function getMatrixReport(int $bulan, int $tahun, ?int $idKelas = null): array
    {
        $startDate = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth();
        $daysInMonth = $startDate->daysInMonth;
        $endDate = $startDate->copy()->endOfMonth();

        // Hitung hari belajar aktif dalam bulan berjalan (mengabaikan hari libur & hari non-aktif)
        $hariAktifStr = Pengaturan::getValue('hari_aktif', 'Senin,Selasa,Rabu,Kamis,Jumat,Sabtu');
        $activeDaysList = array_map('trim', explode(',', strtolower($hariAktifStr)));
        $dayNamesIndo = [
            0 => 'minggu',
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
        ];

        $holidays = HariLibur::where('tanggal_mulai', '<=', $endDate->format('Y-m-d'))
            ->where('tanggal_selesai', '>=', $startDate->format('Y-m-d'))
            ->get();

        $effectiveDaysCount = 0;
        $effectiveDaysMap = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $curDate = Carbon::createFromDate($tahun, $bulan, $d);
            $dayName = $dayNamesIndo[$curDate->dayOfWeek] ?? '';
            $dateStr = $curDate->format('Y-m-d');

            $isDayOff = ! in_array($dayName, $activeDaysList, true);
            $isHoliday = $holidays->contains(fn ($h) => $dateStr >= $h->tanggal_mulai && $dateStr <= $h->tanggal_selesai);

            $isEffective = (! $isDayOff && ! $isHoliday);
            $effectiveDaysMap[$d] = $isEffective;
            if ($isEffective) {
                $effectiveDaysCount++;
            }
        }
        $divisor = $effectiveDaysCount > 0 ? $effectiveDaysCount : $daysInMonth;

        $query = Siswa::with(['kelas', 'absensi' => function ($q) use ($startDate, $endDate) {
            $q->whereBetween('tanggal', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
        }])->where('status', 'aktif');

        if ($idKelas) {
            $query->where('id_kelas', $idKelas);
        }

        $siswaList = $query->orderBy('id_kelas')->orderBy('nama_lengkap')->get();

        $rows = $siswaList->map(function ($s) use ($daysInMonth, $divisor) {
            $absensiByDay = [];
            foreach ($s->absensi as $absen) {
                $day = (int) Carbon::parse($absen->tanggal)->format('j');
                $code = match ($absen->status) {
                    'Hadir' => 'H',
                    'Terlambat' => 'T',
                    'Sakit' => 'S',
                    'Izin' => 'I',
                    'Alpa' => 'A',
                    default => '-'
                };
                $absensiByDay[$day] = $code;
            }

            $h = 0;
            $t = 0;
            $sakit = 0;
            $izin = 0;
            $alpa = 0;
            $daily = [];

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $c = $absensiByDay[$d] ?? '-';
                $daily[$d] = $c;
                if ($c === 'H') {
                    $h++;
                } elseif ($c === 'T') {
                    $t++;
                } elseif ($c === 'S') {
                    $sakit++;
                } elseif ($c === 'I') {
                    $izin++;
                } elseif ($c === 'A') {
                    $alpa++;
                }
            }

            $totalMasuk = $h + $t;
            $persentase = round(min(100, ($totalMasuk / $divisor) * 100), 1);

            return [
                'id_siswa' => $s->id,
                'nisn' => $s->nisn,
                'nama_lengkap' => $s->nama_lengkap,
                'nama_kelas' => $s->kelas?->nama_kelas,
                'daily' => $daily,
                'summary' => [
                    'hadir' => $h,
                    'terlambat' => $t,
                    'sakit' => $sakit,
                    'izin' => $izin,
                    'alpa' => $alpa,
                    'persentase' => $persentase,
                ],
            ];
        });

        return [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'days_in_month' => $daysInMonth,
            'effective_days' => $effectiveDaysCount,
            'data' => $rows,
        ];
    }
}
