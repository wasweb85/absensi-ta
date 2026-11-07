<?php

namespace App\Services;

use App\Models\HariLibur;
use App\Models\Pengaturan;
use App\Models\TpqKnowledgeBase;
use App\Models\User;
use Carbon\Carbon;

class GetTpqInformation
{
    /** @var array<int, string> */
    protected array $bulanMap = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function __construct(
        protected KnowledgeBaseService $knowledgeBaseService
    ) {}

    /**
     * Hasilkan informasi hari libur TPQ secara to-the-point dari tabel hari_libur.
     */
    public function handleHolidayQuery(string $message = ''): string
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
     * Hasilkan informasi umum TPQ berdasarkan Knowledge Base dinamis dan Pengaturan instansi.
     */
    public function handleGeneralInfoQuery(string $message = '', ?string $topik = null): string
    {
        $namaInstansi = Pengaturan::getValue('nama_instansi', "TPQ Nurul Mun'im") ?: "TPQ Nurul Mun'im";
        $jamMasuk = Pengaturan::getValue('jam_masuk', '15:30') ?: '15:30';
        $batasTerlambat = Pengaturan::getValue('batas_terlambat', '16:00') ?: '16:00';
        $jamPulang = Pengaturan::getValue('jam_pulang', '17:00') ?: '17:00';
        $nominalSpp = (int) (Pengaturan::getValue('nominal_spp_default', 50000) ?: 50000);
        $nominalRp = 'Rp '.number_format($nominalSpp, 0, ',', '.');
        $namaBendahara = User::getNamaBendahara();
        $kontakBendahara = trim((string) (
            Pengaturan::getValue('no_hp_bendahara')
            ?: Pengaturan::getValue('kontak_bendahara')
            ?: '085235567818'
        )) ?: '085235567818';

        $matchedEntries = $this->knowledgeBaseService->search($message, $topik);

        $baseText = "🕌 *Informasi Umum {$namaInstansi}:*\n\n"
            ."⏰ *Jadwal & Jam Kegiatan Mengaji:*\n"
            ."• Jam Masuk: Pukul *{$jamMasuk} WIB*\n"
            ."• Batas Toleransi Hadir Tepat Waktu: Pukul *{$batasTerlambat} WIB*\n"
            ."• Jam Pulang: Pukul *{$jamPulang} WIB*\n\n"
            ."📋 *Tata Tertib & Presensi:*\n"
            ."• Setiap santri wajib melakukan scan kartu QR Code saat datang.\n"
            ."• Apabila berhalangan hadir (Izin/Sakit), wali santri dapat mengonfirmasi melalui WhatsApp ini.\n\n"
            ."💳 *Informasi Administrasi & SPP:*\n"
            ."• Syahriyah / SPP Bulanan: *{$nominalRp}* / bulan\n"
            ."• Bendahara TPQ: *{$namaBendahara}* (WA: *{$kontakBendahara}*)";

        // Tambahkan entri Knowledge Base relevan yang sesuai dengan pertanyaan wali santri
        $customLines = [];
        foreach ($matchedEntries->take(3) as $entry) {
            /** @var TpqKnowledgeBase $entry */
            if ((int) $entry->urutan > 5 || ($message !== '' && $this->entryDirectlyMatchesMessage($entry, $message))) {
                $customLines[] = "📌 *{$entry->judul}:*\n{$entry->konten}";
            }
        }

        if (! empty($customLines)) {
            return $baseText."\n\n".implode("\n\n", $customLines);
        }

        return $baseText;
    }

    /**
     * Susun payload terstruktur untuk Gemini Function Calling (`get_tpq_information`).
     *
     * @return array<string, mixed>
     */
    public function buildToolPayload(string $message = '', ?string $topik = null): array
    {
        $cleanTopik = strtolower(trim((string) ($topik ?? 'umum')));
        $isHoliday = str_contains($cleanTopik, 'libur') || (bool) preg_match('/\b(libur|tanggal merah)\b/i', $message);

        $kbEntries = $this->knowledgeBaseService->search($message, $cleanTopik)
            ->map(fn (TpqKnowledgeBase $kb) => [
                'id' => $kb->id,
                'kategori' => $kb->kategori,
                'judul' => $kb->judul,
                'konten' => $kb->konten,
            ])
            ->values()
            ->all();

        if ($isHoliday) {
            return [
                'status' => 'ok',
                'topik' => 'hari_libur',
                'knowledge_base' => $kbEntries,
                'ringkasan_resmi' => $this->handleHolidayQuery($message),
            ];
        }

        return [
            'status' => 'ok',
            'topik' => $cleanTopik ?: 'umum',
            'knowledge_base' => $kbEntries,
            'ringkasan_resmi' => $this->handleGeneralInfoQuery($message, $cleanTopik),
        ];
    }

    /**
     * Periksa apakah entri Knowledge Base cocok langsung dengan kata kunci pada pesan wali santri.
     */
    protected function entryDirectlyMatchesMessage(TpqKnowledgeBase $entry, string $message): bool
    {
        $cleanMsg = strtolower(trim($message));
        $judulLower = strtolower((string) $entry->judul);
        if ($judulLower !== '' && str_contains($cleanMsg, $judulLower)) {
            return true;
        }

        $keywords = array_filter(array_map('trim', explode(',', strtolower((string) $entry->kata_kunci))));
        foreach ($keywords as $kw) {
            if (strlen($kw) >= 3 && str_contains($cleanMsg, $kw)) {
                return true;
            }
        }

        return false;
    }
}
