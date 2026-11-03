<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\ChatbotLog;
use App\Models\HariLibur;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\TagihanSpp;
use App\Models\User;
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
        $intent = $this->detectIntent($trimmedMessage, $normalizedSender);

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
            $reply = $this->handleSppNoticeQuery($firstSiswa, $candidates, $trimmedMessage);
            $log = $this->logInteraction($normalizedSender, $trimmedMessage, $intent, null, $reply, 'sukses');

            return [
                'success' => true,
                'intent' => $intent,
                'reply' => $reply,
                'siswa' => null,
                'log_id' => $log->id,
            ];
        }

        // Jika wali meminta rekap "Semua" anak sekaligus saat punya >1 santri atau pertanyaan umum absensi/SPP
        if ($resolved['status'] === 'found_all') {
            /** @var Collection<int, Siswa> $candidates */
            $candidates = $resolved['candidates'];
            $firstSiswa = $candidates->first();
            if ($intent === 'unknown' || $intent === 'bantuan') {
                $intent = 'absensi';
            }

            $reply = match ($intent) {
                'spp_notice' => $this->handleSppNoticeQuery($firstSiswa, $candidates, $trimmedMessage),
                default => $this->handleMultiAbsensiQuery($candidates, $trimmedMessage),
            };

            $log = $this->logInteraction($normalizedSender, $trimmedMessage, $intent, null, $reply, 'sukses');

            return [
                'success' => true,
                'intent' => $intent,
                'reply' => $reply,
                'siswa' => null,
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

        // Jika pesan adalah konfirmasi pemilihan anak (cth: "1", "2") atau pertanyaan "anak satunya lagi",
        // periksa apakah sebelumnya wali sudah mengajukan pertanyaan spesifik (misal tanya absensi / SPP)
        if ((! empty($resolved['is_selection_reply']) || ! empty($resolved['is_other_child'])) && in_array($intent, ['unknown', 'bantuan'], true)) {
            $pendingIntent = $this->resolvePreviousIntentFromHistory($normalizedSender);
            $intent = $pendingIntent ?: 'absensi';
        }

        // 3. Bangun Respon berdasarkan Intent
        $reply = match ($intent) {
            'spp_notice' => $this->handleSppNoticeQuery($siswa, null, $trimmedMessage),
            'izin_sakit' => $this->handleIzinSakitQuery($siswa, $trimmedMessage),
            'absensi' => $siswa ? $this->handleAbsensiQuery($siswa, $trimmedMessage) : $this->handleHelpQuery(null),
            'bantuan' => $this->handleHelpQuery($siswa),
            default => $this->handleUnknownQuery($siswa, $trimmedMessage, $resolved['candidates'] ?? null),
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
    public function detectIntent(string $message, ?string $normalizedSender = null): string
    {
        $clean = strtolower(trim($message));

        // 1. Intent Pertanyaan SPP & Tunggakan
        if (preg_match('/\b(nunggak|menunggak|tunggak|tunggakan|spp|tagihan|biaya|bayar|pembayaran|lunas|belum lunas|utang|hutang|piutang|infaq|syahriyah|iuran|belum bayar|kurang bayar|kuitansi|berapa bulan|biaya ngaji|bayar ngaji)\b/i', $clean)) {
            return 'spp_notice';
        }

        // 2. Intent Pemberitahuan Anak Izin / Sakit dari Wali Santri
        $isAskingRecap = (bool) preg_match('/\b(gimana|bagaimana|cek|rekap|berapa|berpa|brp|laporan|info|apakah|lihat|status|berapa kali|berpa kali|berapa hari)\b/i', $clean);
        $isInformingAbsence = (bool) preg_match('/\b(mohon izin|minta izin|mau izin|izin tidak|izin gak|izin nggak|tidak bisa hadir|tidak bisa masuk|tidak masuk|gak masuk|nggak masuk|absen dulu|lagi sakit|sedang sakit|karena sakit|kurang enak badan|demam|acara keluarga|bepergian)\b/i', $clean);
        if ($isInformingAbsence && ! $isAskingRecap) {
            return 'izin_sakit';
        }

        // 3. Intent Absensi & Kehadiran
        // A. Kata kunci absensi/kehadiran langsung
        $hasAttendanceWords = (bool) preg_match('/\b(presensi|absensi|kehadiran|absen|masuk|hadir|bolos|izin|sakit|alpa|alfa|terlambat|telat|rekap|datang|berangkat|pulang|sampai|ngaji|mengaji)\b/i', $clean);
        // B. Pertanyaan frekuensi / jumlah kehadiran (cth: "berapa kali?", "berpa kali?", "berapa hari?", "berapa kali masuk")
        $hasFrequencyWords = (bool) preg_match('/\b(berapa kali|berpa kali|brp kali|berapa hari|brp hari|berapa kali hadir|berpa kali hadir|berapa kali masuk|berpa kali masuk|berapa kali absen|berpa kali absen|ada yang alpa|ada alpa|ada yang alfa|ada yang bolos)\b/i', $clean);
        // C. Pertanyaan temporal (cth: "kalau bulan sebelumnya berpa kali?", "bulan lalu gimana?", "kemarin gimana?", "minggu lalu?")
        $hasTemporalWords = (bool) preg_match('/\b(bulan\s*(?:lalu|kemarin|kemaren|sebelumnya|berikutnya)|bln\s*(?:lalu|kemarin|kemaren|sebelumnya)|kemarin|kemaren|hari kemarin|hari kemaren|minggu lalu|minggu ini|januari|februari|pebruari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)\b/i', $clean);

        if ($hasAttendanceWords || $hasFrequencyWords) {
            return 'absensi';
        }

        // 4. Follow-up temporal berdasarkan riwayat percakapan sebelumnya
        if ($hasTemporalWords) {
            if ($normalizedSender) {
                $prevIntent = $this->resolvePreviousIntentFromHistory($normalizedSender);
                if ($prevIntent && in_array($prevIntent, ['absensi', 'spp_notice'], true)) {
                    return $prevIntent;
                }
            }
            // Default jika ada kata temporal dan pertanyaan ("gimana", "berapa", "berpa", "cek", "info", dll) -> absensi
            if (preg_match('/\b(gimana|bagaimana|berapa|berpa|brp|cek|info|apakah|apa|ada|status|lihat|kalau|gmn)\b/i', $clean) || str_contains($clean, '?')) {
                return 'absensi';
            }
        }

        // 5. Intent Informasi Hari Libur (Hanya hari libur, tidak menampilkan agenda internal)
        if (preg_match('/\b(libur|liburan|tanggal merah|rutinan|kapan libur|kalender|jadwal libur|prei)\b/i', $clean)) {
            return 'agenda';
        }

        // 6. Intent Apresiasi / Terima Kasih / Konfirmasi Singkat
        $hasQuestionWord = (bool) preg_match('/\b(gimana|bagaimana|berapa|berpa|brp|kapan|apakah|cek|menu|bantuan|tanya|bisa apa|halo|hai)\b/i', $clean);
        if (! $hasQuestionWord && preg_match('/\b(terima\s*kasih|terimakasih|makasih|mksh|trims|syukron|jazakallah|jazakumullah|alhamdulillah|baik|oke|ok|siap|noted|sama-sama|waalaikumsalam|wa\'alaikumussalam|walaikumsalam)\b/i', $clean)) {
            return 'apresiasi';
        }

        // 7. Intent Bantuan & Sapaan
        if (preg_match('/\b(halo|hai|assalam|assalamualaikum|assalamu\'alaikum|salam|pagi|siang|sore|malam|ustadz|ustazah|admin|menu|bantuan|help|info|bisa apa|tanya|panduan)\b/i', $clean)) {
            return 'bantuan';
        }

        // 8. Follow-up kontekstual jika ada tanda tanya atau pertanyaan pendek setelah topik sebelumnya
        if ($normalizedSender) {
            $prevIntent = $this->resolvePreviousIntentFromHistory($normalizedSender);
            if ($prevIntent && in_array($prevIntent, ['absensi', 'spp_notice'], true)) {
                if (preg_match('/(\?|\b(gimana|bagaimana|berapa|berpa|brp|apakah|apa|kapan|kenapa|ada|kalau|terus)\b)/i', $clean)) {
                    return $prevIntent;
                }
            }
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
     * Hapus sesi santri aktif (misal saat wali bertanya umum tentang semua anak)
     */
    protected function forgetActiveStudent(string $normalizedPhone): void
    {
        Cache::forget("wa_active_student_{$normalizedPhone}");

        try {
            $raw = Pengaturan::getValue('wa_active_student_map');
            if (! empty($raw)) {
                $map = json_decode((string) $raw, true);
                if (is_array($map) && isset($map[$normalizedPhone])) {
                    unset($map[$normalizedPhone]);
                    Pengaturan::setValue('wa_active_student_map', json_encode($map));
                }
            }
        } catch (\Throwable) {
        }
    }

    /**
     * Mengambil topik / intent terakhir dari percakapan wali santri sebelumnya
     */
    protected function resolvePreviousIntentFromHistory(string $normalizedPhone): ?string
    {
        $lastLog = ChatbotLog::where('no_pengirim', $normalizedPhone)
            ->whereIn('intent_terdeteksi', ['absensi', 'spp_notice', 'agenda', 'izin_sakit'])
            ->where('created_at', '>=', Carbon::now()->subMinutes(30))
            ->latest('id')
            ->first();

        return $lastLog?->intent_terdeteksi;
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

        // A. Cek apakah wali meminta informasi "anak yang satunya lagi" / "anak kedua" / "yang satunya"
        $isOtherChildQuery = (bool) preg_match('/\b(anak\s*(?:sya|saya)?\s*yang\s*(?:satunya|lain|kedua)|yang\s*(?:satunya|lain)(?:\s*lagi)?|satunya\s*lagi|anak\s*(?:satunya|kedua))\b/i', $cleanMsg);
        if ($isOtherChildQuery && $candidates->count() >= 2) {
            $lastStudentId = $this->getRememberedActiveStudentId($normalizedPhone);
            if (! $lastStudentId) {
                $lastStudentId = ChatbotLog::where('no_pengirim', $normalizedPhone)
                    ->whereNotNull('id_siswa')
                    ->latest('id')
                    ->value('id_siswa');
            }

            $chosen = null;
            if ($lastStudentId) {
                $chosen = $candidates->first(fn ($c) => $c->id !== (int) $lastStudentId);
            }
            if (! $chosen) {
                $chosen = $candidates->get(1); // default ke anak kedua
            }
            if ($chosen) {
                $this->rememberActiveStudent($normalizedPhone, $chosen->id);

                return [
                    'status' => 'found',
                    'siswa' => $chosen,
                    'is_other_child' => true,
                ];
            }
        }

        // B. Cek apakah ada nama lengkap atau nama panggilan spesifik di dalam pesan
        // (Misal: "Farel", "khowas", "Mohammad Farel")
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

        // C. Cek apakah pesan adalah angka pilihan murni ("1", "2", "1.", "#1")
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

        // D. Cek apakah wali menyertakan angka urut anak di dalam kalimat (cth: "anak 1", "anak ke 2", "nomor 1")
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

        // E. Jika wali mengetik "semua" / "keduanya" / "dua-duanya"
        if (preg_match('/\b(semua|semuanya|keduanya|dua-duanya|kedua anak|semua anak)\b/i', $lowerMsg)) {
            $this->forgetActiveStudent($normalizedPhone);

            return [
                'status' => 'found_all',
                'candidates' => $candidates,
            ];
        }

        // F. Cek Intent: jika pertanyaan umum tentang "absensi" atau "spp_notice" (cth: "kalau absensi anak saya", "anak saya nunggak berapa bulan", "kalau bulan sebelumnya berpa kali?")
        // TANPA menyebutkan nama anak spesifik:
        // Selalu tampilkan data SEMUA anak agar wali yang punya >1 santri langsung menerima informasi lengkap kedua anaknya!
        $detectedIntent = $this->detectIntent($cleanMsg, $normalizedPhone);
        if (in_array($detectedIntent, ['absensi', 'spp_notice'], true)) {
            $this->forgetActiveStudent($normalizedPhone);

            return [
                'status' => 'found_all',
                'candidates' => $candidates,
            ];
        }

        // G. Cek sesi aktif santri dari Cache / Pengaturan / ChatbotLog jika bukan pertanyaan umum
        $rememberedId = $this->getRememberedActiveStudentId($normalizedPhone);
        if ($rememberedId) {
            $cachedStudent = $candidates->firstWhere('id', $rememberedId);
            if ($cachedStudent) {
                return ['status' => 'found', 'siswa' => $cachedStudent];
            }
        }

        // H. Tampilkan prompt informasi santri terdaftar
        $prompt = "Nomor WhatsApp Anda terdaftar sebagai wali dari *{$candidates->count()} santri*:\n";

        foreach ($candidates as $i => $cand) {
            $num = $i + 1;
            $namaKelas = $cand->kelas?->nama_kelas ?: '-';
            $prompt .= "{$num}. *{$cand->nama_lengkap}* ({$namaKelas})\n";
        }

        $prompt .= "\nSilakan sebutkan *nama ananda* (atau ketik *1* sampai *{$candidates->count()}*) untuk memilih data santri yang ingin Anda tanyakan (*Absensi*, *SPP*, atau *Hari Libur*).";

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
     * Mengekstrak periode waktu (bulan/tahun atau hari kemarin) dari pesan wali santri
     *
     * @return array{
     *     mode: 'month'|'day',
     *     bulan: int,
     *     tahun: int,
     *     nama_bulan: string,
     *     label_periode: string,
     *     is_current_month: bool,
     *     is_previous_month: bool,
     *     is_specific_day: bool,
     *     tanggal_khusus: string|null,
     *     label_hari_khusus: string|null
     * }
     */
    public function parseTargetPeriod(string $message): array
    {
        $clean = strtolower(trim($message));
        $now = Carbon::now('Asia/Jakarta');
        $currentMonth = (int) $now->month;
        $currentYear = (int) $now->year;

        // 1. Cek apakah menanyakan "kemarin" / "kemaren"
        if (preg_match('/\b(kemarin|kemaren|hari kemarin|hari kemaren)\b/i', $clean)) {
            $yesterday = Carbon::yesterday('Asia/Jakarta');

            return [
                'mode' => 'day',
                'bulan' => (int) $yesterday->month,
                'tahun' => (int) $yesterday->year,
                'nama_bulan' => $this->bulanMap[(int) $yesterday->month] ?? 'Bulan',
                'label_periode' => $yesterday->locale('id')->isoFormat('dddd, D MMMM Y'),
                'is_current_month' => (int) $yesterday->month === $currentMonth && (int) $yesterday->year === $currentYear,
                'is_previous_month' => false,
                'is_specific_day' => true,
                'tanggal_khusus' => $yesterday->toDateString(),
                'label_hari_khusus' => 'Kemarin ('.$yesterday->locale('id')->isoFormat('dddd, D MMMM Y').')',
            ];
        }

        // 2. Cek apakah menanyakan 2 bulan lalu
        if (preg_match('/\b(2|dua)\s*bulan\s*(?:lalu|kemarin|kemaren|sebelumnya)\b/i', $clean)) {
            $dt = $now->copy()->subMonths(2);
            $m = (int) $dt->month;
            $y = (int) $dt->year;
            $namaBln = $this->bulanMap[$m] ?? "Bulan {$m}";

            return [
                'mode' => 'month',
                'bulan' => $m,
                'tahun' => $y,
                'nama_bulan' => $namaBln,
                'label_periode' => "Bulan {$namaBln} {$y}",
                'is_current_month' => false,
                'is_previous_month' => true,
                'is_specific_day' => false,
                'tanggal_khusus' => null,
                'label_hari_khusus' => null,
            ];
        }

        // 3. Cek apakah menanyakan "bulan sebelumnya" / "bulan lalu" / "bulan kemarin"
        if (preg_match('/\b(bulan\s*(?:sebelumnya|lalu|kemarin|kemaren|terakhir)|bln\s*(?:sebelumnya|lalu|kemarin|kemaren)|last\s*month)\b/i', $clean)) {
            $dt = $now->copy()->subMonth();
            $m = (int) $dt->month;
            $y = (int) $dt->year;
            $namaBln = $this->bulanMap[$m] ?? "Bulan {$m}";

            return [
                'mode' => 'month',
                'bulan' => $m,
                'tahun' => $y,
                'nama_bulan' => $namaBln,
                'label_periode' => "Bulan {$namaBln} {$y}",
                'is_current_month' => false,
                'is_previous_month' => true,
                'is_specific_day' => false,
                'tanggal_khusus' => null,
                'label_hari_khusus' => null,
            ];
        }

        // 4. Cek apakah ada nama bulan spesifik (misal: "oktober", "september")
        $monthPatternMap = [
            1 => 'januari|jan',
            2 => 'februari|pebruari|feb',
            3 => 'maret|mar',
            4 => 'april|apr',
            5 => 'mei|may',
            6 => 'juni|jun',
            7 => 'juli|jul',
            8 => 'agustus|agust|agt',
            9 => 'september|sept|sep',
            10 => 'oktober|okt',
            11 => 'november|nopember|nov',
            12 => 'desember|des',
        ];

        // Cari tahun spesifik jika disebutkan (cth: 2025, 2026, 2027)
        $targetYear = $currentYear;
        if (preg_match('/\b(202[4-9])\b/', $clean, $ym)) {
            $targetYear = (int) $ym[1];
        }

        foreach ($monthPatternMap as $mNum => $mRegex) {
            if (preg_match('/\b('.$mRegex.')\b/i', $clean)) {
                $namaBln = $this->bulanMap[$mNum] ?? "Bulan {$mNum}";
                $isCurrent = ($mNum === $currentMonth && $targetYear === $currentYear);

                return [
                    'mode' => 'month',
                    'bulan' => $mNum,
                    'tahun' => $targetYear,
                    'nama_bulan' => $namaBln,
                    'label_periode' => "Bulan {$namaBln} {$targetYear}",
                    'is_current_month' => $isCurrent,
                    'is_previous_month' => ! $isCurrent,
                    'is_specific_day' => false,
                    'tanggal_khusus' => null,
                    'label_hari_khusus' => null,
                ];
            }
        }

        // 5. Default: Bulan berjalan saat ini
        $namaBln = $this->bulanMap[$currentMonth] ?? 'Bulan Ini';

        return [
            'mode' => 'month',
            'bulan' => $currentMonth,
            'tahun' => $currentYear,
            'nama_bulan' => $namaBln,
            'label_periode' => "Bulan {$namaBln} {$currentYear}",
            'is_current_month' => true,
            'is_previous_month' => false,
            'is_specific_day' => false,
            'tanggal_khusus' => null,
            'label_hari_khusus' => null,
        ];
    }

    /**
     * Menghasilkan balasan informasi terkait SPP & Tunggakan Santri secara langsung (to-the-point)
     *
     * @param  Collection<int, Siswa>|null  $allCandidates
     */
    public function handleSppNoticeQuery(?Siswa $siswa, ?Collection $allCandidates = null, string $originalMessage = ''): string
    {
        $period = $this->parseTargetPeriod($originalMessage);
        $clean = strtolower(trim($originalMessage));
        $isAskingPaymentTarget = (bool) preg_match(
            '/\b(kepada siapa|ke siapa|sama siapa|lewat siapa|hubungi siapa|bayar ke siapa|bayar kemana|kemana bayar|cara bayar|lewat mana|rekening|transfer|no.*bendahara|nomor.*bendahara|kontak.*bendahara|bendahara)\b/i',
            $clean
        );

        $hasTunggakan = false;
        $sections = [];

        if ($allCandidates && $allCandidates->count() > 1) {
            foreach ($allCandidates as $cand) {
                $sectionData = $this->buildSingleStudentSppData($cand, $period);
                $sections[] = $sectionData['text'];
                if ($sectionData['has_tunggakan']) {
                    $hasTunggakan = true;
                }
            }

            $introPeriode = $period['is_previous_month'] ? " untuk {$period['label_periode']}" : '';

            $baseReply = "Berikut informasi SPP ananda{$introPeriode}:\n\n"
                .implode("\n\n────────────────────\n\n", $sections);
        } elseif ($siswa) {
            $sectionData = $this->buildSingleStudentSppData($siswa, $period);
            $sections[] = $sectionData['text'];
            if ($sectionData['has_tunggakan']) {
                $hasTunggakan = true;
            }
            $baseReply = $sectionData['text'];
        } else {
            return 'Data santri tidak ditemukan untuk memeriksa informasi SPP.';
        }

        $paymentNotice = $this->buildSppPaymentNotice($isAskingPaymentTarget, $hasTunggakan);
        if (! empty($paymentNotice)) {
            return $baseReply."\n\n".$paymentNotice;
        }

        return $baseReply;
    }

    /**
     * Menghasilkan catatan panduan pembayaran SPP ke Bendahara
     */
    protected function buildSppPaymentNotice(bool $isAskingPaymentTarget, bool $hasTunggakan): string
    {
        $bendaharaInfo = $this->getBendaharaInfo();
        $namaBendahara = $bendaharaInfo['nama'];
        $kontak = $bendaharaInfo['kontak'];
        $rekeningSpp = trim((string) Pengaturan::getValue('rekening_spp'));

        if ($isAskingPaymentTarget) {
            $lines = [
                'Pembayaran SPP dapat diselesaikan melalui Bendahara:',
                "• Bendahara: {$namaBendahara}",
            ];
            if (! empty($kontak)) {
                $lines[] = "• Kontak / No. HP: {$kontak}";
            }
            if (! empty($rekeningSpp)) {
                $lines[] = "• Rekening: {$rekeningSpp}";
            }
            $lines[] = '• Lokasi: Kantor / Sekretariat TPQ';

            if ($hasTunggakan) {
                $lines[] = '';
                $lines[] = 'Besar harapan kami agar Bapak/Ibu dapat segera menyelesaikan kewajiban administrasi ini melalui Bendahara.';
            }

            return implode("\n", $lines);
        }

        if ($hasTunggakan) {
            $kontakSuffix = ! empty($kontak) ? " - {$kontak}" : '';

            return "Besar harapan kami agar Bapak/Ibu dapat segera menyelesaikan kewajiban administrasi ini melalui Bendahara ({$namaBendahara}{$kontakSuffix}) atau langsung di kantor TPQ.";
        }

        return '';
    }

    /**
     * Mengambil nama resmi dan nomor kontak bendahara dari database / pengaturan
     *
     * @return array{nama: string, kontak: string}
     */
    public function getBendaharaInfo(): array
    {
        $namaBendahara = User::getNamaBendahara();
        $kontakBendahara = trim((string) (
            Pengaturan::getValue('no_hp_bendahara')
            ?: Pengaturan::getValue('kontak_bendahara')
            ?: Pengaturan::getValue('kontak_instansi')
        ));

        return [
            'nama' => $namaBendahara,
            'kontak' => $kontakBendahara,
        ];
    }

    /**
     * Menyusun blok teks status SPP & rincian tunggakan untuk 1 santri
     */
    protected function buildSingleStudentSppSection(Siswa $siswa, ?array $period = null): string
    {
        return $this->buildSingleStudentSppData($siswa, $period)['text'];
    }

    /**
     * Menyusun data status SPP & rincian tunggakan untuk 1 santri
     *
     * @return array{text: string, has_tunggakan: bool, total_bulan: int, total_tunggakan: float}
     */
    protected function buildSingleStudentSppData(Siswa $siswa, ?array $period = null): array
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
        $hasTunggakan = ($totalBulan > 0 && $totalTunggakan > 0);

        // Jika menanyakan periode bulan tertentu (misal: "spp bulan kemarin" / "spp oktober")
        $statusBulanSpesifik = '';
        if ($period && ! empty($period['is_previous_month'])) {
            $tagihanSpesifik = TagihanSpp::with(['pembayaran' => fn ($q) => $q->where('status_transaksi', 'valid')])
                ->where('id_siswa', $siswa->id)
                ->where('bulan', $period['bulan'])
                ->where('tahun', $period['tahun'])
                ->first();

            if ($tagihanSpesifik) {
                $totalBayar = (float) $tagihanSpesifik->pembayaran->sum('jumlah_bayar');
                $sisa = max(0, (float) $tagihanSpesifik->nominal_tagihan - $totalBayar);
                if ($tagihanSpesifik->status === 'lunas' || $sisa <= 0) {
                    $statusBulanSpesifik = "• SPP {$period['label_periode']}: ✅ *LUNAS*\n";
                } else {
                    $sisaRp = 'Rp '.number_format($sisa, 0, ',', '.');
                    $statusBulanSpesifik = "• SPP {$period['label_periode']}: ⚠️ *BELUM LUNAS* (Sisa {$sisaRp})\n";
                }
            }
        }

        if (! $hasTunggakan) {
            $text = "💳 *SPP ananda {$siswa->nama_lengkap}* ({$namaKelas}):\n"
                .$statusBulanSpesifik
                ."• Status: ✅ *LUNAS* (Tidak ada tunggakan s/d {$namaBulanIni} {$cutOffTahun})\n"
                .'• Total Tunggakan: *Rp 0*';

            return [
                'text' => $text,
                'has_tunggakan' => false,
                'total_bulan' => 0,
                'total_tunggakan' => 0.0,
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
            .implode("\n", $rincianLines);

        return [
            'text' => $text,
            'has_tunggakan' => true,
            'total_bulan' => $totalBulan,
            'total_tunggakan' => $totalTunggakan,
        ];
    }

    /**
     * Menghasilkan balasan informasi presensi kehadiran untuk 1 santri (tanpa salam pembuka/penutup)
     */
    public function handleAbsensiQuery(Siswa $siswa, string $originalMessage = ''): string
    {
        $hariTanggalStr = Carbon::now('Asia/Jakarta')->locale('id')->isoFormat('dddd, D MMMM Y');
        $period = $this->parseTargetPeriod($originalMessage);

        return $this->buildSingleStudentAbsensiSection($siswa, $hariTanggalStr, $period);
    }

    /**
     * Menghasilkan balasan informasi presensi sekaligus untuk wali yang memiliki >1 santri
     *
     * @param  Collection<int, Siswa>  $candidates
     */
    public function handleMultiAbsensiQuery(Collection $candidates, string $originalMessage = ''): string
    {
        $hariTanggalStr = Carbon::now('Asia/Jakarta')->locale('id')->isoFormat('dddd, D MMMM Y');
        $period = $this->parseTargetPeriod($originalMessage);
        $sections = [];

        foreach ($candidates as $siswa) {
            $sections[] = $this->buildSingleStudentAbsensiSection($siswa, $hariTanggalStr, $period);
        }

        $introPeriode = $period['is_previous_month'] ? " untuk {$period['label_periode']}" : '';

        return "Berikut informasi absensi ananda{$introPeriode}:\n\n"
            .implode("\n\n────────────────────\n\n", $sections);
    }

    /**
     * Menyusun blok teks ringkasan presensi hari ini & bulan berjalan untuk 1 santri
     */
    protected function buildSingleStudentAbsensiSection(Siswa $siswa, string $hariTanggalStr, ?array $period = null): string
    {
        $period = $period ?: $this->parseTargetPeriod('');
        $namaKelas = $siswa->kelas?->nama_kelas ?: 'Jilid';
        $targetMonth = $period['bulan'];
        $targetYear = $period['tahun'];

        // Rekapitulasi pada bulan target
        $startOfMonth = Carbon::createFromDate($targetYear, $targetMonth, 1)->startOfMonth()->toDateString();
        $endOfMonth = Carbon::createFromDate($targetYear, $targetMonth, 1)->endOfMonth()->toDateString();

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

        // Jika wali menanyakan bulan sebelumnya atau bulan lampau tertentu:
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
                .$catatanText;
        }

        // Jika wali menanyakan tanggal kemarin tertentu
        if (! empty($period['is_specific_day']) && ! empty($period['tanggal_khusus'])) {
            $presensiKhusus = Absensi::where('id_siswa', $siswa->id)
                ->where('tanggal', $period['tanggal_khusus'])
                ->first();

            $statusKhususText = 'Tidak ada catatan presensi pada tanggal ini.';
            if ($presensiKhusus) {
                $jam = $presensiKhusus->waktu_masuk ?: '-';
                $st = strtolower($presensiKhusus->status);
                $statusKhususText = match ($st) {
                    'hadir' => "✅ *HADIR TEPAT WAKTU* (Pukul {$jam} WIB)",
                    'terlambat' => "⚠️ *HADIR TERLAMBAT* (Pukul {$jam} WIB)",
                    'izin' => '📝 *IZIN*',
                    'sakit' => '🏥 *SAKIT*',
                    'alpa' => '❌ *ALPA / TANPA KETERANGAN*',
                    default => "*{$presensiKhusus->status}*",
                };
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
                ."📈 Persentase Kehadiran: *{$persentase}%*";
        }

        // Default: Bulan berjalan saat ini (tampilkan Status Hari Ini + Rekap Bulan Ini)
        $today = Carbon::today('Asia/Jakarta')->toDateString();
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

        return "📋 *Absensi ananda {$siswa->nama_lengkap}* ({$namaKelas}):\n\n"
            ."📅 *Hari Ini ({$hariTanggalStr}):*\n"
            ."Status: {$statusHariIniText}\n\n"
            ."📊 *Rekap {$period['label_periode']}:*\n"
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
            ."2. *SPP*: _\"Anak saya nunggak berapa bulan?\"_, _\"Cek SPP\"_, atau _\"Bayar ke siapa?\"_\n"
            .'3. *Hari Libur*: _"Bulan ini apakah ada hari libur?"_';
    }

    /**
     * Balasan ketika pesan di luar 3 topik yang diizinkan (Absensi, SPP, Hari Libur)
     */
    public function handleUnknownQuery(?Siswa $siswa, string $originalMessage, ?Collection $candidates = null): string
    {
        $santriName = 'ananda';
        if ($candidates && $candidates->count() > 1) {
            $names = $candidates->pluck('nama_lengkap')->map(fn ($n) => "*{$n}*")->implode(' & ');
            $santriName = "putra/putri Bapak/Ibu ({$names})";
        } elseif ($siswa) {
            $santriName = "ananda *{$siswa->nama_lengkap}*";
        }

        return "Mohon maaf, layanan ini hanya menyediakan informasi terkait *Absensi*, *SPP*, dan *Informasi Hari Libur* untuk {$santriName}.\n\n"
            ."Silakan ketik pertanyaan sesuai topik berikut:\n"
            ."1. *Absensi*: _\"Gimana absensi anak saya?\"_ atau _\"Bulan sebelumnya berapa kali?\"_\n"
            ."2. *SPP*: _\"Anak saya nunggak berapa bulan?\"_, _\"Cek SPP\"_, atau _\"Bayar ke siapa?\"_\n"
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
