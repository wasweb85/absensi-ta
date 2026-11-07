<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\ActivityLog;
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

        // 1. Deteksi intent awal dari teks pesan melalui IntentService
        $intent = $this->detectIntent($trimmedMessage, $normalizedSender);

        // 2. Identifikasi Santri berdasarkan nomor pengirim, nama panggilan, nomor urut, atau sesi database
        $resolved = $this->resolveStudent($normalizedSender, $trimmedMessage);

        // Keamanan (Section 10): Nomor yang belum terdaftar hanya boleh mengakses informasi hari libur umum (agenda)
        if ($resolved['status'] === 'unregistered' && $intent !== 'agenda') {
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

        // 3. Hybrid AI Conversation Engine (Gemini API + Database Context + Function Calling)
        // Jika Gemini API terkonfigurasi, proses menggunakan AI Engine; jika gagal/timeout atau tidak aktif, fallback ke mesin lokal
        $geminiService = app(GeminiService::class);
        if ($geminiService->isConfigured()) {
            $candidates = $this->findCandidatesByPhone($normalizedSender);
            $aiOutcome = $this->processWithGeminiEngine(
                $normalizedSender,
                $trimmedMessage,
                $intent,
                $resolved,
                $candidates,
                $geminiService
            );

            if ($aiOutcome !== null) {
                /** @var Siswa|null $aiSiswa */
                $aiSiswa = $aiOutcome['siswa'];
                $log = $this->logInteraction(
                    $normalizedSender,
                    $trimmedMessage,
                    $aiOutcome['intent'],
                    $aiSiswa?->id,
                    $aiOutcome['reply'],
                    'sukses'
                );

                return [
                    'success' => true,
                    'intent' => $aiOutcome['intent'],
                    'reply' => $aiOutcome['reply'],
                    'siswa' => $aiSiswa ? $this->formatSiswaPayload($aiSiswa) : null,
                    'log_id' => $log->id,
                    'engine' => 'gemini_hybrid',
                ];
            }
        }

        // Pertanyaan umum institusi (Agenda & Hari Libur / Info TPQ) langsung dijawab tanpa terhalang pemilihan multi-santri
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

        if ($intent === 'info_tpq') {
            $reply = $this->handleInfoTpqQuery($trimmedMessage);
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
     * Klasifikasi intent dari teks pesan bahasa sehari-hari wali santri melalui IntentService
     */
    public function detectIntent(string $message, ?string $normalizedSender = null): string
    {
        return app(IntentService::class)->detectIntent($message, $normalizedSender);
    }

    /**
     * Hybrid AI Conversation Engine menggunakan Google Gemini API + Function Calling + Konteks Percakapan.
     * Mengembalikan null jika Gemini tidak merespons atau mengaktifkan mekanisme fallback otomatis.
     *
     * @param  array<string, mixed>  $resolved
     * @param  Collection<int, Siswa>  $candidates
     * @return array{intent: string, reply: string, siswa: Siswa|null}|null
     */
    protected function processWithGeminiEngine(
        string $normalizedSender,
        string $message,
        string $initialIntent,
        array $resolved,
        Collection $candidates,
        GeminiService $geminiService
    ): ?array {
        try {
            $conversationService = app(ConversationService::class);
            /** @var Siswa|null $activeSiswa */
            $activeSiswa = $resolved['siswa'] ?? null;

            if (! $activeSiswa && $candidates->count() === 1) {
                $activeSiswa = $candidates->first();
            }

            $historyContents = [];
            if ($conversationService->hasConversationTables()) {
                $conversation = $conversationService->getOrCreateConversation($normalizedSender, $activeSiswa?->id);
                $historyContents = $conversationService->getGeminiHistory($conversation, 6);
            }

            $historyContents[] = [
                'role' => 'user',
                'parts' => [
                    ['text' => $message],
                ],
            ];

            $finalIntent = $initialIntent;
            $systemInstruction = $this->buildGeminiSystemInstruction($candidates, $activeSiswa);
            $functionDeclarations = $this->getGeminiFunctionDeclarations();

            $toolExecutor = function (string $toolName, array $args) use (
                $candidates,
                &$activeSiswa,
                &$finalIntent,
                $normalizedSender,
                $message
            ): array {
                $intentService = app(IntentService::class);
                $finalIntent = $intentService->mapToolToIntent($toolName, $finalIntent);

                return $this->executeGeminiTool(
                    $toolName,
                    $args,
                    $candidates,
                    $activeSiswa,
                    $normalizedSender,
                    $message
                );
            };

            $aiResult = $geminiService->generateContent(
                contents: $historyContents,
                systemInstruction: $systemInstruction,
                functionDeclarations: $functionDeclarations,
                toolExecutor: $toolExecutor
            );

            if (! ($aiResult['success'] ?? false) || ($aiResult['fallback'] ?? false) || empty(trim((string) ($aiResult['reply'] ?? '')))) {
                return null;
            }

            if ($finalIntent === 'unknown') {
                $finalIntent = $activeSiswa ? 'bantuan' : 'info_tpq';
            }

            return [
                'intent' => $finalIntent,
                'reply' => trim((string) $aiResult['reply']),
                'siswa' => $activeSiswa,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Bangun System Instruction untuk Gemini AI sesuai gaya komunikasi TPQ Nurul Mun'im (Section 11).
     *
     * @param  Collection<int, Siswa>  $candidates
     */
    public function buildGeminiSystemInstruction(Collection $candidates, ?Siswa $activeSiswa = null): string
    {
        $namaInstansi = Pengaturan::getValue('nama_instansi', "TPQ Nurul Mun'im") ?: "TPQ Nurul Mun'im";
        $hariIni = Carbon::now('Asia/Jakarta')->locale('id')->isoFormat('dddd, D MMMM Y');
        $bendahara = $this->getBendaharaInfo();

        $santriContext = 'Wali santri belum terhubung ke data santri spesifik.';
        if ($candidates->count() === 1) {
            $s = $candidates->first();
            $kelas = $s->kelas?->nama_kelas ?: '-';
            $santriContext = "Wali dari 1 santri terdaftar: ID={$s->id}, Nama={$s->nama_lengkap}, Kelas/Jilid={$kelas}.";
        } elseif ($candidates->count() > 1) {
            $list = $candidates->map(fn (Siswa $s, int $idx) => ($idx + 1).". ID={$s->id}, Nama={$s->nama_lengkap} (".($s->kelas?->nama_kelas ?: '-').')')->implode('; ');
            $activeStr = $activeSiswa ? "Santri yang sedang dibahas saat ini: ID={$activeSiswa->id} ({$activeSiswa->nama_lengkap})." : 'Belum ada santri spesifik yang dipilih pada sesi ini.';
            $santriContext = "Wali dari {$candidates->count()} santri terdaftar: [{$list}]. {$activeStr}";
        }

        return "Anda adalah Asisten Layanan Informasi WhatsApp resmi {$namaInstansi}.\n"
            ."Tanggal hari ini: {$hariIni} (Zona waktu: Asia/Jakarta WIB).\n"
            ."Konteks Wali Santri: {$santriContext}\n"
            ."Kontak Bendahara TPQ: {$bendahara['nama']} (WA: {$bendahara['kontak']}).\n\n"
            ."ATURAN KOMUNIKASI & KEAMANAN WAJIB:\n"
            ."1. Gunakan bahasa Indonesia yang ramah, sopan, natural, tidak kaku, tidak terlalu panjang, dan mudah dipahami Bapak/Ibu wali santri.\n"
            ."2. Pahami pertanyaan bahasa sehari-hari, singkatan, typo, dan pertanyaan lanjutan (seperti 'kalau bulan lalu?', 'anak saya yang satunya?', 'bayarnya ke siapa?') berdasarkan konteks percakapan tanpa meminta ulang informasi yang sudah jelas.\n"
            ."3. WAJIB memanggil fungsi (tool) yang tersedia (`check_attendance`, `check_spp_payment`, `check_spp_arrears`, `get_tpq_information`) untuk mengambil data nyata dari database. DILARANG mengarang data kehadiran, jam masuk, atau nominal tagihan SPP.\n"
            ."4. Hanya berikan data santri yang terdaftar pada konteks wali santri di atas. Jangan pernah menampilkan data santri lain.\n"
            ."5. Jika wali memiliki lebih dari 1 anak dan menanyakan satu anak tanpa menyebut nama, Anda dapat memanggil fungsi untuk semua anak wali tersebut atau menanyakan dengan sopan nama ananda yang dimaksud.\n"
            .'6. Jika pertanyaan di luar layanan TPQ atau Anda tidak mengetahui jawabannya, sampaikan secara jujur dan tawarkan bantuan untuk menghubungi Administrator / Bendahara TPQ.';
    }

    /**
     * Daftar deklarasi fungsi (Function Calling) untuk Gemini API.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getGeminiFunctionDeclarations(): array
    {
        return [
            [
                'name' => 'check_attendance',
                'description' => 'Memeriksa data kehadiran/absensi santri di TPQ (kehadiran hari ini, jam masuk, izin kemarin, atau rekap kehadiran bulanan).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'santri_id' => [
                            'type' => 'INTEGER',
                            'description' => 'ID santri yang ingin diperiksa (opsional jika wali hanya punya 1 santri atau ingin cek semua anak).',
                        ],
                        'nama_santri' => [
                            'type' => 'STRING',
                            'description' => 'Nama panggilan atau nama lengkap santri yang ditanyakan oleh wali.',
                        ],
                        'periode' => [
                            'type' => 'STRING',
                            'description' => 'Periode waktu absensi yang ditanyakan, misalnya: hari_ini, kemarin, bulan_ini, bulan_lalu, atau nama bulan (cth: Oktober 2026).',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'check_spp_payment',
                'description' => 'Memeriksa status pembayaran SPP santri (apakah SPP bulan ini/tertentu sudah dibayar, pembayaran terakhir, dan cara/kontak konfirmasi pembayaran ke Bendahara).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'santri_id' => [
                            'type' => 'INTEGER',
                            'description' => 'ID santri yang ingin diperiksa.',
                        ],
                        'nama_santri' => [
                            'type' => 'STRING',
                            'description' => 'Nama santri yang ditanyakan.',
                        ],
                        'periode' => [
                            'type' => 'STRING',
                            'description' => 'Periode bulan SPP yang ditanyakan (cth: bulan_ini, bulan_lalu, Oktober 2026).',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'check_spp_arrears',
                'description' => 'Memeriksa apakah santri masih mempunyai tunggakan SPP, total nominal tunggakan, rincian bulan yang belum lunas, dan cara melunasi tunggakan.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'santri_id' => [
                            'type' => 'INTEGER',
                            'description' => 'ID santri yang ingin diperiksa tunggakannya.',
                        ],
                        'nama_santri' => [
                            'type' => 'STRING',
                            'description' => 'Nama santri yang ingin diperiksa tunggakannya.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'get_tpq_information',
                'description' => 'Mengambil informasi umum TPQ Nurul Mun\'im seperti jadwal kegiatan mengaji, jam masuk dan pulang, hari libur, peraturan TPQ, informasi pembayaran, dan administrasi.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'topik' => [
                            'type' => 'STRING',
                            'description' => 'Topik informasi TPQ: jadwal, hari_libur, peraturan, pembayaran, administrasi, atau umum.',
                        ],
                        'pertanyaan' => [
                            'type' => 'STRING',
                            'description' => 'Kalimat pertanyaan wali santri terkait informasi TPQ.',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Eksekutor fungsi (Tool Executor) yang dijalankan oleh Laravel dengan validasi keamanan ketat (Section 10).
     *
     * @param  array<string, mixed>  $args
     * @param  Collection<int, Siswa>  $candidates
     * @return array<string, mixed>
     */
    protected function executeGeminiTool(
        string $toolName,
        array $args,
        Collection $candidates,
        ?Siswa &$activeSiswa,
        string $normalizedSender,
        string $originalMessage
    ): array {
        if ($toolName === 'get_tpq_information') {
            $topik = strtolower(trim((string) ($args['topik'] ?? 'umum')));
            $queryText = (string) ($args['pertanyaan'] ?? $originalMessage);

            if (str_contains($topik, 'libur') || preg_match('/\b(libur|tanggal merah)\b/i', $queryText)) {
                return [
                    'status' => 'ok',
                    'topik' => 'hari_libur',
                    'ringkasan_resmi' => $this->handleAgendaQuery($queryText),
                ];
            }

            return [
                'status' => 'ok',
                'topik' => $topik,
                'ringkasan_resmi' => $this->handleInfoTpqQuery($queryText),
            ];
        }

        // Untuk fungsi data pribadi (absensi & SPP), wajib memvalidasi kepemilikan santri terhadap nomor WA wali
        if ($candidates->isEmpty()) {
            return [
                'status' => 'unauthorized',
                'error' => 'Nomor WhatsApp pengirim belum terdaftar sebagai wali santri.',
            ];
        }

        $targetResolution = $this->resolveTargetStudentForTool($args, $candidates, $activeSiswa, $normalizedSender);
        if (($targetResolution['status'] ?? '') === 'unauthorized') {
            return [
                'status' => 'unauthorized',
                'error' => $targetResolution['error'] ?? 'Akses data santri lain ditolak.',
            ];
        }

        /** @var Siswa|null $selectedSiswa */
        $selectedSiswa = $targetResolution['siswa'] ?? null;
        if ($selectedSiswa) {
            $activeSiswa = $selectedSiswa;
            $this->rememberActiveStudent($normalizedSender, $selectedSiswa->id);
        }

        $periodHint = trim((string) ($args['periode'] ?? '')) !== ''
            ? (string) $args['periode'].' '.$originalMessage
            : $originalMessage;

        return match ($toolName) {
            'check_attendance' => [
                'status' => 'ok',
                'santri' => $selectedSiswa ? $this->formatSiswaPayload($selectedSiswa) : 'semua_anak_wali',
                'data_absensi' => $selectedSiswa
                    ? $this->handleAbsensiQuery($selectedSiswa, $periodHint)
                    : $this->handleMultiAbsensiQuery($candidates, $periodHint),
            ],
            'check_spp_payment', 'check_spp_arrears' => [
                'status' => 'ok',
                'santri' => $selectedSiswa ? $this->formatSiswaPayload($selectedSiswa) : 'semua_anak_wali',
                'data_spp' => $selectedSiswa
                    ? $this->handleSppNoticeQuery($selectedSiswa, null, $periodHint)
                    : $this->handleSppNoticeQuery($candidates->first(), $candidates, $periodHint),
                'bendahara' => $this->getBendaharaInfo(),
            ],
            default => [
                'status' => 'error',
                'error' => "Fungsi {$toolName} tidak dikenali.",
            ],
        };
    }

    /**
     * Validasi parameter santri_id / nama_santri dari AI agar hanya mengakses santri milik wali yang bersangkutan.
     *
     * @param  array<string, mixed>  $args
     * @param  Collection<int, Siswa>  $candidates
     * @return array{status: string, siswa?: Siswa|null, error?: string}
     */
    protected function resolveTargetStudentForTool(
        array $args,
        Collection $candidates,
        ?Siswa $activeSiswa,
        string $normalizedSender
    ): array {
        // 1. Jika AI mengirimkan santri_id eksplisit, wajib termasuk dalam daftar anak milik nomor WA ini
        if (! empty($args['santri_id'])) {
            $requestedId = (int) $args['santri_id'];
            $matched = $candidates->firstWhere('id', $requestedId);
            if (! $matched) {
                return [
                    'status' => 'unauthorized',
                    'error' => 'Santri dengan ID tersebut tidak terdaftar pada nomor WhatsApp Bapak/Ibu.',
                ];
            }

            return ['status' => 'found', 'siswa' => $matched];
        }

        // 2. Jika AI mengirimkan nama_santri
        if (! empty($args['nama_santri'])) {
            $nama = strtolower(trim((string) $args['nama_santri']));
            foreach ($candidates as $cand) {
                if (str_contains(strtolower($cand->nama_lengkap), $nama)) {
                    return ['status' => 'found', 'siswa' => $cand];
                }
            }

            return [
                'status' => 'unauthorized',
                'error' => "Santri bernama '{$args['nama_santri']}' tidak terdaftar pada nomor WhatsApp Bapak/Ibu.",
            ];
        }

        // 3. Jika wali hanya punya 1 anak, otomatis gunakan anak tersebut
        if ($candidates->count() === 1) {
            return ['status' => 'found', 'siswa' => $candidates->first()];
        }

        // 4. Gunakan santri aktif dari konteks percakapan jika ada
        if ($activeSiswa && $candidates->contains('id', $activeSiswa->id)) {
            return ['status' => 'found', 'siswa' => $activeSiswa];
        }

        $rememberedId = $this->getRememberedActiveStudentId($normalizedSender);
        if ($rememberedId) {
            $remembered = $candidates->firstWhere('id', $rememberedId);
            if ($remembered) {
                return ['status' => 'found', 'siswa' => $remembered];
            }
        }

        // 5. Jika wali punya >1 anak dan belum memilih spesifik, kembalikan semua anak milik wali tersebut
        return ['status' => 'found_all', 'siswa' => null];
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
     * Simpan ID santri aktif ke Cache, Pengaturan, dan tabel chat_conversations
     */
    protected function rememberActiveStudent(string $normalizedPhone, int $siswaId): void
    {
        Cache::put("wa_active_student_{$normalizedPhone}", $siswaId, now()->addMinutes(30));

        try {
            $conversationService = app(ConversationService::class);
            if ($conversationService->hasConversationTables()) {
                $conversation = $conversationService->getOrCreateConversation($normalizedPhone, $siswaId);
                $conversationService->updateActiveSantri($conversation, $siswaId, 'active');
            }
        } catch (\Throwable) {
        }

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
     * Ambil ID santri aktif dari Cache, chat_conversations, Pengaturan, atau riwayat ChatbotLog 30 menit terakhir
     */
    protected function getRememberedActiveStudentId(string $normalizedPhone): ?int
    {
        $cachedId = Cache::get("wa_active_student_{$normalizedPhone}");
        if ($cachedId) {
            return (int) $cachedId;
        }

        try {
            $conversationService = app(ConversationService::class);
            $convSantriId = $conversationService->getActiveSantriId($normalizedPhone, 30);
            if ($convSantriId) {
                return $convSantriId;
            }
        } catch (\Throwable) {
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
            $conversationService = app(ConversationService::class);
            if ($conversationService->hasConversationTables()) {
                $conversation = $conversationService->getOrCreateConversation($normalizedPhone, null);
                $conversationService->updateActiveSantri($conversation, null, 'active');
            }
        } catch (\Throwable) {
        }

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
        try {
            $conversationService = app(ConversationService::class);
            $convIntent = $conversationService->getLastConversationIntent($normalizedPhone, 30);
            if ($convIntent) {
                return $convIntent;
            }
        } catch (\Throwable) {
        }

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
            '/\b(kepada siapa|ke siapa|sama siapa|lewat siapa|hubungi siapa|(?:bayar|membayar)\s+ke\s+siapa|(?:bayar|membayar)\s+ke\s*mana|ke\s*mana\s+(?:bayar|membayar)|ke\s*mana kalau mau (?:bayar|membayar)|cara\s+(?:bayar|membayar)|lewat mana|rekening|transfer|bendahara|mau\s+(?:bayar|membayar)|ingin\s+(?:bayar|membayar)|pengen\s+(?:bayar|membayar))\b/i',
            $clean
        );

        $isAskingSppStatus = (bool) preg_match(
            '/\b(nunggak|menunggak|tunggak|tunggakan|tagihan|berapa|cek|rincian|status|lunas|belum lunas|belum bayar|kurang bayar|kuitansi|rekap|daftar)\b/i',
            $clean
        );

        // Jika wali HANYA bertanya kepada siapa/kemana/cara membayar (tanpa meminta status tagihan),
        // langsung jawab tujuan pembayaran ke Bendahara secara to-the-point tanpa memborbardir status tagihan anak
        if ($isAskingPaymentTarget && ! $isAskingSppStatus) {
            return $this->buildDirectPaymentGuideReply($siswa, $allCandidates);
        }

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
     * Balasan langsung dan to-the-point saat wali santri hanya menanyakan kepada siapa/ke mana membayar SPP
     *
     * @param  Collection<int, Siswa>|null  $allCandidates
     */
    public function buildDirectPaymentGuideReply(?Siswa $siswa = null, ?Collection $allCandidates = null): string
    {
        $bendaharaInfo = $this->getBendaharaInfo();
        $namaBendahara = $bendaharaInfo['nama'];
        $kontak = $bendaharaInfo['kontak'] ?: '085235567818';
        $rekeningSpp = trim((string) Pengaturan::getValue('rekening_spp'));

        $lines = [
            'Pembayaran SPP dapat diselesaikan melalui Bendahara:',
            "• Bendahara: {$namaBendahara}",
            "• No. WhatsApp / HP: {$kontak}",
        ];
        if (! empty($rekeningSpp)) {
            $lines[] = "• Rekening: {$rekeningSpp}";
        }
        $lines[] = '• Lokasi: Kantor / Sekretariat TPQ';
        $lines[] = '';
        $lines[] = 'Besar harapan kami agar Bapak/Ibu dapat segera menyelesaikan kewajiban administrasi ini melalui Bendahara.';
        $lines[] = '';
        $lines[] = 'Untuk melihat rincian tagihan ananda, silakan ketik: *"Cek SPP"*';

        return implode("\n", $lines);
    }

    /**
     * Menghasilkan catatan panduan pembayaran SPP ke Bendahara
     */
    protected function buildSppPaymentNotice(bool $isAskingPaymentTarget, bool $hasTunggakan): string
    {
        $bendaharaInfo = $this->getBendaharaInfo();
        $namaBendahara = $bendaharaInfo['nama'];
        $kontak = $bendaharaInfo['kontak'] ?: '085235567818';
        $rekeningSpp = trim((string) Pengaturan::getValue('rekening_spp'));

        if ($isAskingPaymentTarget) {
            $lines = [
                'Pembayaran SPP dapat diselesaikan melalui Bendahara:',
                "• Bendahara: {$namaBendahara}",
                "• No. WhatsApp / HP: {$kontak}",
            ];
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
            $lines = [
                'Untuk pembayaran & konfirmasi SPP, silakan hubungi Bendahara:',
                "• Bendahara: {$namaBendahara}",
                "• No. WhatsApp / HP: {$kontak}",
            ];
            if (! empty($rekeningSpp)) {
                $lines[] = "• Rekening: {$rekeningSpp}";
            }
            $lines[] = '• Lokasi: Kantor / Sekretariat TPQ';
            $lines[] = '';
            $lines[] = 'Besar harapan kami agar Bapak/Ibu dapat segera menyelesaikan kewajiban administrasi ini melalui Bendahara.';

            return implode("\n", $lines);
        }

        return "Informasi & konfirmasi SPP silakan hubungi Bendahara:\n• {$namaBendahara} (No. WA: {$kontak})";
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
            ?: '085235567818'
        ));

        if (empty($kontakBendahara)) {
            $kontakBendahara = '085235567818';
        }

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
     * Menghasilkan informasi umum TPQ (Jadwal kegiatan, Jam masuk & pulang, Peraturan, Administrasi)
     */
    public function handleInfoTpqQuery(string $message = ''): string
    {
        $namaInstansi = Pengaturan::getValue('nama_instansi', "TPQ Nurul Mun'im") ?: "TPQ Nurul Mun'im";
        $jamMasuk = Pengaturan::getValue('jam_masuk', '15:30') ?: '15:30';
        $batasTerlambat = Pengaturan::getValue('batas_terlambat', '16:00') ?: '16:00';
        $jamPulang = Pengaturan::getValue('jam_pulang', '17:00') ?: '17:00';
        $nominalSpp = (int) (Pengaturan::getValue('nominal_spp_default', 50000) ?: 50000);
        $nominalRp = 'Rp '.number_format($nominalSpp, 0, ',', '.');
        $bendahara = $this->getBendaharaInfo();

        return "🕌 *Informasi Umum {$namaInstansi}:*\n\n"
            ."⏰ *Jadwal & Jam Kegiatan Mengaji:*\n"
            ."• Jam Masuk: Pukul *{$jamMasuk} WIB*\n"
            ."• Batas Toleransi Hadir Tepat Waktu: Pukul *{$batasTerlambat} WIB*\n"
            ."• Jam Pulang: Pukul *{$jamPulang} WIB*\n\n"
            ."📋 *Tata Tertib & Presensi:*\n"
            ."• Setiap santri wajib melakukan scan kartu QR Code saat datang.\n"
            ."• Apabila berhalangan hadir (Izin/Sakit), wali santri dapat mengonfirmasi melalui WhatsApp ini.\n\n"
            ."💳 *Informasi Administrasi & SPP:*\n"
            ."• Syahriyah / SPP Bulanan: *{$nominalRp}* / bulan\n"
            ."• Bendahara TPQ: *{$bendahara['nama']}* (WA: *{$bendahara['kontak']}*)";
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
        $kontakBendahara = $this->getBendaharaInfo()['kontak'] ?: '085235567818';

        return "Layanan informasi otomatis TPQ{$santriStr}. Silakan ketik pertanyaan terkait 3 informasi berikut (atau ketik *1*, *2*, atau *3*):\n\n"
            ."1. *Absensi*: _\"Gimana absensi {$contohNama} hari ini?\"_\n"
            ."2. *SPP*: _\"Anak saya nunggak berapa bulan?\"_, _\"Cek SPP\"_, atau _\"Bayar ke siapa?\"_ (No. Bendahara: *{$kontakBendahara}*)\n"
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

        $kontakBendahara = $this->getBendaharaInfo()['kontak'] ?: '085235567818';

        return "Mohon maaf, layanan ini hanya menyediakan informasi terkait *Absensi*, *SPP*, dan *Informasi Hari Libur* untuk {$santriName}.\n\n"
            ."Silakan ketik pertanyaan sesuai topik berikut (atau ketik *1*, *2*, atau *3*):\n"
            ."1. *Absensi*: _\"Gimana absensi anak saya?\"_ atau _\"Bulan sebelumnya berapa kali?\"_\n"
            ."2. *SPP*: _\"Anak saya nunggak berapa bulan?\"_, _\"Cek SPP\"_, atau _\"Bayar ke siapa?\"_ (No. Bendahara: *{$kontakBendahara}*)\n"
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
     * Menyimpan riwayat percakapan ke tabel chatbot_logs dan chat_conversations / chat_messages
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
        try {
            $conversationService = app(ConversationService::class);
            if ($conversationService->hasConversationTables()) {
                $convStatus = $intent === 'multi_santri' ? 'awaiting_student_selection' : 'active';
                $conversationService->recordInteractionPair(
                    phoneNumber: $phone,
                    incomingMessage: $message,
                    botReply: $reply,
                    intent: $intent,
                    santriId: $siswaId,
                    status: $convStatus
                );
            }
        } catch (\Throwable) {
        }

        ActivityLog::record(
            modul: 'whatsapp',
            aksi: 'chatbot',
            deskripsi: "Chatbot merespons pesan dari {$phone} (Topik: {$intent})",
            properties: [
                'phone' => $phone,
                'intent' => $intent,
                'id_siswa' => $siswaId,
                'status' => $status,
            ]
        );

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
