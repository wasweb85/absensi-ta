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
     * Hasilkan informasi umum TPQ berdasarkan Knowledge Base murni dan data instansi asli (tanpa data dummy).
     */
    public function handleGeneralInfoQuery(string $message = '', ?string $topik = null): string
    {
        $namaInstansi = Pengaturan::getValue('nama_instansi', "TPQ Nurul Mun'im") ?: "TPQ Nurul Mun'im";
        $matchedEntries = $this->knowledgeBaseService->search($message, $topik);

        // 1. Cek apakah ada entri Knowledge Base yang secara langsung cocok dengan pertanyaan
        $specificMatches = [];
        if ($message !== '' || ($topik !== null && $topik !== 'umum')) {
            foreach ($matchedEntries as $entry) {
                if ($this->entryDirectlyMatchesMessage($entry, $message) || ($topik !== null && $topik !== 'umum' && str_contains(strtolower((string) $entry->kategori), strtolower($topik)))) {
                    $specificMatches[] = $entry;
                }
            }
        }

        // Jika ada kecocokan spesifik, HANYA tampilkan topik yang dicari (tanpa mencampur data lain / dummy)
        if (! empty($specificMatches)) {
            $lines = ["🕌 *Informasi {$namaInstansi}:*"];
            foreach (array_slice($specificMatches, 0, 3) as $entry) {
                $lines[] = "📌 *{$entry->judul}:*\n{$entry->konten}";
            }

            return implode("\n\n", $lines);
        }

        // 2. Jika ada hasil pencarian Knowledge Base berbasis skor relevansi
        if ($message !== '' && $matchedEntries->isNotEmpty()) {
            $topEntries = $matchedEntries->take(2);
            $lines = ["🕌 *Informasi {$namaInstansi}:*"];
            foreach ($topEntries as $entry) {
                $lines[] = "📌 *{$entry->judul}:*\n{$entry->konten}";
            }

            return implode("\n\n", $lines);
        }

        // 3. Jika pertanyaan umum ("info tpq", "profil tpq"), tampilkan entri aktif dari database Knowledge Base
        $allActive = $this->knowledgeBaseService->getAll(true);
        if ($allActive->isNotEmpty()) {
            $lines = ["🕌 *Informasi Umum {$namaInstansi}:*"];
            foreach ($allActive->take(4) as $entry) {
                $lines[] = "📌 *{$entry->judul}:*\n{$entry->konten}";
            }

            return implode("\n\n", $lines);
        }

        // 4. Fallback bersih jika Knowledge Base belum diisi data sama sekali
        $namaBendahara = User::getNamaBendahara();
        $kontakBendahara = trim((string) (
            Pengaturan::getValue('no_hp_bendahara')
            ?: Pengaturan::getValue('kontak_bendahara')
            ?: '085235567818'
        )) ?: '085235567818';

        return "🕌 *Informasi {$namaInstansi}:*\n\n"
            ."Untuk informasi resmi seputar kegiatan, jam mengaji, dan administrasi TPQ Nurul Mun'im, silakan hubungi:\n"
            ."• Bendahara TPQ: *{$namaBendahara}* (WA: *{$kontakBendahara}*)";
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
        if ($cleanMsg === '') {
            return false;
        }

        $judulLower = strtolower((string) $entry->judul);
        if ($judulLower !== '' && str_contains($cleanMsg, $judulLower)) {
            return true;
        }

        $keywords = array_filter(array_map('trim', explode(',', strtolower((string) $entry->kata_kunci))));
        foreach ($keywords as $kw) {
            if ($kw === '') {
                continue;
            }

            // 1. Cek kecocokan langsung substring
            if (strlen($kw) >= 3 && str_contains($cleanMsg, $kw)) {
                return true;
            }

            // 2. Cek multi-kata (cth: "kartu hilang" cocok dengan "kalau kartu anak saya hilang gimana")
            $tokens = preg_split('/\s+/', $kw) ?: [];
            if (count($tokens) >= 2) {
                $allPresent = true;
                foreach ($tokens as $token) {
                    if (strlen($token) >= 3 && ! str_contains($cleanMsg, $token)) {
                        $allPresent = false;
                        break;
                    }
                }
                if ($allPresent) {
                    return true;
                }
            }
        }

        // Cek jika ada token kata kunci penting dari judul yang terdapat di pesan
        $words = preg_split('/\s+/', preg_replace('/[^a-z0-9\s]/i', ' ', $judulLower) ?: '') ?: [];
        foreach ($words as $w) {
            if (strlen($w) >= 4 && ! in_array($w, ['santri', 'informasi', 'tentang', 'data', 'pada'], true) && str_contains($cleanMsg, $w)) {
                return true;
            }
        }

        return false;
    }
}
