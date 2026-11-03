<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\ChatbotLog;
use App\Models\HariLibur;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\TagihanSpp;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
     * Memproses pesan masuk dari WhatsApp dan menghasilkan respon otomatis yang luwes & kontekstual
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

        // 1. Deteksi intent awal dari teks pesan
        $intent = $this->detectIntent($trimmedMessage);

        // Pertanyaan umum institusi (Agenda & Hari Libur) langsung dijawab tanpa terhalang pemilihan multi-santri
        if ($intent === 'agenda') {
            $reply = $this->handleAgendaQuery($trimmedMessage);
            $log = $this->logInteraction($normalizedSender, $trimmedMessage, $intent, null, $reply, 'sukses');

            return [
                'success' => true,
                'intent' => $intent,
                'reply' => $reply,
                'siswa' => null,
                'log_id' => $log->id,
            ];
        }

        // Ucapan terima kasih / konfirmasi singkat ("baik terimakasih", "oke makasih", "alhamdulillah")
        // langsung dijawab dengan ramah tanpa memaksa memilih anak 1 atau 2
        if ($intent === 'apresiasi') {
            $candidates = $this->findCandidatesByPhone($normalizedSender);
            $firstSiswa = $candidates->count() === 1 ? $candidates->first() : null;
            $reply = $this->handleApresiasiQuery($candidates);
            $log = $this->logInteraction($normalizedSender, $trimmedMessage, $intent, $firstSiswa?->id, $reply, 'sukses');

            return [
                'success' => true,
                'intent' => $intent,
                'reply' => $reply,
                'siswa' => $firstSiswa ? $this->formatSiswaPayload($firstSiswa) : null,
                'log_id' => $log->id,
            ];
        }

        // 2. Identifikasi Santri berdasarkan nomor pengirim, nama panggilan, nomor urut, atau sesi database
        $resolved = $this->resolveStudent($normalizedSender, $trimmedMessage);

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

        // Jika wali menanyakan SPP/keuangan namun punya >1 anak dan belum memilih spesifik,
        // langsung berikan informasi layanan SPP untuk seluruh anak tanpa memaksa ketik 1 atau 2
        if ($resolved['status'] === 'multi_selection_prompt' && $intent === 'spp_notice') {
            /** @var Collection<int, Siswa> $candidates */
            $candidates = $resolved['candidates'];
            $firstSiswa = $candidates->first();
            $reply = $this->handleSppNoticeQuery($firstSiswa, $candidates);
            $log = $this->logInteraction($normalizedSender, $trimmedMessage, $intent, $firstSiswa?->id, $reply, 'sukses');

            return [
                'success' => true,
                'intent' => $intent,
                'reply' => $reply,
                'siswa' => $firstSiswa ? $this->formatSiswaPayload($firstSiswa) : null,
                'log_id' => $log->id,
            ];
        }

        // Jika wali meminta rekap "Semua" anak sekaligus saat punya >1 santri
        if ($resolved['status'] === 'found_all') {
            /** @var Collection<int, Siswa> $candidates */
            $candidates = $resolved['candidates'];
            $firstSiswa = $candidates->first();
            if ($intent === 'unknown' || $intent === 'bantuan') {
                $intent = 'absensi';
            }

            $reply = match ($intent) {
                'spp_notice' => $this->handleSppNoticeQuery($firstSiswa, $candidates),
                default => $this->handleMultiAbsensiQuery($candidates),
            };

            $log = $this->logInteraction($normalizedSender, $trimmedMessage, $intent, $firstSiswa?->id, $reply, 'sukses');

            return [
                'success' => true,
                'intent' => $intent,
                'reply' => $reply,
                'siswa' => $firstSiswa ? $this->formatSiswaPayload($firstSiswa) : null,
                'log_id' => $log->id,
            ];
        }

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

        /** @var Siswa|null $siswa */
        $siswa = $resolved['siswa'] ?? null;

        // Jika pesan adalah konfirmasi pemilihan anak (cth: "1", "2"),
        // periksa apakah sebelumnya wali sudah mengajukan pertanyaan spesifik (misal tanya absensi / SPP)
        if (! empty($resolved['is_selection_reply']) && $intent === 'unknown') {
            $pendingIntent = $this->resolvePendingIntentFromHistory($normalizedSender);
            $intent = $pendingIntent ?: 'bantuan';
        }

        // 3. Bangun Respon berdasarkan Intent
        $reply = match ($intent) {
            'spp_notice' => $this->handleSppNoticeQuery($siswa),
            'izin_sakit' => $this->handleIzinSakitQuery($siswa, $trimmedMessage),
            'absensi' => $siswa ? $this->handleAbsensiQuery($siswa, $trimmedMessage) : $this->handleHelpQuery(null),
            'bantuan' => $this->handleHelpQuery($siswa),
            default => $this->handleUnknownQuery($siswa, $trimmedMessage),
        };

        // 4. Catat riwayat ke log (sekaligus menyimpan id_siswa aktif di database untuk percakapan berikutnya)
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
            'siswa' => $siswa ? $this->formatSiswaPayload($siswa) : null,
            'log_id' => $log->id,
        ];
    }

    /**
     * Format data siswa untuk response JSON
     *
     * @return array{id: int, nisn: string|null, nama_lengkap: string, kelas: string|null}
     */
    protected function formatSiswaPayload(Siswa $siswa): array
    {
        return [
            'id' => $siswa->id,
            'nisn' => $siswa->nisn,
            'nama_lengkap' => $siswa->nama_lengkap,
            'kelas' => $siswa->kelas?->nama_kelas,
        ];
    }

    /**
     * Klasifikasi intent dari teks pesan bahasa sehari-hari wali santri
     * Hanya 3 informasi utama untuk wali santri: Absensi, SPP, dan Hari Libur
     */
    public function detectIntent(string $message): string
    {
        $clean = strtolower(trim($message));

        // 1. Intent Pertanyaan SPP & Tunggakan
        if (preg_match('/\b(nunggak|menunggak|tunggak|tunggakan|spp|tagihan|biaya|bayar|pembayaran|lunas|belum lunas|utang|hutang|piutang|infaq|syahriyah|iuran|belum bayar|kurang bayar|kuitansi|berapa bulan)\b/i', $clean)) {
            return 'spp_notice';
        }

        // 2. Intent Pemberitahuan Anak Izin / Sakit dari Wali Santri
        $isAskingRecap = (bool) preg_match('/\b(gimana|bagaimana|cek|rekap|berapa|laporan|info|apakah|apa|lihat|status)\b/i', $clean);
        $isInformingAbsence = (bool) preg_match('/\b(mohon izin|minta izin|mau izin|izin tidak|izin gak|izin nggak|tidak bisa hadir|tidak bisa masuk|tidak masuk|gak masuk|nggak masuk|absen dulu|lagi sakit|sedang sakit|karena sakit|kurang enak badan|demam|acara keluarga|bepergian)\b/i', $clean);
        if ($isInformingAbsence && ! $isAskingRecap) {
            return 'izin_sakit';
        }

        // 3. Intent Absensi & Kehadiran
        if (preg_match('/\b(presensi|absensi|kehadiran|absen|masuk|hadir|bolos|izin|sakit|alpa|alfa|terlambat|telat|rekap|datang|berangkat|pulang|sampai|ngaji|mengaji)\b/i', $clean)) {
            return 'absensi';
        }

        // 4. Intent Informasi Hari Libur (Hanya hari libur, tidak menampilkan agenda internal)
        if (preg_match('/\b(libur|liburan|tanggal merah|rutinan|kapan libur|kalender|jadwal libur|prei)\b/i', $clean)) {
            return 'agenda';
        }

        // 5. Intent Apresiasi / Terima Kasih / Konfirmasi Singkat
        $hasQuestionWord = (bool) preg_match('/\b(gimana|bagaimana|berapa|kapan|apakah|cek|menu|bantuan|tanya|bisa apa|halo|hai)\b/i', $clean);
        if (! $hasQuestionWord && preg_match('/\b(terima\s*kasih|terimakasih|makasih|mksh|trims|syukron|jazakallah|jazakumullah|alhamdulillah|baik|oke|ok|siap|noted|sama-sama|waalaikumsalam|wa\'alaikumussalam|walaikumsalam)\b/i', $clean)) {
            return 'apresiasi';
        }

        // 6. Intent Bantuan & Sapaan
        if (preg_match('/\b(halo|hai|assalam|assalamualaikum|assalamu\'alaikum|salam|pagi|siang|sore|malam|ustadz|ustazah|admin|menu|bantuan|help|info|bisa apa|tanya|panduan)\b/i', $clean)) {
            return 'bantuan';
        }

        return 'unknown';
    }

    /**
     * Cari daftar santri yang terhubung dengan nomor WA orang tua
     *
     * @return Collection<int, Siswa>
     */
    protected function findCandidatesByPhone(string $normalizedPhone): Collection
    {
        $localPhone = '0'.substr($normalizedPhone, 2);
        $candidates = Siswa::with('kelas')
            ->whereIn('no_hp_ortu', [$normalizedPhone, $localPhone])
            ->get();

        if ($candidates->isEmpty() && strlen($normalizedPhone) >= 9) {
            $last9Digits = substr($normalizedPhone, -9);
            $candidates = Siswa::with('kelas')
                ->where('no_hp_ortu', 'like', "%{$last9Digits}")
                ->get();
        }

        return $candidates;
    }

    /**
     * Simpan ID santri aktif ke Cache dan Pengaturan (agar tetap bertahan di serverless Vercel)
     */
    protected function rememberActiveStudent(string $normalizedPhone, int $siswaId): void
    {
        Cache::put("wa_active_student_{$normalizedPhone}", $siswaId, now()->addMinutes(30));

        try {
            $raw = Pengaturan::getValue('wa_active_student_map');
            $map = ! empty($raw) ? (json_decode((string) $raw, true) ?: []) : [];
            if (! is_array($map)) {
                $map = [];
            }
            $map[$normalizedPhone] = [
                'siswa_id' => $siswaId,
                'expires_at' => Carbon::now()->addMinutes(30)->timestamp,
            ];
            if (count($map) > 200) {
                $map = array_slice($map, -200, null, true);
            }
            Pengaturan::setValue('wa_active_student_map', json_encode($map));
        } catch (\Throwable) {
        }
    }

    /**
     * Ambil ID santri aktif dari Cache, Pengaturan, atau riwayat ChatbotLog 30 menit terakhir
     */
    protected function getRememberedActiveStudentId(string $normalizedPhone): ?int
    {
        $cachedId = Cache::get("wa_active_student_{$normalizedPhone}");
        if ($cachedId) {
            return (int) $cachedId;
        }

        try {
            $raw = Pengaturan::getValue('wa_active_student_map');
            if (! empty($raw)) {
                $map = json_decode((string) $raw, true);
                if (isset($map[$normalizedPhone]['siswa_id'], $map[$normalizedPhone]['expires_at'])) {
                    if ((int) $map[$normalizedPhone]['expires_at'] >= Carbon::now()->timestamp) {
                        return (int) $map[$normalizedPhone]['siswa_id'];
                    }
                }
            }
        } catch (\Throwable) {
        }

        // Cek juga dari tabel chatbot_logs dalam 30 menit terakhir
        $recentLogStudentId = ChatbotLog::where('no_pengirim', $normalizedPhone)
            ->whereNotNull('id_siswa')
            ->where('created_at', '>=', Carbon::now()->subMinutes(30))
            ->latest('id')
            ->value('id_siswa');

        return $recentLogStudentId ? (int) $recentLogStudentId : null;
    }

    /**
     * Cek apakah sebelum memilih angka 1/2, wali santri sudah menanyakan topik tertentu (misal absensi / SPP)
     */
    protected function resolvePendingIntentFromHistory(string $normalizedPhone): ?string
    {
        $lastMultiLog = ChatbotLog::where('no_pengirim', $normalizedPhone)
            ->where('intent_terdeteksi', 'multi_santri')
            ->where('created_at', '>=', Carbon::now()->subMinutes(30))
            ->latest('id')
            ->first();

        if (! $lastMultiLog || empty($lastMultiLog->pesan_masuk)) {
            return null;
        }

        $prevIntent = $this->detectIntent($lastMultiLog->pesan_masuk);
        if (in_array($prevIntent, ['absensi', 'spp_notice', 'izin_sakit', 'agenda'], true)) {
            return $prevIntent;
        }

        return null;
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
                $this->rememberActiveStudent($normalizedPhone, $siswaByNisn->id);

                return ['status' => 'found', 'siswa' => $siswaByNisn];
            }
        }

        // 2. Cari santri berdasarkan no_hp_ortu
        $candidates = $this->findCandidatesByPhone($normalizedPhone);

        if ($candidates->isEmpty()) {
            return ['status' => 'unregistered'];
        }

        // Jika hanya ada 1 anak, langsung gunakan tanpa perlu tanya
        if ($candidates->count() === 1) {
            return ['status' => 'found', 'siswa' => $candidates->first()];
        }

        // Jika memiliki 2 anak atau lebih (Multi-Santri)
        $cleanMsg = trim($message);
        $lowerMsg = strtolower($cleanMsg);

        // A. Jika wali mengetik "semua" / "keduanya" / "dua-duanya"
        if (preg_match('/\b(semua|semuanya|keduanya|dua-duanya|kedua anak|semua anak)\b/i', $lowerMsg)) {
            return [
                'status' => 'found_all',
                'candidates' => $candidates,
            ];
        }

        // B. Jika pesan adalah angka pilihan murni ("1", "2", "1.", "#1")
        if (preg_match('/^\s*#?([1-9])[\.\)]?\s*$/', $cleanMsg, $numMatch)) {
            $index = (int) $numMatch[1] - 1;
            if ($candidates->has($index)) {
                $chosen = $candidates->get($index);
                $this->rememberActiveStudent($normalizedPhone, $chosen->id);

                return [
                    'status' => 'found',
                    'siswa' => $chosen,
                    'is_selection_reply' => true,
                ];
            }
        }

        // C. Cek apakah ada nama lengkap atau nama panggilan (kata dari nama_lengkap >= 3 huruf) di dalam pesan
        foreach ($candidates as $cand) {
            if (stripos($cleanMsg, $cand->nama_lengkap) !== false) {
                $this->rememberActiveStudent($normalizedPhone, $cand->id);

                return ['status' => 'found', 'siswa' => $cand];
            }
        }

        foreach ($candidates as $cand) {
            $parts = preg_split('/\s+/', strtolower(trim($cand->nama_lengkap))) ?: [];
            foreach ($parts as $part) {
                $cleanPart = preg_replace('/[^a-z0-9]/i', '', $part);
                if (strlen($cleanPart) >= 3 && preg_match('/\b'.preg_quote($cleanPart, '/').'\b/i', $lowerMsg)) {
                    $this->rememberActiveStudent($normalizedPhone, $cand->id);

                    return ['status' => 'found', 'siswa' => $cand];
                }
            }
        }

        // D. Cek apakah wali menyertakan angka urut anak di dalam kalimat (cth: "berapa tunggakan SPP anak saya 1", "anak ke 2", "nomor 1")
        if (preg_match('/(?:\b(?:anak|santri|nomor|no|ke|pilihan)\s*(?:saya\s*)?(?:ke\s*)?[-]?\s*([1-9])\b|\b([1-9])\s*$)/i', $cleanMsg, $inlineNum)) {
            $numStr = ! empty($inlineNum[1]) ? $inlineNum[1] : ($inlineNum[2] ?? '');
            if ($numStr !== '') {
                $index = (int) $numStr - 1;
                if ($candidates->has($index)) {
                    $chosen = $candidates->get($index);
                    $this->rememberActiveStudent($normalizedPhone, $chosen->id);

                    return ['status' => 'found', 'siswa' => $chosen];
                }
            }
        }

        // E. Cek sesi aktif dari Cache / Pengaturan / ChatbotLog 30 menit terakhir
        $rememberedId = $this->getRememberedActiveStudentId($normalizedPhone);
        if ($rememberedId) {
            $cachedStudent = $candidates->firstWhere('id', $rememberedId);
            if ($cachedStudent) {
                return ['status' => 'found', 'siswa' => $cachedStudent];
            }
        }

        // F. Jika belum ada pilihan anak aktif:
        // Apabila wali langsung bertanya tentang "absensi" atau "spp_notice" tanpa menyebut nama anak, tampilkan langsung laporan seluruh anaknya
        // agar wali tidak perlu bolak-balik mengetik angka 1 atau 2!
        $detectedIntent = $this->detectIntent($cleanMsg);
        if (in_array($detectedIntent, ['absensi', 'spp_notice'], true)) {
            return [
                'status' => 'found_all',
                'candidates' => $candidates,
            ];
        }

        $prompt = "Nomor WhatsApp Anda terdaftar sebagai wali dari *{$candidates->count()} santri*:\n";

        foreach ($candidates as $i => $cand) {
            $num = $i + 1;
            $namaKelas = $cand->kelas?->nama_kelas ?: '-';
            $prompt .= "{$num}. *{$cand->nama_lengkap}* ({$namaKelas})\n";
        }

        $prompt .= "\nSilakan ketik angka *1*".($candidates->count() > 1 ? " sampai *{$candidates->count()}*" : '')
            .' (atau sebutkan *nama ananda*) untuk memilih santri yang ingin ditanyakan (*Absensi*, *SPP*, atau *Hari Libur*).';

        return [
            'status' => 'multi_selection_prompt',
            'candidates' => $candidates,
            'prompt' => $prompt,
        ];
    }

    /**
     * Menghasilkan balasan singkat ketika wali santri mengucapkan terima kasih / konfirmasi
     *
     * @param  Collection<int, Siswa>  $candidates
     */
    public function handleApresiasiQuery(Collection $candidates): string
    {
        $santriStr = '';
        if ($candidates->isNotEmpty()) {
            $names = $candidates->pluck('nama_lengkap')->map(fn ($n) => "*{$n}*")->implode(' & ');
            $santriStr = " Wali dari ananda {$names}";
        }

        return "Sama-sama Bapak/Ibu{$santriStr}. Silakan kirim pesan kembali jika ingin mengecek informasi *Absensi*, *SPP*, atau *Hari Libur*.";
    }

    /**
     * Menghasilkan balasan ketika wali santri mengabarkan anak izin / sakit
     */
    public function handleIzinSakitQuery(?Siswa $siswa, string $message): string
    {
        $santriStr = $siswa ? "ananda *{$siswa->nama_lengkap}* ({$siswa->kelas?->nama_kelas})" : 'ananda';
        $isSakit = (bool) preg_match('/\b(sakit|demam|batuk|pilek|panas|kurang enak badan|berobat|rawat)\b/i', $message);

        if ($isSakit) {
            return "Terima kasih atas informasinya Bapak/Ibu. Konfirmasi bahwa {$santriStr} hari ini berhalangan hadir karena *sakit* telah diterima. _Syafahullah / Syafakillah_, semoga ananda lekas sembuh.";
        }

        return "Terima kasih atas konfirmasinya Bapak/Ibu. Pemberitahuan *izin* untuk {$santriStr} hari ini telah diterima.";
    }

    /**
     * Menghasilkan balasan informasi terkait SPP & Tunggakan Santri secara langsung (to-the-point)
     *
     * @param  Collection<int, Siswa>|null  $allCandidates
     */
    public function handleSppNoticeQuery(?Siswa $siswa, ?Collection $allCandidates = null): string
    {
        if ($allCandidates && $allCandidates->count() > 1) {
            $sections = [];
            foreach ($allCandidates as $idx => $cand) {
                $num = $idx + 1;
                $sections[] = "==============================\n*SANTRI {$num}:*\n".$this->buildSingleStudentSppSection($cand);
            }

            return "Berikut informasi SPP untuk seluruh putra/putri Bapak/Ibu:\n\n".implode("\n\n", $sections);
        }

        if (! $siswa) {
            return 'Data santri tidak ditemukan untuk memeriksa informasi SPP.';
        }

        return $this->buildSingleStudentSppSection($siswa);
    }

    /**
     * Menyusun blok teks status SPP & rincian tunggakan untuk 1 santri
     */
    protected function buildSingleStudentSppSection(Siswa $siswa): string
    {
        SppBillingService::ensureSppSchemaExists();

        $sppService = app(SppBillingService::class);
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

        if ($totalBulan === 0 || $totalTunggakan <= 0) {
            return "💳 *Informasi SPP {$siswa->nama_lengkap}* ({$namaKelas}):\n"
                ."• Status: ✅ *LUNAS* (Tidak ada tunggakan s/d {$namaBulanIni} {$cutOffTahun})\n"
                .'• Total Tunggakan: *Rp 0*';
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

        return "💳 *Informasi SPP {$siswa->nama_lengkap}* ({$namaKelas}):\n"
            ."• Status: ⚠️ *Menunggak {$totalBulan} bulan*\n"
            ."• Total Tunggakan: *{$totalRp}*\n\n"
            ."*Rincian Bulan Belum Lunas:*\n"
            .implode("\n", $rincianLines);
    }

    /**
     * Menghasilkan balasan informasi presensi kehadiran untuk 1 santri (tanpa salam pembuka/penutup)
     */
    public function handleAbsensiQuery(Siswa $siswa, string $originalMessage = ''): string
    {
        $hariTanggalStr = Carbon::now()->locale('id')->isoFormat('dddd, D MMMM Y');

        return $this->buildSingleStudentAbsensiSection($siswa, $hariTanggalStr);
    }

    /**
     * Menghasilkan balasan informasi presensi sekaligus untuk wali yang memiliki >1 santri
     *
     * @param  Collection<int, Siswa>  $candidates
     */
    public function handleMultiAbsensiQuery(Collection $candidates): string
    {
        $hariTanggalStr = Carbon::now()->locale('id')->isoFormat('dddd, D MMMM Y');
        $sections = [];

        foreach ($candidates as $idx => $siswa) {
            $num = $idx + 1;
            $sections[] = "==============================\n*SANTRI {$num}:*\n".$this->buildSingleStudentAbsensiSection($siswa, $hariTanggalStr);
        }

        return "Berikut informasi kehadiran untuk seluruh putra/putri Bapak/Ibu:\n\n"
            .implode("\n\n", $sections);
    }

    /**
     * Menyusun blok teks ringkasan presensi hari ini & bulan berjalan untuk 1 santri
     */
    protected function buildSingleStudentAbsensiSection(Siswa $siswa, string $hariTanggalStr): string
    {
        $today = Carbon::today()->toDateString();
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $namaBulan = $this->bulanMap[$currentMonth] ?? 'Bulan Ini';
        $namaKelas = $siswa->kelas?->nama_kelas ?: 'Jilid';

        $presensiHariIni = Absensi::where('id_siswa', $siswa->id)
            ->where('tanggal', $today)
            ->first();

        $statusHariIniText = 'Belum ada catatan presensi masuk hari ini.';
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

        return "Informasi absensi *{$siswa->nama_lengkap}* ({$namaKelas}):\n\n"
            ."📅 *Hari Ini ({$hariTanggalStr}):*\n"
            ."Status: {$statusHariIniText}\n\n"
            ."📊 *Rekap Bulan {$namaBulan} {$currentYear}:*\n"
            ."• Hadir Tepat Waktu: {$totalHadir} hari\n"
            ."• Hadir Terlambat: {$totalTerlambat} hari\n"
            ."• Izin: {$totalIzin} hari\n"
            ."• Sakit: {$totalSakit} hari\n"
            ."• Alpa: {$totalAlpa} hari\n"
            ."📈 Persentase Kehadiran: *{$persentase}%*";
    }

    /**
     * Menghasilkan informasi hari libur secara to-the-point (tanpa menampilkan agenda internal TPQ)
     */
    public function handleAgendaQuery(string $message = ''): string
    {
        $today = Carbon::today()->toDateString();
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        $namaBulan = $this->bulanMap[$currentMonth] ?? 'Bulan Ini';

        $liburMendatang = HariLibur::where('tanggal_selesai', '>=', $today)
            ->orderBy('tanggal_mulai', 'asc')
            ->limit(5)
            ->get();

        $reply = "📅 *Informasi Hari Libur TPQ ({$namaBulan} {$currentYear}):*\n\n";

        if ($liburMendatang->isNotEmpty()) {
            foreach ($liburMendatang as $lib) {
                $mulai = Carbon::parse($lib->tanggal_mulai)->locale('id')->isoFormat('D MMM Y');
                $selesai = Carbon::parse($lib->tanggal_selesai)->locale('id')->isoFormat('D MMM Y');
                $tglStr = ($mulai === $selesai) ? $mulai : "{$mulai} s.d. {$selesai}";
                $reply .= "• *{$tglStr}*: {$lib->keterangan}\n";
            }
            $reply .= "\nDi luar tanggal tersebut, kegiatan mengaji berjalan normal sesuai jadwal rutin mingguan.";
        } else {
            $reply .= "Untuk bulan *{$namaBulan} {$currentYear}* saat ini tidak ada jadwal hari libur khusus tambahan di luar hari libur rutin mingguan. Kegiatan mengaji berjalan normal seperti biasa.";
        }

        return $reply;
    }

    /**
     * Pesan panduan bantuan ringkas (hanya 3 topik: Absensi, SPP, Hari Libur)
     */
    public function handleHelpQuery(?Siswa $siswa): string
    {
        $santriStr = $siswa
            ? " untuk ananda *{$siswa->nama_lengkap}* ({$siswa->kelas?->nama_kelas})"
            : '';

        $contohNama = $siswa ? strtolower(explode(' ', trim($siswa->nama_lengkap))[0]) : 'anak saya';

        return "Layanan informasi otomatis TPQ{$santriStr}. Silakan ketik pertanyaan terkait 3 informasi berikut:\n\n"
            ."1. *Absensi*: _\"Gimana absensi {$contohNama} hari ini?\"_\n"
            ."2. *SPP*: _\"Anak saya nunggak berapa bulan?\"_ atau _\"Cek SPP\"_\n"
            .'3. *Hari Libur*: _"Bulan ini apakah ada hari libur?"_';
    }

    /**
     * Balasan ketika pesan di luar 3 topik yang diizinkan (Absensi, SPP, Hari Libur)
     */
    public function handleUnknownQuery(?Siswa $siswa, string $originalMessage): string
    {
        $santriName = $siswa ? "ananda *{$siswa->nama_lengkap}*" : 'putra/putri Bapak/Ibu';

        return "Mohon maaf, layanan ini hanya menyediakan informasi terkait *Absensi*, *SPP*, dan *Informasi Hari Libur* untuk {$santriName}.\n\n"
            ."Silakan ketik pertanyaan sesuai topik berikut:\n"
            ."1. *Absensi*: _\"Gimana absensi anak saya?\"_\n"
            ."2. *SPP*: _\"Anak saya nunggak berapa bulan?\"_ atau _\"Cek SPP\"_\n"
            .'3. *Hari Libur*: _"Bulan ini apakah ada hari libur?"_';
    }

    /**
     * Balasan untuk nomor pengirim yang belum terdaftar
     */
    public function buildUnregisteredReply(string $phone): string
    {
        $namaInstansi = Pengaturan::getValue('nama_instansi', 'TPQ & Lembaga');

        return "Mohon maaf, nomor WhatsApp Anda (*{$phone}*) belum terdaftar pada data wali santri *{$namaInstansi}*.\n\n"
            ."Jika Anda adalah wali santri, ketik *NISN [Nomor Induk]* untuk mengecek data ananda.\n"
            .'Contoh: *NISN 1001 cek absensi*';
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
