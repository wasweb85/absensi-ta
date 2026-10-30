<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class GoogleSheetSyncService
{
    public function __construct(
        protected WhatsAppService $whatsAppService
    ) {}

    /**
     * Ekstrak spreadsheet ID dari URL Google Sheets
     */
    public function extractSpreadsheetId(string $url): ?string
    {
        if (preg_match('/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/', trim($url), $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Otomatis mendeteksi GID tab sheet yang memuat kolom santri (NISN & Nama)
     */
    public function discoverSantriSheetGid(string $spreadsheetId): ?string
    {
        try {
            $htmlRes = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Laravel-Sync-Bot/1.0',
                ])
                ->get("https://docs.google.com/spreadsheets/d/{$spreadsheetId}/htmlview");

            if (! $htmlRes->successful()) {
                return null;
            }

            $body = $htmlRes->body();
            if (! preg_match_all('/gid=([0-9]+)/', $body, $matches)) {
                return null;
            }

            $gids = array_unique($matches[1]);
            $candidateGid = null;

            foreach ($gids as $gid) {
                try {
                    $csvRes = Http::timeout(5)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Laravel-Sync-Bot/1.0',
                        ])
                        ->get("https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format=csv&gid={$gid}");

                    if (! $csvRes->successful()) {
                        continue;
                    }

                    $firstLine = strtok($csvRes->body(), "\r\n");
                    if (! $firstLine) {
                        continue;
                    }

                    $headers = str_getcsv($firstLine);
                    $normalized = array_map(fn ($h) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $h)), $headers);

                    $hasNisn = false;
                    $hasNama = false;
                    $hasSantriDetail = false;

                    foreach ($normalized as $norm) {
                        if (in_array($norm, ['nisn', 'nis', 'noinduk', 'nomorinduk', 'idsantri', 'idsiswa'], true)) {
                            $hasNisn = true;
                        }
                        if (in_array($norm, ['namalengkap', 'nama', 'namasantri', 'namasiswa'], true)) {
                            $hasNama = true;
                        }
                        if (in_array($norm, ['namaayah', 'namaortu', 'nohp', 'nohandphone', 'jeniskelamin', 'alamat'], true)) {
                            $hasSantriDetail = true;
                        }
                    }

                    if ($hasNisn && $hasNama) {
                        if ($hasSantriDetail) {
                            return (string) $gid;
                        }
                        $candidateGid = (string) $gid;
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }

            return $candidateGid;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Konversi URL Google Sheets biasa menjadi URL export CSV langsung
     *
     * @throws Exception
     */
    public function extractExportCsvUrl(string $url): string
    {
        $trimmed = trim($url);

        // Validasi domain untuk mencegah SSRF
        $parsed = parse_url($trimmed);
        $host = strtolower($parsed['host'] ?? '');

        if (! in_array($host, ['docs.google.com', 'drive.google.com', 'sheets.googleapis.com'], true)) {
            throw new Exception('URL tidak valid. Harap gunakan link resmi dari Google Spreadsheet (docs.google.com).');
        }

        // Ekstrak ID spreadsheet
        $spreadsheetId = $this->extractSpreadsheetId($trimmed);
        if (! $spreadsheetId) {
            throw new Exception('Format URL Google Sheets tidak dikenali. Pastikan URL memuat ID spreadsheet yang valid.');
        }

        // Ekstrak GID (tab sheet), default ke 0
        $gid = '0';
        if (preg_match('/[#&?]gid=([0-9]+)/', $trimmed, $gidMatches)) {
            $gid = $gidMatches[1];
        }

        return "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format=csv&gid={$gid}";
    }

    /**
     * Ambil konten CSV dari Google Sheets dan parse menjadi array baris data
     *
     * @return array<int, array<string, string|null>>
     *
     * @throws Exception
     */
    public function fetchAndParse(string $url): array
    {
        $spreadsheetId = $this->extractSpreadsheetId($url);
        $hasExplicitGid = (bool) preg_match('/[#&?]gid=([0-9]+)/', $url);
        $exportUrl = $this->extractExportCsvUrl($url);

        // Jika URL generic (tidak ada gid spesifik), otomatis cari tab santri
        if (! $hasExplicitGid && $spreadsheetId) {
            $discoveredGid = $this->discoverSantriSheetGid($spreadsheetId);
            if ($discoveredGid !== null) {
                $exportUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format=csv&gid={$discoveredGid}";
            }
        }

        try {
            $response = Http::timeout(30)
                ->connectTimeout(15)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Laravel-Sync-Bot/1.0',
                ])
                ->get($exportUrl);
        } catch (Exception $e) {
            throw new Exception("Gagal menghubungi Google Sheets. Periksa koneksi internet Anda atau coba lagi nanti. ({$e->getMessage()})");
        }

        // Jika response gagal / 400 (karena gid=0 tidak ada pada spreadsheet tujuan), coba recover
        if (! $response->successful() && $spreadsheetId) {
            $fallbackUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format=csv";
            $fallbackRes = Http::timeout(20)->get($fallbackUrl);
            if ($fallbackRes->successful()) {
                $response = $fallbackRes;
            } elseif (! $hasExplicitGid) {
                $discoveredGid = $this->discoverSantriSheetGid($spreadsheetId);
                if ($discoveredGid !== null) {
                    $retryRes = Http::timeout(20)->get("https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format=csv&gid={$discoveredGid}");
                    if ($retryRes->successful()) {
                        $response = $retryRes;
                    }
                }
            }
        }

        if (! $response->successful()) {
            throw new Exception("Google Spreadsheet mengembalikan status HTTP {$response->status()}. Pastikan link dapat diakses publik (Siapa saja yang memiliki link).");
        }

        $body = $response->body();

        // Deteksi jika Google me-redirect ke login page (sheet masih private)
        if (str_contains($body, 'accounts.google.com') || str_contains($body, 'ServiceLogin') || str_contains($body, '<html')) {
            throw new Exception(
                "Spreadsheet tidak dapat diakses publik. Silakan buka Google Sheet Anda > klik tombol 'Bagikan' (Share) di kanan atas > ubah Akses Umum menjadi 'Siapa saja yang memiliki link' (Viewer/Pelihat)."
            );
        }

        // Hapus BOM UTF-8 jika ada
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);

        $lines = preg_split('/\r\n|\r|\n/', trim($body));
        if (empty($lines) || count($lines) < 2) {
            throw new Exception('Spreadsheet kosong atau hanya memiliki baris header tanpa data santri.');
        }

        // Deteksi delimiter dari baris header: koma, titik koma, atau tab
        $firstLine = $lines[0];
        $delimiters = [',', ';', "\t"];
        $chosenDelimiter = ',';
        $maxCount = 0;
        foreach ($delimiters as $delim) {
            $count = substr_count($firstLine, $delim);
            if ($count > $maxCount) {
                $maxCount = $count;
                $chosenDelimiter = $delim;
            }
        }

        $headers = str_getcsv($firstLine, $chosenDelimiter);
        $headerMap = $this->mapHeaders($headers);

        if (! isset($headerMap['nisn'])) {
            throw new Exception("Kolom 'NISN' tidak ditemukan pada baris pertama spreadsheet. Pastikan tab santri memiliki kolom bernama 'NISN' atau 'NIS'.");
        }

        if (! isset($headerMap['nama_lengkap'])) {
            throw new Exception("Kolom 'Nama' tidak ditemukan pada baris pertama spreadsheet. Pastikan tab santri memiliki kolom bernama 'Nama Lengkap' atau 'Nama'.");
        }

        $rows = [];
        $totalLines = count($lines);

        for ($i = 1; $i < $totalLines; $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            $cols = str_getcsv($line, $chosenDelimiter);
            if (empty(array_filter($cols, fn ($v) => trim((string) $v) !== ''))) {
                continue;
            }

            $rowNumber = $i + 1; // 1-indexed seperti di tampilan Google Sheets

            $rows[] = [
                '_row' => (string) $rowNumber,
                'nisn' => isset($headerMap['nisn']) ? trim((string) ($cols[$headerMap['nisn']] ?? '')) : '',
                'nama_lengkap' => isset($headerMap['nama_lengkap']) ? trim((string) ($cols[$headerMap['nama_lengkap']] ?? '')) : '',
                'kelas' => isset($headerMap['kelas']) ? trim((string) ($cols[$headerMap['kelas']] ?? '')) : '',
                'jenis_kelamin' => isset($headerMap['jenis_kelamin']) ? trim((string) ($cols[$headerMap['jenis_kelamin']] ?? '')) : 'L',
                'no_hp_ortu' => isset($headerMap['no_hp_ortu']) ? trim((string) ($cols[$headerMap['no_hp_ortu']] ?? '')) : '',
                'nama_ortu' => isset($headerMap['nama_ortu']) ? trim((string) ($cols[$headerMap['nama_ortu']] ?? '')) : '',
                'alamat' => isset($headerMap['alamat']) ? trim((string) ($cols[$headerMap['alamat']] ?? '')) : '',
                'status' => isset($headerMap['status']) ? trim((string) ($cols[$headerMap['status']] ?? '')) : 'aktif',
            ];
        }

        return $rows;
    }

    /**
     * Memetakan nama kolom header ke atribut yang diharapkan
     *
     * @param  array<int, string>  $headers
     * @return array<string, int>
     */
    protected function mapHeaders(array $headers): array
    {
        $map = [];

        foreach ($headers as $index => $raw) {
            $normalized = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $raw)));

            // NISN
            if (in_array($normalized, ['nisn', 'nis', 'noinduk', 'nomorinduk', 'idsantri', 'idsiswa'], true)) {
                $map['nisn'] = $index;
            }
            // Nama Lengkap
            elseif (in_array($normalized, ['namalengkap', 'nama', 'namasantri', 'namasiswa'], true)) {
                $map['nama_lengkap'] = $index;
            }
            // Kelas / Jilid
            elseif (in_array($normalized, ['kelas', 'jilid', 'namakelas', 'rombel', 'jenjang'], true)) {
                $map['kelas'] = $index;
            }
            // Jenis Kelamin
            elseif (in_array($normalized, ['jeniskelamin', 'jk', 'gender', 'lp'], true)) {
                $map['jenis_kelamin'] = $index;
            }
            // No HP Ortu / WhatsApp
            elseif (in_array($normalized, ['nohportu', 'nohp', 'nowa', 'waortu', 'telepon', 'whatsapp', 'hportu', 'kontakortu', 'nomorhp', 'hp', 'nohandphone', 'handphone', 'nomorhandphone', 'telp', 'ponsel', 'nohpwhatsapp'], true)) {
                $map['no_hp_ortu'] = $index;
            }
            // Nama Ortu
            elseif (in_array($normalized, ['namaortu', 'namawali', 'wali', 'orangtua', 'namaayah', 'namaibu', 'ayah', 'ibu'], true)) {
                if (! isset($map['nama_ortu']) || in_array($normalized, ['namaortu', 'namawali', 'namaayah', 'ayah'], true)) {
                    $map['nama_ortu'] = $index;
                }
            }
            // Alamat
            elseif (in_array($normalized, ['alamat', 'alamatrumah', 'domisili'], true)) {
                $map['alamat'] = $index;
            }
            // Status
            elseif (in_array($normalized, ['status', 'statussantri', 'keaktifan'], true)) {
                $map['status'] = $index;
            }
        }

        return $map;
    }

    /**
     * Sinkronkan data baris ke database dengan validasi presisi dan mekanisme Upsert
     *
     * @param  array<int, array<string, string|null>>  $rows
     * @return array{
     *     total_rows: int,
     *     created: int,
     *     updated: int,
     *     failed: int,
     *     errors: array<int, array{row: string, nisn: string, nama: string, reason: string}>,
     *     sample_preview: array<int, array<string, mixed>>
     * }
     */
    public function syncData(array $rows, bool $autoCreateKelas = true): array
    {
        $kelases = Kelas::all()->keyBy(function ($k) {
            return strtoupper(trim($k->nama_kelas));
        });

        $created = 0;
        $updated = 0;
        $errors = [];
        $samplePreview = [];
        $seenNisnsInBatch = [];

        $batchNisns = collect($rows)
            ->map(fn ($r) => trim((string) ($r['nisn'] ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existingSiswaMap = $batchNisns !== []
            ? Siswa::whereIn('nisn', $batchNisns)->get()->keyBy('nisn')
            : collect();

        $existingUserMap = $batchNisns !== []
            ? User::whereIn('nisn', $batchNisns)->orWhereIn('username', $batchNisns)->get()->keyBy(fn ($u) => $u->nisn ?: $u->username)
            : collect();

        foreach ($rows as $item) {
            $rowNum = (string) ($item['_row'] ?? '?');
            $nisn = trim((string) ($item['nisn'] ?? ''));
            $nama = trim((string) ($item['nama_lengkap'] ?? ''));
            $rawKelas = trim((string) ($item['kelas'] ?? ''));

            // Validasi data penting
            if ($nisn === '') {
                $errors[] = [
                    'row' => $rowNum,
                    'nisn' => '-',
                    'nama' => $nama ?: '-',
                    'reason' => 'NISN tidak boleh kosong.',
                ];

                continue;
            }

            if ($nama === '') {
                $errors[] = [
                    'row' => $rowNum,
                    'nisn' => $nisn,
                    'nama' => '-',
                    'reason' => 'Nama santri tidak boleh kosong.',
                ];

                continue;
            }

            // Cegah penimpaan santri jika terdapat NISN kembar dalam sheet yang sama
            if (isset($seenNisnsInBatch[$nisn])) {
                $prev = $seenNisnsInBatch[$nisn];
                $errors[] = [
                    'row' => $rowNum,
                    'nisn' => $nisn,
                    'nama' => $nama,
                    'reason' => "NISN '{$nisn}' kembar dengan Baris #{$prev['row']} ({$prev['nama']}). Harap bedakan NISN-nya di spreadsheet.",
                ];

                continue;
            }

            $seenNisnsInBatch[$nisn] = [
                'row' => $rowNum,
                'nama' => $nama,
            ];

            // Normalisasi Kelas
            $lookupKelas = strtoupper($rawKelas);
            $kelas = $kelases->get($lookupKelas);

            if (! $kelas) {
                if ($rawKelas === '') {
                    $errors[] = [
                        'row' => $rowNum,
                        'nisn' => $nisn,
                        'nama' => $nama,
                        'reason' => 'Kolom Jilid/Kelas kosong.',
                    ];

                    continue;
                }

                if ($autoCreateKelas) {
                    // Buat kelas otomatis jika belum ada di database
                    $kelas = Kelas::create([
                        'nama_kelas' => $rawKelas,
                        'warna_label' => '#059669',
                    ]);
                    $kelases->put($lookupKelas, $kelas);
                } else {
                    $errors[] = [
                        'row' => $rowNum,
                        'nisn' => $nisn,
                        'nama' => $nama,
                        'reason' => "Jilid '{$rawKelas}' belum terdaftar di sistem.",
                    ];

                    continue;
                }
            }

            // Normalisasi Jenis Kelamin
            $rawJk = strtoupper(trim((string) ($item['jenis_kelamin'] ?? 'L')));
            $jk = (str_starts_with($rawJk, 'P') || str_contains($rawJk, 'PEREMPUAN') || str_contains($rawJk, 'WANITA')) ? 'P' : 'L';

            // Normalisasi No HP Ortu
            $rawPhone = trim((string) ($item['no_hp_ortu'] ?? ''));
            $normalizedPhone = $rawPhone !== '' ? $this->whatsAppService->normalizePhone($rawPhone) : null;

            // Status Keaktifan
            $rawStatus = strtolower(trim((string) ($item['status'] ?? 'aktif')));
            $status = in_array($rawStatus, ['aktif', 'nonaktif', 'lulus', 'pindah'], true) ? $rawStatus : 'aktif';

            $namaOrtu = trim((string) ($item['nama_ortu'] ?? '')) ?: null;
            $alamat = trim((string) ($item['alamat'] ?? '')) ?: null;

            // Transaksi per record agar tidak merusak record lain jika ada glitch
            try {
                DB::transaction(function () use (
                    $nisn, $nama, $jk, $kelas, $normalizedPhone, $namaOrtu, $alamat, $status,
                    &$created, &$updated, $existingSiswaMap, $existingUserMap
                ) {
                    $existing = $existingSiswaMap->get($nisn);

                    if ($existing) {
                        $existing->update([
                            'nama_lengkap' => $nama,
                            'jenis_kelamin' => $jk,
                            'id_kelas' => $kelas->id,
                            'no_hp_ortu' => $normalizedPhone ?? $existing->no_hp_ortu,
                            'nama_ortu' => $namaOrtu ?? $existing->nama_ortu,
                            'alamat' => $alamat ?? $existing->alamat,
                            'status' => $status,
                        ]);
                        $updated++;
                    } else {
                        $newSiswa = Siswa::create([
                            'nisn' => $nisn,
                            'nama_lengkap' => $nama,
                            'jenis_kelamin' => $jk,
                            'id_kelas' => $kelas->id,
                            'no_hp_ortu' => $normalizedPhone,
                            'nama_ortu' => $namaOrtu,
                            'alamat' => $alamat,
                            'status' => $status,
                        ]);
                        $existingSiswaMap->put($nisn, $newSiswa);
                        $created++;
                    }

                    // Sinkronkan atau buat akun User login santri
                    $user = $existingUserMap->get($nisn);
                    if ($user) {
                        $user->update([
                            'name' => $nama,
                            'id_kelas' => $kelas->id,
                            'nisn' => $nisn,
                        ]);
                    } else {
                        $newUser = User::create([
                            'name' => $nama,
                            'username' => $nisn,
                            'email' => $nisn.'@santri.sekolah.id',
                            'password' => Hash::make($nisn),
                            'role' => 'siswa',
                            'id_kelas' => $kelas->id,
                            'nisn' => $nisn,
                        ]);
                        $existingUserMap->put($nisn, $newUser);
                    }
                });

                if (count($samplePreview) < 3) {
                    $samplePreview[] = [
                        'nisn' => $nisn,
                        'nama_lengkap' => $nama,
                        'kelas' => $kelas->nama_kelas,
                        'no_hp_ortu' => $normalizedPhone,
                        'status' => $status,
                    ];
                }
            } catch (Exception $e) {
                $errors[] = [
                    'row' => $rowNum,
                    'nisn' => $nisn,
                    'nama' => $nama,
                    'reason' => 'Gagal simpan ke database: '.$e->getMessage(),
                ];
            }
        }

        return [
            'total_rows' => count($rows),
            'created' => $created,
            'updated' => $updated,
            'failed' => count($errors),
            'errors' => $errors,
            'sample_preview' => $samplePreview,
        ];
    }
}
