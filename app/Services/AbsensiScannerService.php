<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Models\Siswa;
use Carbon\Carbon;

class AbsensiScannerService
{
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

            return [
                'success' => true,
                'type' => 'masuk',
                'status' => $status,
                'message' => "Absen masuk berhasil. Status: {$status} pada {$currentTimeShort}.",
                'data' => [
                    'nisn' => $siswa->nisn,
                    'nama_lengkap' => $siswa->nama_lengkap,
                    'kelas' => $siswa->kelas?->nama_kelas,
                    'waktu_masuk' => $currentTimeShort,
                    'status' => $status,
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
