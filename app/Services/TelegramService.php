<?php

namespace App\Services;

use App\Models\Agenda;
use App\Models\HariLibur;
use App\Models\Pengaturan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * Mendapatkan nama instansi resmi
     */
    public function getNamaInstansi(): string
    {
        $val = Pengaturan::getValue('nama_instansi');

        if (empty($val) || in_array(strtoupper(trim($val)), ['E ABSENSI', 'E-ABSENSI', 'SISTEM TERPADU ABSENSI SANTRI', 'SISTEM TERPADU PRESENSI & NOTIFIKASI WHATSAPP SANTRI', 'TPQ & LEMBAGA'])) {
            return "TPQ Nurul Mun'im";
        }

        return $val;
    }

    /**
     * Mendapatkan Bot Token dari database / argumen
     */
    public function getBotToken(?string $token = null): ?string
    {
        return $token ?: Pengaturan::getValue('telegram_bot_token');
    }

    /**
     * Mendapatkan Chat ID tujuan dari database / argumen
     */
    public function getChatId(?string $chatId = null): ?string
    {
        return $chatId ?: Pengaturan::getValue('telegram_chat_id');
    }

    /**
     * Mendapatkan jam konfigurasi pengiriman notifikasi pengingat agenda H-1 (Format HH:mm)
     */
    public function getJamPengiriman(): string
    {
        $jam = Pengaturan::getValue('telegram_notif_agenda_jam', '07:00');

        return substr(trim($jam ?: '07:00'), 0, 5);
    }

    /**
     * Mengecek apakah notifikasi agenda Telegram diaktifkan
     */
    public function isNotifAktif(): bool
    {
        $aktif = Pengaturan::getValue('telegram_notif_agenda_aktif', '1');

        return $aktif === '1' || $aktif === 'true' || $aktif === 1;
    }

    /**
     * Kirim pesan teks ke Telegram Bot API
     */
    public function sendMessage(
        string $message,
        ?string $chatId = null,
        ?string $botToken = null,
        string $parseMode = 'HTML'
    ): array {
        $token = $this->getBotToken($botToken);
        $targetChat = $this->getChatId($chatId);

        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'Token Bot Telegram belum dikonfigurasi di Pengaturan.',
            ];
        }

        if (empty($targetChat)) {
            return [
                'success' => false,
                'message' => 'Chat ID Telegram (admin / grup) belum diisi di Pengaturan.',
            ];
        }

        try {
            $url = "https://api.telegram.org/bot{$token}/sendMessage";

            $response = Http::withoutVerifying()
                ->timeout(15)
                ->post($url, [
                    'chat_id' => $targetChat,
                    'text' => $message,
                    'parse_mode' => $parseMode,
                    'disable_web_page_preview' => true,
                ]);

            $json = $response->json();

            Log::info('Telegram sendMessage response', [
                'chat_id' => $targetChat,
                'status' => $response->status(),
                'ok' => $json['ok'] ?? false,
            ]);

            if ($response->successful() && ! empty($json['ok'])) {
                return [
                    'success' => true,
                    'message' => 'Pesan notifikasi Telegram berhasil dikirim.',
                    'data' => $json['result'] ?? null,
                ];
            }

            $rawDesc = $json['description'] ?? 'Gagal mengirim pesan Telegram.';
            $descLower = strtolower($rawDesc);

            $friendlyError = match (true) {
                str_contains($descLower, 'unauthorized') => 'Token Bot Telegram tidak valid atau salah. Periksa kembali token dari @BotFather.',
                str_contains($descLower, 'chat not found') => 'Chat ID Telegram tidak ditemukan. Pastikan Anda sudah memulai (/start) chat dengan bot atau memasukkan bot ke grup.',
                str_contains($descLower, 'bot was blocked') => 'Bot diblokir oleh pengguna pada Chat ID tersebut.',
                str_contains($descLower, 'not enough rights') => 'Bot belum memiliki hak izin kirim pesan di grup tersebut.',
                default => 'Telegram API: '.$rawDesc,
            };

            return [
                'success' => false,
                'message' => $friendlyError,
                'error_detail' => $json,
            ];
        } catch (\Throwable $e) {
            Log::error('Telegram sendMessage exception: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Gagal menghubungi server Telegram: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Uji koneksi bot Telegram dengan mengirim pesan percobaan
     */
    public function testConnection(?string $chatId = null, ?string $botToken = null): array
    {
        $namaInstansi = $this->getNamaInstansi();
        $now = Carbon::now('Asia/Jakarta');
        $waktuStr = $now->translatedFormat('l, d F Y - H:i').' WIB';
        $jamPengingat = $this->getJamPengiriman();

        $message = "<b>UJI KONEKSI BOT TELEGRAM BERHASIL</b>\n"
            ."<b>Lembaga:</b> {$namaInstansi}\n"
            ."<b>Waktu:</b> {$waktuStr}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            ."Bot Telegram telah terhubung dan siap mengirimkan:\n"
            ."• <b>Pengingat H-1 Kegiatan Kalender</b> (Jadwal: pukul <b>{$jamPengingat} WIB</b>)\n"
            ."• Notifikasi rekapitulasi & sistem\n\n"
            .'<i>Pesan ini dikirimkan otomatis sebagai pengujian koneksi dari menu Pengaturan.</i>';

        return $this->sendMessage($message, $chatId, $botToken);
    }

    /**
     * Memformat tanggal ke bahasa Indonesia
     */
    public function formatTanggalIndo(string $dateStr): string
    {
        try {
            $carbon = Carbon::parse($dateStr)->locale('id');

            return $carbon->translatedFormat('l, d F Y');
        } catch (\Throwable $e) {
            return $dateStr;
        }
    }

    /**
     * Membangun format pesan pengingat agenda H-1 (HTML Telegram)
     */
    public function buildPesanAgendaHMinus1(
        Collection|array $agendas,
        Collection|array $liburs,
        string $tanggalBesok
    ): string {
        $namaInstansi = $this->getNamaInstansi();
        $hariTanggalIndo = $this->formatTanggalIndo($tanggalBesok);

        $msg = "<b>PENGINGAT KEGIATAN TPQ (H-1)</b>\n"
            ."<b>{$namaInstansi}</b>\n"
            ."<b>Hari / Tanggal:</b> {$hariTanggalIndo}\n"
            ."━━━━━━━━━━━━━━━━━━━━\n\n"
            ."Memberitahukan agenda kegiatan yang dijadwalkan untuk <b>BESOK</b>:\n\n";

        if (! empty($agendas) && count($agendas) > 0) {
            foreach ($agendas as $idx => $agenda) {
                $no = $idx + 1;
                $judul = htmlspecialchars($agenda->judul ?? '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $kategori = htmlspecialchars($agenda->kategori ?? 'Umum', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $keterangan = htmlspecialchars($agenda->keterangan ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                $tglMulai = $agenda->tanggal ? Carbon::parse($agenda->tanggal)->format('d/m/Y') : '-';
                $tglSelesai = $agenda->tanggal_selesai ? Carbon::parse($agenda->tanggal_selesai)->format('d/m/Y') : $tglMulai;

                $periodeStr = ($tglMulai === $tglSelesai) ? $tglMulai : "{$tglMulai} s/d {$tglSelesai}";

                $msg .= "{$no}. <b>{$judul}</b>\n";
                $msg .= "   • <b>Kategori:</b> <i>{$kategori}</i>\n";
                $msg .= "   • <b>Periode:</b> {$periodeStr}\n";
                if (! empty($keterangan)) {
                    $msg .= "   • <b>Keterangan:</b> {$keterangan}\n";
                }
                $msg .= "\n";
            }
        }

        if (! empty($liburs) && count($liburs) > 0) {
            $msg .= "<b>Informasi Libur / Non-Aktif:</b>\n";
            foreach ($liburs as $libur) {
                $ketLibur = htmlspecialchars($libur->keterangan ?? 'Hari Libur', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $mulai = $libur->tanggal_mulai ? Carbon::parse($libur->tanggal_mulai)->format('d/m/Y') : '-';
                $selesai = $libur->tanggal_selesai ? Carbon::parse($libur->tanggal_selesai)->format('d/m/Y') : $mulai;
                $rangeLibur = ($mulai === $selesai) ? $mulai : "{$mulai} s/d {$selesai}";

                $msg .= "• {$ketLibur} ({$rangeLibur})\n";
            }
            $msg .= "\n";
        }

        $msg .= "Dimohon segenap ustadz/ustadzah, panitia, dan pengurus untuk mempersiapkan agenda kegiatan tersebut.\n"
            .'<i>Jazakumullah khairan katsiran.</i>';

        return $msg;
    }

    /**
     * Memeriksa dan mengirimkan notifikasi H-1 agenda kegiatan besok ke Telegram
     *
     * @param  bool  $force  Kirim paksa meskipun sudah pernah terkirim hari ini
     */
    public function sendAgendaReminderHMinus1(bool $force = false): array
    {
        if (! $this->isNotifAktif()) {
            return [
                'success' => false,
                'sent' => false,
                'message' => 'Pengingat kegiatan Telegram sedang dinonaktifkan di Pengaturan.',
            ];
        }

        $token = $this->getBotToken();
        $chatId = $this->getChatId();

        if (empty($token) || empty($chatId)) {
            return [
                'success' => false,
                'sent' => false,
                'message' => 'Token Bot atau Chat ID Telegram belum dikonfigurasi di Pengaturan.',
            ];
        }

        $tomorrow = Carbon::tomorrow('Asia/Jakarta')->format('Y-m-d');
        $cacheKey = "telegram_agenda_reminder_{$tomorrow}";

        if (! $force && Cache::has($cacheKey)) {
            return [
                'success' => true,
                'sent' => false,
                'message' => "Pengingat kegiatan untuk besok ({$tomorrow}) sudah pernah dikirim sebelumnya hari ini.",
            ];
        }

        // Cari agenda kegiatan yang berlangsung besok
        $agendas = Agenda::where(function ($q) use ($tomorrow) {
            $q->where('tanggal', $tomorrow)
                ->orWhere(function ($q2) use ($tomorrow) {
                    $q2->where('tanggal', '<=', $tomorrow)
                        ->where('tanggal_selesai', '>=', $tomorrow);
                });
        })->orderBy('tanggal', 'asc')->get();

        // Cari hari libur besok (bila ada)
        $liburs = HariLibur::where('tanggal_mulai', '<=', $tomorrow)
            ->where('tanggal_selesai', '>=', $tomorrow)
            ->get();

        if ($agendas->isEmpty() && $liburs->isEmpty()) {
            return [
                'success' => true,
                'sent' => false,
                'message' => "Tidak ada agenda kegiatan atau hari libur untuk besok ({$tomorrow}).",
            ];
        }

        $pesan = $this->buildPesanAgendaHMinus1($agendas, $liburs, $tomorrow);
        $sendResult = $this->sendMessage($pesan, $chatId, $token);

        if ($sendResult['success'] ?? false) {
            // Tandai sudah terkirim agar tidak duplikat (masa simpan 48 jam)
            Cache::put($cacheKey, true, 86400 * 2);

            $totalItem = $agendas->count() + $liburs->count();

            return [
                'success' => true,
                'sent' => true,
                'message' => "Pengingat H-1 berhasil dikirim ke Telegram ({$totalItem} agenda/libur besok).",
                'agendas_count' => $agendas->count(),
                'liburs_count' => $liburs->count(),
            ];
        }

        return [
            'success' => false,
            'sent' => false,
            'message' => $sendResult['message'] ?? 'Gagal mengirim pengingat ke Telegram.',
        ];
    }
}
