<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\Agenda;
use App\Models\ChatbotLog;
use App\Models\HariLibur;
use App\Models\Pengaturan;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ChatbotService
{
    protected array $bulanMap = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * Normalisasi nomor telepon ke format internasional 628...
     */
    public function normalizePhone(?string $phone): string
    {
        return WhatsAppService::normalizePhone($phone);
    }

    /**
     * Memproses pesan masuk dari WhatsApp dan menghasilkan respon otomatis
     */
    public function handleIncomingMessage(string $sender, string $message): array
    {
        $normalizedSender = $this->normalizePhone($sender);
        $trimmedMessage = trim($message);

        if (empty($trimmedMessage)) {
            return [
                'success' => false,
                'intent' => 'unknown',
                'reply' => 'Pesan tidak boleh kosong.',
                'siswa' => null,
            ];
        }

        // 1. Deteksi apakah pesan adalah pertanyaan umum institusi (Agenda / Hari Libur)
        $intent = $this->detectIntent($trimmedMessage);
        if ($intent === 'agenda') {
            $reply = $this->handleAgendaQuery();
            $log = $this->logInteraction($normalizedSender, $trimmedMessage, $intent, null, $reply, 'sukses');

            return [
                'success' => true,
                'intent' => $intent,
                'reply' => $reply,
                'siswa' => null,
                'log_id' => $log->id,
            ];
        }

        // 2. Identifikasi Santri berdasarkan nomor pengirim atau sesi
        $resolved = $this->resolveStudent($normalizedSender, $trimmedMessage);

        if ($resolved['status'] === 'multi_selection_prompt') {
            $reply = $resolved['prompt'];
            $intent = 'multi_santri';
            $log = $this->logInteraction($normalizedSender, $trimmedMessage, $intent, null, $reply, 'sukses');

            return [
                'success' => true,
                'intent' => $intent,
                'reply' => $reply,
                'siswa' => null,
                'log_id' => $log->id,
            ];
        }

        if ($resolved['status'] === 'unregistered') {
            $reply = $this->buildUnregisteredReply($normalizedSender);
            $intent = 'unregistered';
            $log = $this->logInteraction($normalizedSender, $trimmedMessage, $intent, null, $reply, 'sukses');

            return [
                'success' => true,
                'intent' => $intent,
                'reply' => $reply,
                'siswa' => null,
                'log_id' => $log->id,
            ];
        }

        /** @var Siswa|null $siswa */
        $siswa = $resolved['siswa'] ?? null;

        // Jika pesan adalah konfirmasi pemilihan anak (cth: "1", "2")
        if (isset($resolved['is_selection_reply']) && $resolved['is_selection_reply'] && $intent === 'unknown') {
            $intent = 'bantuan';
        }

        // 3. Bangun Respon berdasarkan Intent
        $reply = match ($intent) {
            'spp_notice' => $this->handleSppNoticeQuery($siswa),
            'absensi' => $siswa ? $this->handleAbsensiQuery($siswa) : $this->handleHelpQuery(null),
            'bantuan' => $this->handleHelpQuery($siswa),
            default => $this->handleUnknownQuery($siswa, $trimmedMessage),
        };

        // 4. Catat riwayat ke log
        $log = $this->logInteraction(
            $normalizedSender,
            $trimmedMessage,
            $intent,
            $siswa?->id,
            $reply,
            'sukses'
        );

        return [
            'success' => true,
            'intent' => $intent,
            'reply' => $reply,
            'siswa' => $siswa ? [
                'id' => $siswa->id,
                'nisn' => $siswa->nisn,
                'nama_lengkap' => $siswa->nama_lengkap,
                'kelas' => $siswa->kelas?->nama_kelas,
            ] : null,
            'log_id' => $log->id,
        ];
    }

    /**
     * Klasifikasi intent dari teks pesan
     */
    public function detectIntent(string $message): string
    {
        $clean = strtolower($message);

        // Intent Pertanyaan SPP & Keuangan (Arahkan ke kantor sekretariat)
        if (preg_match('/\b(tunggakan|spp|tagihan|biaya|bayar|lunas|utang|piutang|belum bayar|kurang bayar)\b/i', $clean)) {
            return 'spp_notice';
        }

        // Intent Absensi & Kehadiran
        if (preg_match('/\b(presensi|absensi|kehadiran|absen|masuk|hadir|bolos|izin|sakit|alpa)\b/i', $clean)) {
            return 'absensi';
        }

        // Intent Agenda & Hari Libur
        if (preg_match('/\b(libur|agenda|kegiatan|acara|jadwal|kapan libur|kalender)\b/i', $clean)) {
            return 'agenda';
        }

        // Intent Bantuan & Sapaan
        if (preg_match('/\b(halo|hai|assalam|assalamualaikum|menu|bantuan|info|bisa apa|tanya)\b/i', $clean)) {
            return 'bantuan';
        }

        return 'unknown';
    }

    /**
     * Menyelesaikan santri mana yang terhubung dengan nomor WhatsApp pengirim
     */
    public function resolveStudent(string $normalizedPhone, string $message): array
    {
        // 1. Cek apakah ada pencarian NISN langsung dalam pesan (misal: "NISN 1001" atau "1001")
        if (preg_match('/\bnisn\s*[:=]?\s*([0-9]{3,15})\b/i', $message, $m)) {
            $siswaByNisn = Siswa::with('kelas')->where('nisn', $m[1])->first();
            if ($siswaByNisn) {
                Cache::put("wa_active_student_{$normalizedPhone}", $siswaByNisn->id, now()->addMinutes(15));

                return ['status' => 'found', 'siswa' => $siswaByNisn];
            }
        }

        // 2. Cari santri berdasarkan no_hp_ortu (menggunakan B-Tree index terlebih dahulu)
        $localPhone = '0'.substr($normalizedPhone, 2);
        $candidates = Siswa::with('kelas')
            ->whereIn('no_hp_ortu', [$normalizedPhone, $localPhone])
            ->get();

        // Fallback pencarian akhiran nomor jika format di database belum ternormalisasi
        if ($candidates->isEmpty() && strlen($normalizedPhone) >= 9) {
            $last9Digits = substr($normalizedPhone, -9);
            $candidates = Siswa::with('kelas')
                ->where('no_hp_ortu', 'like', "%{$last9Digits}")
                ->get();
        }

        if ($candidates->isEmpty()) {
            return ['status' => 'unregistered'];
        }

        // Jika hanya ada 1 anak
        if ($candidates->count() === 1) {
            return ['status' => 'found', 'siswa' => $candidates->first()];
        }

        // Jika memiliki 2 anak atau lebih (Multi-Santri)
        $cleanMsg = trim($message);

        // Jika pesan adalah angka pilihan (1, 2, 3...)
        if (is_numeric($cleanMsg)) {
            $index = (int) $cleanMsg - 1;
            if ($candidates->has($index)) {
                $chosen = $candidates->get($index);
                Cache::put("wa_active_student_{$normalizedPhone}", $chosen->id, now()->addMinutes(15));

                return [
                    'status' => 'found',
                    'siswa' => $chosen,
                    'is_selection_reply' => true,
                ];
            }
        }

        // Cek apakah ada nama anak yang diketik langsung
        foreach ($candidates as $cand) {
            if (stripos($cleanMsg, $cand->nama_lengkap) !== false) {
                Cache::put("wa_active_student_{$normalizedPhone}", $cand->id, now()->addMinutes(15));

                return ['status' => 'found', 'siswa' => $cand];
            }
        }

        // Cek sesi aktif di cache
        $cachedId = Cache::get("wa_active_student_{$normalizedPhone}");
        if ($cachedId) {
            $cachedStudent = $candidates->firstWhere('id', $cachedId);
            if ($cachedStudent) {
                return ['status' => 'found', 'siswa' => $cachedStudent];
            }
        }

        // Jika belum ada pilihan anak aktif, tampilkan prompt pilihan
        $prompt = "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\n"
            ."Nomor WhatsApp Anda terdaftar sebagai wali dari *{$candidates->count()} santri* di TPQ:\n";

        foreach ($candidates as $i => $cand) {
            $num = $i + 1;
            $namaKelas = $cand->kelas?->nama_kelas ?: '-';
            $prompt .= "{$num}. *{$cand->nama_lengkap}* ({$namaKelas})\n";
        }

        $prompt .= "\nSilakan ketik angka *1*".($candidates->count() > 1 ? " sampai *{$candidates->count()}*" : '').' untuk memilih data santri yang ingin Anda tanyakan.';

        return [
            'status' => 'multi_selection_prompt',
            'candidates' => $candidates,
            'prompt' => $prompt,
        ];
    }

    /**
     * Menghasilkan balasan informasi terkait SPP & Keuangan (diarahkan ke pengurus)
     */
    public function handleSppNoticeQuery(?Siswa $siswa): string
    {
        $namaInstansi = Pengaturan::getValue('nama_instansi', 'TPQ & Lembaga');
        $santriStr = $siswa ? " ananda *{$siswa->nama_lengkap}*" : '';

        return "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\n"
            ."Yth. Bapak/Ibu Wali{$santriStr},\n\n"
            ."Layanan WhatsApp Bot *{$namaInstansi}* difokuskan untuk *Presensi / Kehadiran Santri Berbasis QR Code*, *Jadwal Agenda Kegiatan*, serta *Informasi Hari Libur TPQ*.\n\n"
            ."Untuk urusan administrasi, SPP, atau keuangan lainnya, silakan langsung menghubungi pengurus/kantor sekretariat lembaga. Jazakumullah khairan katsiran.\n\n"
            ."Wassalamu'alaikum Warahmatullahi Wabarakatuh.";
    }

    /**
     * Menghasilkan balasan informasi presensi kehadiran santri
     */
    public function handleAbsensiQuery(Siswa $siswa): string
    {
        $today = Carbon::today()->toDateString();
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $namaBulan = $this->bulanMap[$currentMonth] ?? 'Bulan Ini';
        $namaKelas = $siswa->kelas?->nama_kelas ?: 'Jilid';

        // 1. Presensi Hari Ini (memanfaatkan composite index id_siswa + tanggal)
        $presensiHariIni = Absensi::where('id_siswa', $siswa->id)
            ->where('tanggal', $today)
            ->first();

        $statusHariIniText = 'Belum ada catatan presensi hari ini.';
        if ($presensiHariIni) {
            $jam = $presensiHariIni->waktu_masuk ?: '-';
            $st = strtolower($presensiHariIni->status);
            $statusHariIniText = match ($st) {
                'hadir' => "✅ *HADIR TEPAT WAKTU* (Pukul {$jam} WIB)",
                'terlambat' => "⚠️ *HADIR TERLAMBAT* (Pukul {$jam} WIB)",
                'izin' => '📝 *IZIN*',
                'sakit' => '🏥 *SAKIT*',
                'alpa' => '❌ *ALPA / TANPA KETERANGAN*',
                default => "*{$presensiHariIni->status}*",
            };
        }

        // 2. Rekapitulasi Bulan Berjalan (sargable range query pada index id_siswa + tanggal)
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();
        $rekapBulan = Absensi::where('id_siswa', $siswa->id)
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->get();

        $totalHadir = $rekapBulan->filter(fn ($r) => strtolower($r->status) === 'hadir')->count();
        $totalTerlambat = $rekapBulan->filter(fn ($r) => strtolower($r->status) === 'terlambat')->count();
        $totalIzin = $rekapBulan->filter(fn ($r) => strtolower($r->status) === 'izin')->count();
        $totalSakit = $rekapBulan->filter(fn ($r) => strtolower($r->status) === 'sakit')->count();
        $totalAlpa = $rekapBulan->filter(fn ($r) => strtolower($r->status) === 'alpa')->count();

        $totalHariKegiatan = $rekapBulan->count();
        $persentase = $totalHariKegiatan > 0
            ? round((($totalHadir + $totalTerlambat) / $totalHariKegiatan) * 100)
            : 0;

        $hariTanggalStr = Carbon::now()->locale('id')->isoFormat('dddd, D MMMM Y');

        return "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\n"
            ."Berikut laporan presensi ananda *{$siswa->nama_lengkap}* ({$namaKelas}):\n\n"
            ."📅 *Hari Ini ({$hariTanggalStr}):*\n"
            ."Status: {$statusHariIniText}\n\n"
            ."📊 *Rekapitulasi Bulan {$namaBulan} {$currentYear}:*\n"
            ."• Hadir: {$totalHadir} kali\n"
            ."• Terlambat: {$totalTerlambat} kali\n"
            ."• Izin: {$totalIzin} kali\n"
            ."• Sakit: {$totalSakit} kali\n"
            ."• Alpa: {$totalAlpa} kali\n"
            ."📈 Persentase Kehadiran: *{$persentase}%*\n\n"
            .'Semoga ananda senantiasa istiqamah dalam menuntut ilmu Al-Qur\'an. Jazakumullah khairan.';
    }

    /**
     * Menghasilkan informasi kegiatan dan kalender libur
     */
    public function handleAgendaQuery(): string
    {
        $today = Carbon::today()->toDateString();

        $liburMendatang = HariLibur::where('tanggal_selesai', '>=', $today)
            ->orderBy('tanggal_mulai', 'asc')
            ->limit(3)
            ->get();

        $agendaMendatang = Agenda::where('tanggal', '>=', $today)
            ->orderBy('tanggal', 'asc')
            ->limit(3)
            ->get();

        $reply = "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\n"
            ."📅 *INFORMASI JADWAL & AGENDA TPQ:*\n\n";

        if ($liburMendatang->isNotEmpty()) {
            $reply .= "🏖️ *Hari Libur Terdekat:*\n";
            foreach ($liburMendatang as $lib) {
                $mulai = Carbon::parse($lib->tanggal_mulai)->locale('id')->isoFormat('D MMM Y');
                $selesai = Carbon::parse($lib->tanggal_selesai)->locale('id')->isoFormat('D MMM Y');
                $tglStr = ($mulai === $selesai) ? $mulai : "{$mulai} s.d. {$selesai}";
                $reply .= "• {$tglStr}: *{$lib->keterangan}*\n";
            }
            $reply .= "\n";
        } else {
            $reply .= "🏖️ Tidak ada jadwal libur dalam waktu dekat.\n\n";
        }

        if ($agendaMendatang->isNotEmpty()) {
            $reply .= "📋 *Agenda Kegiatan Terdekat:*\n";
            foreach ($agendaMendatang as $ag) {
                $tgl = Carbon::parse($ag->tanggal)->locale('id')->isoFormat('dddd, D MMMM Y');
                $reply .= "• *{$ag->judul}* ({$tgl})\n";
                if ($ag->keterangan) {
                    $reply .= "  {$ag->keterangan}\n";
                }
            }
        } else {
            $reply .= "📋 Tidak ada agenda khusus dalam waktu dekat.\n";
        }

        $reply .= "\nJazakumullah khairan katsiran.";

        return $reply;
    }

    /**
     * Pesan panduan bantuan islami
     */
    public function handleHelpQuery(?Siswa $siswa): string
    {
        $namaInstansi = Pengaturan::getValue('nama_instansi', 'TPQ Al-Qur\'an');
        $namaSantriStr = $siswa ? "Bapak/Ibu Wali dari *{$siswa->nama_lengkap}* ({$siswa->kelas?->nama_kelas})" : 'Bapak/Ibu Wali Santri';

        return "Assalamu'alaikum Warahmatullahi Wabarakatuh, {$namaSantriStr}.\n\n"
            ."Selamat datang di layanan informasi otomatis presensi *{$namaInstansi}*.\n\n"
            ."Anda dapat menanyakan hal-hal berikut:\n"
            ."1. *Cek Kehadiran Santri*: Ketik _\"Gimana absensi anak saya?\"_ atau _\"Cek kehadiran\"_\n"
            ."2. *Info Jadwal & Libur*: Ketik _\"Kapan libur berikutnya?\"_ atau _\"Info agenda\"_\n"
            ."3. *Panduan Lengkap*: Ketik _\"Bantuan\"_ atau _\"Menu\"_\n\n"
            .'Ketik kata kunci di atas kapan saja untuk memantau kehadiran ananda secara realtime. Jazakumullah khairan katsiran.';
    }

    /**
     * Balasan ketika pesan tidak dikenali
     */
    public function handleUnknownQuery(?Siswa $siswa, string $originalMessage): string
    {
        $santriName = $siswa ? "ananda *{$siswa->nama_lengkap}*" : 'ananda';

        return "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\n"
            ."Mohon maaf, sistem informasi presensi kami belum memahami maksud pesan: _\"{$originalMessage}\"_.\n\n"
            ."Untuk kemudahan Anda memeriksa data {$santriName}, silakan ketik salah satu kata kunci berikut:\n"
            ."• *Absensi* atau *Kehadiran* : untuk melihat catatan kehadiran\n"
            ."• *Libur* atau *Agenda* : untuk melihat jadwal kegiatan TPQ\n"
            ."• *Menu* : untuk panduan lengkap\n\n"
            .'Jazakumullah khairan katsiran.';
    }

    /**
     * Balasan untuk nomor pengirim yang belum terdaftar
     */
    public function buildUnregisteredReply(string $phone): string
    {
        $namaInstansi = Pengaturan::getValue('nama_instansi', 'TPQ & Lembaga');

        return "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\n"
            ."Mohon maaf, nomor WhatsApp Anda (*{$phone}*) belum terdaftar pada data orang tua / wali santri *{$namaInstansi}*.\n\n"
            ."Jika Anda adalah wali santri yang sah, Anda dapat menghubungkan nomor ini dengan mengetik:\n"
            ."*NISN [Nomor Induk]*\n"
            ."Contoh: *NISN 1001*\n\n"
            .'Atau silakan hubungi bagian administrasi TPQ untuk memperbarui nomor kontak Anda. Jazakumullah khairan.';
    }

    /**
     * Menyimpan riwayat percakapan ke tabel chatbot_logs
     */
    public function logInteraction(
        string $phone,
        string $message,
        string $intent,
        ?int $siswaId,
        string $reply,
        string $status = 'sukses',
        ?string $error = null
    ): ChatbotLog {
        return ChatbotLog::create([
            'no_pengirim' => $phone,
            'pesan_masuk' => $message,
            'intent_terdeteksi' => $intent,
            'id_siswa' => $siswaId,
            'pesan_keluar' => $reply,
            'status' => $status,
            'keterangan_error' => $error,
        ]);
    }
}
