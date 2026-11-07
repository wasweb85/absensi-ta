<?php

namespace App\Services;

use App\Models\ChatbotLog;
use Carbon\Carbon;

class IntentService
{
    /**
     * Klasifikasi intent dari pesan bahasa Indonesia sehari-hari wali santri
     * (mendukung variasi kalimat tidak baku, singkatan, typo, dan konteks percakapan lanjutan).
     */
    public function detectIntent(string $message, ?string $normalizedSender = null): string
    {
        $clean = strtolower(trim($message));
        if ($clean === '') {
            return 'unknown';
        }

        // 0. Shortcut pemilihan menu 1, 2, 3 (1 = Absensi, 2 = SPP, 3 = Hari Libur)
        if (preg_match('/^\s*(?:menu\s*|opsi\s*|pilihan\s*|nomor\s*|no\.?\s*)?([123])[\.\)]?\s*$/i', $clean, $numMatches)) {
            return match ($numMatches[1]) {
                '1' => 'absensi',
                '2' => 'spp_notice',
                '3' => 'agenda',
            };
        }

        // 1. Intent Pertanyaan SPP, Pembayaran & Tunggakan (termasuk bahasa sehari-hari & typo)
        if (preg_match('/\b(nunggak|menunggak|tunggak|tunggakan|nunggk|tggkn|spp|tagihan|tgihan|tghan|biaya|bayar|pembayaran|byr|pmbayaran|lunas|blm lunas|belum lunas|utang|hutang|piutang|infaq|syahriyah|iuran|belum bayar|blm byr|kurang bayar|kuitansi|kwitansi|berapa bulan|brp bln|biaya ngaji|bayar ngaji|transfer|tf| bukti bayar|konfirmasi bayar|pembayaran terakhir|bayar terakhir|melunasi)\b/i', $clean)) {
            return 'spp_notice';
        }

        // 2. Intent Pemberitahuan Anak Izin / Sakit dari Wali Santri
        $isAskingRecap = (bool) preg_match('/\b(gimana|gmn|gmna|bagaimana|cek|rekap|berapa|berpa|brp|laporan|info|apakah|apa|lihat|status|berapa kali|berpa kali|berapa hari|kemarin|kemaren|kmrn|bulan)\b/i', $clean);
        $isInformingAbsence = (bool) preg_match('/\b(mohon izin|minta izin|mau izin|ijin tidak|izin tidak|izin gak|izin nggak|tidak bisa hadir|tidak bisa masuk|tidak masuk|gak masuk|nggak masuk|absen dulu|lagi sakit|sedang sakit|karena sakit|kurang enak badan|demam|acara keluarga|bepergian)\b/i', $clean);
        if ($isInformingAbsence && ! $isAskingRecap) {
            return 'izin_sakit';
        }

        // 3. Intent Absensi & Kehadiran (termasuk jam masuk, notifikasi absensi, typo)
        $hasAttendanceWords = (bool) preg_match('/\b(presensi|absensi|absen|absn|kehadiran|khdiran|masuk|msk|hadir|hdir|bolos|izin|ijin|sakit|skt|alpa|alfa|alpha|terlambat|telat|tlt|rekap|datang|dtg|berangkat|brgkt|pulang|plg|sampai|nyampe|notifikasi|notif|scan|qr| barcode)\b/i', $clean);
        $hasFrequencyWords = (bool) preg_match('/\b(berapa kali|berpa kali|brp kali|berapa hari|brp hari|berapa kali hadir|berpa kali hadir|berapa kali masuk|berpa kali masuk|berapa kali absen|berpa kali absen|tidak masuk berapa|gak masuk berapa|ada yang alpa|ada alpa|ada yang alfa|ada yang bolos|udah masuk|udh masuk|udah datang|udh dtg|masuk jam berapa|dtg jam brp)\b/i', $clean);
        $hasTemporalWords = (bool) preg_match('/\b(bulan\s*(?:lalu|kemarin|kemaren|sebelumnya|berikutnya|ini)|bln\s*(?:lalu|kemarin|kemaren|sebelumnya|ini)|kemarin|kemaren|kmrn|hari kemarin|hari kemaren|hari ini|hr ini|tadi|minggu lalu|minggu ini|januari|februari|pebruari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)\b/i', $clean);

        // Jika menanyakan jam masuk & pulang umum TPQ atau jadwal TPQ (bukan jam masuk anak hari ini)
        $isGeneralScheduleQuery = (bool) preg_match('/\b(jadwal\s+(?:kegiatan|tpq|ngaji|mengaji|masuk)|jam\s+masuk\s+dan\s+pulang|jam\s+operasional|jam\s+berapa\s+tpq|peraturan\s+tpq|tata\s+tertib|syarat\s+pendaftaran|alamat\s+tpq|info\s+administrasi)\b/i', $clean);
        if ($isGeneralScheduleQuery && ! preg_match('/\b(anak|ananda|tadi|hari ini|kemarin|kemaren)\b/i', $clean)) {
            return 'info_tpq';
        }

        if ($hasAttendanceWords || $hasFrequencyWords) {
            return 'absensi';
        }

        // 4. Intent Informasi Hari Libur
        if (preg_match('/\b(libur|liburan|tanggal merah|tgl merah|rutinan|kapan libur|kalender|jadwal libur|prei)\b/i', $clean)) {
            return 'agenda';
        }

        // 5. Intent Informasi Umum TPQ (Jadwal kegiatan, peraturan, administrasi, atau Knowledge Base dinamis)
        if (preg_match('/\b(jadwal\s+tpq|kegiatan\s+tpq|agenda\s+tpq|peraturan\s+tpq|tata\s+tertib|seragam\s+tpq|info\s+administrasi|syarat\s+pendaftaran|daftar\s+ulang|alamat\s+tpq|profil\s+tpq|kepala\s+tpq)\b/i', $clean)) {
            return 'info_tpq';
        }

        if ($this->matchesCustomKnowledgeBase($clean)) {
            return 'info_tpq';
        }

        // 6. Follow-up temporal berdasarkan riwayat percakapan sebelumnya
        if ($hasTemporalWords) {
            if ($normalizedSender) {
                $prevIntent = $this->resolvePreviousIntent($normalizedSender);
                if ($prevIntent && in_array($prevIntent, ['absensi', 'spp_notice', 'agenda', 'info_tpq'], true)) {
                    return $prevIntent;
                }
            }
            if (preg_match('/\b(gimana|gmn|bagaimana|berapa|berpa|brp|cek|info|apakah|apa|ada|status|lihat|kalau|klo)\b/i', $clean) || str_contains($clean, '?')) {
                return 'absensi';
            }
        }

        // 7. Intent Apresiasi / Terima Kasih / Konfirmasi Singkat
        $hasQuestionWord = (bool) preg_match('/\b(gimana|gmn|bagaimana|berapa|berpa|brp|kapan|apakah|cek|menu|bantuan|tanya|bisa apa|halo|hai)\b/i', $clean);
        if (! $hasQuestionWord && preg_match('/\b(terima\s*kasih|terimakasih|makasih|mksh|trims|syukron|jazakallah|jazakumullah|alhamdulillah|baik|oke|ok|siap|noted|sama-sama|waalaikumsalam|wa\'alaikumussalam|walaikumsalam)\b/i', $clean)) {
            return 'apresiasi';
        }

        // 8. Intent Bantuan & Sapaan
        if (preg_match('/\b(halo|hai|assalam|assalamualaikum|assalamu\'alaikum|salam|pagi|siang|sore|malam|ustadz|ustazah|admin|menu|bantuan|help|info|bisa apa|tanya|panduan|permisi|punten)\b/i', $clean)) {
            return 'bantuan';
        }

        // 9. Follow-up kontekstual jika ada tanda tanya atau pertanyaan lanjutan setelah topik sebelumnya
        if ($normalizedSender) {
            $prevIntent = $this->resolvePreviousIntent($normalizedSender);
            if ($prevIntent && in_array($prevIntent, ['absensi', 'spp_notice', 'agenda', 'info_tpq'], true)) {
                if (preg_match('/(\?|\b(gimana|gmn|bagaimana|berapa|berpa|brp|apakah|apa|kapan|kenapa|knp|ada|kalau|klo|terus|lalu|yg|yang)\b)/i', $clean)) {
                    return $prevIntent;
                }
            }
        }

        return 'unknown';
    }

    /**
     * Petakan nama fungsi (tool) Gemini ke kategori intent standar aplikasi.
     */
    public function mapToolToIntent(string $toolName, string $fallbackIntent = 'bantuan'): string
    {
        return match ($toolName) {
            'check_attendance' => 'absensi',
            'check_spp_payment', 'check_spp_arrears' => 'spp_notice',
            'get_tpq_information' => $fallbackIntent === 'agenda' ? 'agenda' : 'info_tpq',
            default => $fallbackIntent,
        };
    }

    /**
     * Ambil intent terakhir dari riwayat percakapan (chat_conversations / chatbot_logs) dalam 30 menit terakhir.
     */
    public function resolvePreviousIntent(string $normalizedPhone): ?string
    {
        try {
            $conversationService = app(ConversationService::class);
            $convIntent = $conversationService->getLastConversationIntent($normalizedPhone, 30);
            if ($convIntent) {
                return $convIntent;
            }
        } catch (\Throwable) {
        }

        $lastLog = ChatbotLog::where('no_pengirim', $normalizedPhone)
            ->whereIn('intent_terdeteksi', ['absensi', 'spp_notice', 'agenda', 'izin_sakit', 'info_tpq'])
            ->where('created_at', '>=', Carbon::now()->subMinutes(30))
            ->latest('id')
            ->first();

        return $lastLog?->intent_terdeteksi;
    }

    /**
     * Periksa apakah pesan cocok dengan entri tambahan pada tabel tpq_knowledge_bases.
     */
    protected function matchesCustomKnowledgeBase(string $cleanMessage): bool
    {
        try {
            $kbService = app(KnowledgeBaseService::class);
            if (! $kbService->hasKnowledgeBaseTable()) {
                return false;
            }

            $customEntries = $kbService->getAll(true)->filter(fn ($entry) => (int) $entry->urutan > 5);
            foreach ($customEntries as $entry) {
                $judulLower = strtolower(trim((string) $entry->judul));
                if ($judulLower !== '' && str_contains($cleanMessage, $judulLower)) {
                    return true;
                }

                $keywords = array_filter(array_map('trim', explode(',', strtolower((string) $entry->kata_kunci))));
                foreach ($keywords as $kw) {
                    if (strlen($kw) >= 4 && str_contains($cleanMessage, $kw)) {
                        return true;
                    }
                }
            }
        } catch (\Throwable) {
        }

        return false;
    }
}
