<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\Siswa;
use App\Models\WhatsAppLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CheckAttendance
{
    /**
     * Ambil data lengkap pemeriksaan absensi 1 santri dari database (Hari ini, Tanggal khusus/kemarin, Rekap bulanan, dan Diagnostik Notifikasi WA).
     *
     * @param  array<string, mixed>  $period
     * @return array<string, mixed>
     */
    public function inspectStudentAttendance(Siswa $siswa, array $period, string $originalMessage = ''): array
    {
        $namaKelas = $siswa->kelas?->nama_kelas ?: 'Jilid';
        $targetMonth = (int) ($period['bulan'] ?? Carbon::now('Asia/Jakarta')->month);
        $targetYear = (int) ($period['tahun'] ?? Carbon::now('Asia/Jakarta')->year);

        $startOfMonth = Carbon::createFromDate($targetYear, $targetMonth, 1)->startOfMonth()->toDateString();
        $endOfMonth = Carbon::createFromDate($targetYear, $targetMonth, 1)->endOfMonth()->toDateString();

        $rekapBulan = Absensi::where('id_siswa', $siswa->id)
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->get();

        $totalHadir = $rekapBulan->filter(fn ($r) => strtolower((string) $r->status) === 'hadir')->count();
        $totalTerlambat = $rekapBulan->filter(fn ($r) => strtolower((string) $r->status) === 'terlambat')->count();
        $totalIzin = $rekapBulan->filter(fn ($r) => strtolower((string) $r->status) === 'izin')->count();
        $totalSakit = $rekapBulan->filter(fn ($r) => strtolower((string) $r->status) === 'sakit')->count();
        $totalAlpa = $rekapBulan->filter(fn ($r) => strtolower((string) $r->status) === 'alpa')->count();

        $totalMasuk = $totalHadir + $totalTerlambat;
        $totalTidakMasuk = $totalIzin + $totalSakit + $totalAlpa;
        $totalHariKegiatan = $rekapBulan->count();
        $persentase = $totalHariKegiatan > 0
            ? (int) round(($totalMasuk / $totalHariKegiatan) * 100)
            : 0;

        $today = Carbon::today('Asia/Jakarta')->toDateString();
        $presensiHariIni = Absensi::where('id_siswa', $siswa->id)
            ->where('tanggal', $today)
            ->first();

        $presensiKhusus = null;
        if (! empty($period['is_specific_day']) && ! empty($period['tanggal_khusus'])) {
            $presensiKhusus = Absensi::where('id_siswa', $siswa->id)
                ->where('tanggal', $period['tanggal_khusus'])
                ->first();
        }

        $isAskingNotification = $this->isAskingAboutNotification($originalMessage);
        $notificationDiag = $isAskingNotification
            ? $this->diagnoseNotificationToday($siswa, $presensiHariIni)
            : null;

        return [
            'santri' => [
                'id' => $siswa->id,
                'nisn' => $siswa->nisn,
                'nama_lengkap' => $siswa->nama_lengkap,
                'kelas' => $namaKelas,
            ],
            'periode' => $period['label_periode'] ?? "Bulan {$targetMonth} {$targetYear}",
            'hari_ini' => [
                'tanggal' => $today,
                'tercatat' => (bool) $presensiHariIni,
                'status' => $presensiHariIni?->status,
                'waktu_masuk' => $presensiHariIni?->waktu_masuk,
                'keterangan' => $presensiHariIni?->keterangan,
            ],
            'tanggal_khusus' => $presensiKhusus ? [
                'tanggal' => $period['tanggal_khusus'],
                'label' => $period['label_hari_khusus'] ?? $period['tanggal_khusus'],
                'tercatat' => true,
                'status' => $presensiKhusus->status,
                'waktu_masuk' => $presensiKhusus->waktu_masuk,
            ] : (! empty($period['is_specific_day']) ? [
                'tanggal' => $period['tanggal_khusus'] ?? null,
                'label' => $period['label_hari_khusus'] ?? null,
                'tercatat' => false,
                'status' => null,
                'waktu_masuk' => null,
            ] : null),
            'rekap_bulanan' => [
                'hadir_tepat_waktu' => $totalHadir,
                'hadir_terlambat' => $totalTerlambat,
                'total_masuk' => $totalMasuk,
                'izin' => $totalIzin,
                'sakit' => $totalSakit,
                'alpa' => $totalAlpa,
                'total_tidak_masuk' => $totalTidakMasuk,
                'total_hari_tercatat' => $totalHariKegiatan,
                'persentase_kehadiran' => $persentase,
            ],
            'diagnostik_notifikasi' => $notificationDiag,
        ];
    }

    /**
     * Format teks laporan absensi 1 santri untuk balasan WhatsApp.
     *
     * @param  array<string, mixed>  $period
     */
    public function formatStudentAttendanceText(
        Siswa $siswa,
        string $hariTanggalStr,
        array $period,
        string $originalMessage = ''
    ): string {
        $data = $this->inspectStudentAttendance($siswa, $period, $originalMessage);
        $namaKelas = $data['santri']['kelas'];
        $rekap = $data['rekap_bulanan'];
        $totalHadir = $rekap['hadir_tepat_waktu'];
        $totalTerlambat = $rekap['hadir_terlambat'];
        $totalIzin = $rekap['izin'];
        $totalSakit = $rekap['sakit'];
        $totalAlpa = $rekap['alpa'];
        $totalTidakMasuk = $rekap['total_tidak_masuk'];
        $totalHariKegiatan = $rekap['total_hari_tercatat'];
        $persentase = $rekap['persentase_kehadiran'];

        $isAskingAbsentCount = $this->isAskingAbsentCount($originalMessage);
        $extraAbsentLine = $isAskingAbsentCount
            ? "• Total Tidak Masuk: *{$totalTidakMasuk} kali* (Izin: {$totalIzin}, Sakit: {$totalSakit}, Alpa: {$totalAlpa})\n"
            : '';

        // 1. Jika wali menanyakan bulan sebelumnya atau bulan lampau tertentu
        if (! empty($period['is_previous_month'])) {
            $catatanText = ($totalHariKegiatan > 0)
                ? "📈 Persentase Kehadiran: *{$persentase}%* (Total {$totalHariKegiatan} hari tercatat)"
                : 'ℹ️ _(Belum ada data kegiatan mengaji tercatat pada periode ini)_';

            return "📋 *Absensi ananda {$siswa->nama_lengkap}* ({$namaKelas}):\n\n"
                ."📊 *Rekap {$period['label_periode']}:*\n"
                ."• Hadir Tepat Waktu: *{$totalHadir} kali*\n"
                ."• Hadir Terlambat: *{$totalTerlambat} kali*\n"
                ."• Izin: *{$totalIzin} kali*\n"
                ."• Sakit: *{$totalSakit} kali*\n"
                ."• Alpa: *{$totalAlpa} kali*\n"
                .$extraAbsentLine
                .$catatanText;
        }

        // 2. Jika wali menanyakan tanggal kemarin tertentu
        if (! empty($period['is_specific_day']) && ! empty($period['tanggal_khusus'])) {
            $khusus = $data['tanggal_khusus'];
            $statusKhususText = 'Tidak ada catatan presensi pada tanggal ini.';
            if (! empty($khusus['tercatat'])) {
                $statusKhususText = $this->formatSingleDayStatus((string) $khusus['status'], $khusus['waktu_masuk']);
            }

            return "📋 *Absensi ananda {$siswa->nama_lengkap}* ({$namaKelas}):\n\n"
                ."📅 *{$period['label_hari_khusus']}:*\n"
                ."Status: {$statusKhususText}\n\n"
                ."📊 *Rekap {$period['label_periode']}:*\n"
                ."• Hadir Tepat Waktu: {$totalHadir} hari\n"
                ."• Hadir Terlambat: {$totalTerlambat} hari\n"
                ."• Izin: {$totalIzin} hari\n"
                ."• Sakit: {$totalSakit} hari\n"
                ."• Alpa: {$totalAlpa} hari\n"
                .$extraAbsentLine
                ."📈 Persentase Kehadiran: *{$persentase}%*";
        }

        // 3. Default: Bulan berjalan saat ini (Status Hari Ini + Diagnostik Notifikasi jika ditanyakan + Rekap Bulan Ini)
        $hariIni = $data['hari_ini'];
        $statusHariIniText = 'Belum ada catatan presensi masuk hari ini.';
        if (! empty($hariIni['tercatat'])) {
            $statusHariIniText = $this->formatSingleDayStatus((string) $hariIni['status'], $hariIni['waktu_masuk']);
        }

        $notifSection = '';
        if (! empty($data['diagnostik_notifikasi']['penjelasan'])) {
            $notifSection = "\n🔔 *Info Notifikasi WA:* {$data['diagnostik_notifikasi']['penjelasan']}\n";
        }

        return "📋 *Absensi ananda {$siswa->nama_lengkap}* ({$namaKelas}):\n\n"
            ."📅 *Hari Ini ({$hariTanggalStr}):*\n"
            ."Status: {$statusHariIniText}\n"
            .$notifSection."\n"
            ."📊 *Rekap {$period['label_periode']}:*\n"
            ."• Hadir Tepat Waktu: {$totalHadir} hari\n"
            ."• Hadir Terlambat: {$totalTerlambat} hari\n"
            ."• Izin: {$totalIzin} hari\n"
            ."• Sakit: {$totalSakit} hari\n"
            ."• Alpa: {$totalAlpa} hari\n"
            .$extraAbsentLine
            ."📈 Persentase Kehadiran: *{$persentase}%*";
    }

    /**
     * Format teks laporan absensi untuk wali yang memiliki lebih dari 1 santri.
     *
     * @param  Collection<int, Siswa>  $candidates
     * @param  array<string, mixed>  $period
     */
    public function formatMultiStudentAttendanceText(
        Collection $candidates,
        array $period,
        string $originalMessage = ''
    ): string {
        $hariTanggalStr = Carbon::now('Asia/Jakarta')->locale('id')->isoFormat('dddd, D MMMM Y');
        $sections = [];

        foreach ($candidates as $siswa) {
            $sections[] = $this->formatStudentAttendanceText($siswa, $hariTanggalStr, $period, $originalMessage);
        }

        $introPeriode = ! empty($period['is_previous_month']) ? " untuk {$period['label_periode']}" : '';

        return "Berikut informasi absensi ananda{$introPeriode}:\n\n"
            .implode("\n\n────────────────────\n\n", $sections);
    }

    /**
     * Susun payload terstruktur untuk Gemini Function Calling (`check_attendance`).
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
        $hariTanggalStr = Carbon::now('Asia/Jakarta')->locale('id')->isoFormat('dddd, D MMMM Y');

        if ($selectedSiswa) {
            $detail = $this->inspectStudentAttendance($selectedSiswa, $period, $originalMessage);
            $text = $this->formatStudentAttendanceText($selectedSiswa, $hariTanggalStr, $period, $originalMessage);

            return [
                'status' => 'ok',
                'santri' => $detail['santri'],
                'detail_absensi' => $detail,
                'data_absensi' => $text,
            ];
        }

        $details = [];
        foreach ($candidates as $cand) {
            $details[] = $this->inspectStudentAttendance($cand, $period, $originalMessage);
        }

        return [
            'status' => 'ok',
            'santri' => 'semua_anak_wali',
            'detail_absensi' => $details,
            'data_absensi' => $this->formatMultiStudentAttendanceText($candidates, $period, $originalMessage),
        ];
    }

    /**
     * Format status kehadiran harian beserta jam masuk.
     */
    protected function formatSingleDayStatus(string $status, ?string $waktuMasuk = null): string
    {
        $jam = $waktuMasuk ?: '-';
        $st = strtolower(trim($status));

        return match ($st) {
            'hadir' => "✅ *HADIR TEPAT WAKTU* (Pukul {$jam} WIB)",
            'terlambat' => "⚠️ *HADIR TERLAMBAT* (Pukul {$jam} WIB)",
            'izin' => '📝 *IZIN*',
            'sakit' => '🏥 *SAKIT*',
            'alpa' => '❌ *ALPA / TANPA KETERANGAN*',
            default => "*{$status}*",
        };
    }

    /**
     * Deteksi apakah wali menanyakan alasan belum menerima notifikasi WhatsApp absensi.
     */
    public function isAskingAboutNotification(string $message): bool
    {
        return (bool) preg_match('/\b(notifikasi|notif|pesan\s+wa|wa\s+absensi|belum\s+dapat\s+wa|blm\s+dpt\s+notif|belum\s+ada\s+notif|kenapa\s+belum\s+ada\s+notif)\b/i', $message);
    }

    /**
     * Deteksi apakah wali secara spesifik menanyakan jumlah ketidakhadiran ("tidak masuk berapa kali").
     */
    public function isAskingAbsentCount(string $message): bool
    {
        return (bool) preg_match('/\b(tidak\s+masuk|gak\s+masuk|nggak\s+masuk|absen\s+berapa\s+kali|berapa\s+kali\s+tidak\s+masuk|berapa\s+kali\s+gak\s+masuk|bolos\s+berapa)\b/i', $message);
    }

    /**
     * Periksa riwayat pengiriman WhatsAppLog hari ini untuk menjelaskan alasan notifikasi absensi belum diterima.
     *
     * @return array{status_log: string, penjelasan: string}
     */
    public function diagnoseNotificationToday(Siswa $siswa, ?Absensi $presensiHariIni): array
    {
        if (! $presensiHariIni) {
            return [
                'status_log' => 'belum_presensi',
                'penjelasan' => "Notifikasi belum terkirim karena ananda *{$siswa->nama_lengkap}* belum melakukan scan QR Code presensi masuk hari ini.",
            ];
        }

        $statusLower = strtolower((string) $presensiHariIni->status);
        if (in_array($statusLower, ['izin', 'sakit', 'alpa'], true)) {
            return [
                'status_log' => 'non_scan',
                'penjelasan' => 'Hari ini ananda tercatat dengan status *'.strtoupper($statusLower).'* (bukan melalui scan kartu QR Code kehadiran).',
            ];
        }

        $phoneNorm = WhatsAppService::normalizePhone($siswa->no_hp_ortu);
        $todayLog = null;
        if ($phoneNorm !== '') {
            $todayLog = WhatsAppLog::where('nomor_tujuan', $phoneNorm)
                ->whereDate('created_at', Carbon::today('Asia/Jakarta')->toDateString())
                ->where('tipe_pesan', '!=', 'chatbot_reply')
                ->latest('id')
                ->first();
        }

        $jamMasuk = $presensiHariIni->waktu_masuk ?: '-';

        if (! $todayLog) {
            return [
                'status_log' => 'tidak_tercatat_di_gateway',
                'penjelasan' => "Ananda sudah tercatat hadir pukul *{$jamMasuk} WIB*, namun pesan notifikasi otomatis belum tercatat di antrean WhatsApp Gateway.",
            ];
        }

        return match ($todayLog->status) {
            'failed' => [
                'status_log' => 'failed',
                'penjelasan' => "Ananda sudah hadir pukul *{$jamMasuk} WIB*, namun pengiriman notifikasi WA mengalami kendala pada gateway ({$todayLog->keterangan_error}).",
            ],
            'pending' => [
                'status_log' => 'pending',
                'penjelasan' => "Ananda sudah hadir pukul *{$jamMasuk} WIB* dan notifikasi WA saat ini sedang berada dalam antrean pengiriman.",
            ],
            default => [
                'status_log' => $todayLog->status,
                'penjelasan' => "Notifikasi presensi ananda (masuk pukul *{$jamMasuk} WIB*) telah dikirim oleh sistem dengan status *".strtoupper((string) $todayLog->status).'*.',
            ],
        };
    }
}
