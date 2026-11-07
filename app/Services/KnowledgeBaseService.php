<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Models\TpqKnowledgeBase;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class KnowledgeBaseService
{
    /**
     * Periksa ketersediaan tabel tpq_knowledge_bases di database.
     */
    public function hasKnowledgeBaseTable(): bool
    {
        try {
            return Schema::hasTable('tpq_knowledge_bases');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Pastikan tabel tpq_knowledge_bases tersedia di database (self-healing migration untuk serverless / Vercel).
     */
    public function ensureTableExists(): bool
    {
        if ($this->hasKnowledgeBaseTable()) {
            return true;
        }

        try {
            Artisan::call('migrate', ['--force' => true]);

            return $this->hasKnowledgeBaseTable();
        } catch (\Throwable $e) {
            Log::warning('KnowledgeBase auto-migrate failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Inisialisasi entri Knowledge Base bawaan TPQ Nurul Mun'im jika tabel masih kosong.
     */
    public function seedDefaultEntriesIfEmpty(): void
    {
        if (! $this->hasKnowledgeBaseTable()) {
            return;
        }

        if (TpqKnowledgeBase::count() > 0) {
            return;
        }

        $namaInstansi = Pengaturan::getValue('nama_instansi', "TPQ Nurul Mun'im") ?: "TPQ Nurul Mun'im";
        $jamMasuk = Pengaturan::getValue('jam_masuk', '15:30') ?: '15:30';
        $batasTerlambat = Pengaturan::getValue('batas_terlambat', '16:00') ?: '16:00';
        $jamPulang = Pengaturan::getValue('jam_pulang', '17:00') ?: '17:00';
        $nominalSpp = (int) (Pengaturan::getValue('nominal_spp_default', 50000) ?: 50000);
        $nominalRp = 'Rp '.number_format($nominalSpp, 0, ',', '.');
        $namaBendahara = User::getNamaBendahara();
        $kontakBendahara = trim((string) (Pengaturan::getValue('no_hp_bendahara') ?: '085235567818')) ?: '085235567818';

        $defaults = [
            [
                'kategori' => 'jadwal',
                'judul' => 'Jadwal Kegiatan Mengaji TPQ',
                'kata_kunci' => 'jadwal, kegiatan, ngaji, mengaji, hari aktif, masuk hari apa',
                'konten' => "Kegiatan pembelajaran mengaji di *{$namaInstansi}* dilaksanakan secara rutin setiap sore sesuai kalender akademik TPQ (di luar hari libur rutin mingguan dan tanggal merah nasional).",
                'is_active' => true,
                'urutan' => 1,
            ],
            [
                'kategori' => 'jam_operasional',
                'judul' => 'Jam Masuk dan Jam Pulang Santri',
                'kata_kunci' => 'jam masuk, jam pulang, jam berapa, operasional, terlambat, toleransi',
                'konten' => "• *Jam Masuk*: Pukul *{$jamMasuk} WIB*\n• *Batas Toleransi Tepat Waktu*: Pukul *{$batasTerlambat} WIB*\n• *Jam Pulang*: Pukul *{$jamPulang} WIB*",
                'is_active' => true,
                'urutan' => 2,
            ],
            [
                'kategori' => 'peraturan',
                'judul' => 'Peraturan dan Tata Tertib Santri TPQ',
                'kata_kunci' => 'peraturan, tata tertib, aturan, seragam, kartu qr, izin, sakit, sopan santun',
                'konten' => "1. Santri hadir tepat waktu dengan mengenakan busana muslim/seragam TPQ yang rapi dan sopan.\n2. Setiap santri wajib melakukan *scan Kartu QR Code* saat tiba di TPQ agar tercatat di sistem presensi.\n3. Apabila santri berhalangan hadir (Izin/Sakit), wali santri wajib menginformasikan kepada Wali Jilid atau melalui layanan WhatsApp ini.",
                'is_active' => true,
                'urutan' => 3,
            ],
            [
                'kategori' => 'pembayaran',
                'judul' => 'Informasi Pembayaran Syahriyah / SPP',
                'kata_kunci' => 'pembayaran, spp, syahriyah, biaya, bayar, bendahara, transfer, rekening',
                'konten' => "• Nominal SPP Bulanan: *{$nominalRp}* / bulan\n• Pembayaran dapat dilakukan secara tunai di Kantor Sekretariat TPQ atau melalui transfer lalu konfirmasi ke Bendahara TPQ (*{$namaBendahara}* - WA: *{$kontakBendahara}*).",
                'is_active' => true,
                'urutan' => 4,
            ],
            [
                'kategori' => 'administrasi',
                'judul' => 'Informasi Administrasi & Pendaftaran Santri',
                'kata_kunci' => 'administrasi, pendaftaran, daftar ulang, nisn, kartu santri, sekretariat, kantor',
                'konten' => "Pelayanan administrasi santri baru, cetak ulang Kartu QR Code, serta pembaruan data nomor WhatsApp wali santri dilayani di *Sekretariat {$namaInstansi}* pada jam operasional kegiatan sore.",
                'is_active' => true,
                'urutan' => 5,
            ],
        ];

        foreach ($defaults as $item) {
            TpqKnowledgeBase::create($item);
        }
    }

    /**
     * Ambil daftar seluruh entri Knowledge Base.
     *
     * @return Collection<int, TpqKnowledgeBase>
     */
    public function getAll(bool $onlyActive = false): Collection
    {
        $this->ensureTableExists();

        if (! $this->hasKnowledgeBaseTable()) {
            return collect();
        }

        $this->seedDefaultEntriesIfEmpty();

        $query = TpqKnowledgeBase::query()->orderBy('urutan')->orderBy('id');
        if ($onlyActive) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    /**
     * Cari entri Knowledge Base yang relevan berdasarkan pertanyaan atau kategori.
     *
     * @return Collection<int, TpqKnowledgeBase>
     */
    public function search(string $queryText = '', ?string $kategori = null): Collection
    {
        if (! $this->hasKnowledgeBaseTable()) {
            return collect();
        }

        $this->seedDefaultEntriesIfEmpty();

        $entries = TpqKnowledgeBase::where('is_active', true)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();

        if ($entries->isEmpty()) {
            return collect();
        }

        $cleanQuery = strtolower(trim($queryText));
        $cleanKat = $kategori ? strtolower(trim($kategori)) : '';

        if ($cleanQuery === '' && $cleanKat === '') {
            return $entries;
        }

        $scored = $entries->map(function (TpqKnowledgeBase $entry) use ($cleanQuery, $cleanKat) {
            $score = 0;
            $entryKat = strtolower((string) $entry->kategori);
            $judul = strtolower((string) $entry->judul);
            $keywords = array_filter(array_map('trim', explode(',', strtolower((string) $entry->kata_kunci))));
            $konten = strtolower((string) $entry->konten);

            if ($cleanKat !== '' && $cleanKat !== 'umum' && str_contains($entryKat, $cleanKat)) {
                $score += 10;
            }

            if ($cleanQuery !== '') {
                foreach ($keywords as $kw) {
                    if ($kw !== '' && str_contains($cleanQuery, $kw)) {
                        $score += 5;
                    }
                }

                $words = preg_split('/\s+/', preg_replace('/[^a-z0-9\s]/i', ' ', $cleanQuery) ?: '') ?: [];
                foreach ($words as $w) {
                    if (strlen($w) < 3 || in_array($w, ['tpq', 'apa', 'yang', 'dan', 'untuk', 'Bagaimana', 'gimana', 'info'], true)) {
                        continue;
                    }
                    if (str_contains($judul, $w)) {
                        $score += 3;
                    }
                    if (str_contains($entryKat, $w)) {
                        $score += 3;
                    }
                    if (str_contains($konten, $w)) {
                        $score += 1;
                    }
                }
            }

            return [
                'entry' => $entry,
                'score' => $score,
            ];
        });

        $matched = $scored->filter(fn (array $row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->pluck('entry')
            ->values();

        return $matched->isNotEmpty() ? $matched : $entries;
    }

    /**
     * Tambah entri Knowledge Base baru.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): TpqKnowledgeBase
    {
        $this->ensureTableExists();

        return TpqKnowledgeBase::create([
            'kategori' => $data['kategori'] ?? 'umum',
            'judul' => $data['judul'],
            'kata_kunci' => $data['kata_kunci'] ?? null,
            'konten' => $data['konten'],
            'is_active' => $data['is_active'] ?? true,
            'urutan' => (int) ($data['urutan'] ?? 0),
        ]);
    }

    /**
     * Perbarui entri Knowledge Base berdasarkan ID.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): TpqKnowledgeBase
    {
        $this->ensureTableExists();

        $entry = TpqKnowledgeBase::findOrFail($id);
        $entry->update($data);

        return $entry->fresh() ?? $entry;
    }

    /**
     * Hapus entri Knowledge Base berdasarkan ID.
     */
    public function delete(int $id): bool
    {
        $entry = TpqKnowledgeBase::findOrFail($id);

        return (bool) $entry->delete();
    }
}
