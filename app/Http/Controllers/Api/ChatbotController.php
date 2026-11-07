<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatbotLog;
use App\Models\Pengaturan;
use App\Models\WhatsAppLog;
use App\Services\ChatbotService;
use App\Services\GeminiService;
use App\Services\KnowledgeBaseService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class ChatbotController extends Controller
{
    public function __construct(
        protected ChatbotService $chatbotService,
        protected WhatsAppService $whatsAppService
    ) {}

    /**
     * Endpoint Webhook yang dipanggil oleh Fonnte saat ada pesan masuk
     */
    public function webhook(Request $request): JsonResponse
    {
        // Tangani request verifikasi GET dari Fonnte
        if ($request->isMethod('get')) {
            return response()->json([
                'status' => true,
                'message' => 'Fonnte webhook endpoint is active.',
            ]);
        }

        // Verifikasi token rahasia webhook jika dikonfigurasi
        $secret = Pengaturan::getValue('fonnte_webhook_secret') ?: env('FONNTE_WEBHOOK_SECRET');
        if (! empty($secret)) {
            $token = $request->header('X-Webhook-Token')
                ?: $request->header('Authorization')
                ?: $request->input('token')
                ?: $request->query('token');

            if ($token && str_starts_with((string) $token, 'Bearer ')) {
                $token = substr((string) $token, 7);
            }

            if (! hash_equals((string) $secret, (string) $token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Invalid webhook secret token.',
                ], 401);
            }
        }

        // 1. Deteksi Callback Status Pesan (Delivery Status: sent, delivered, read, failed)
        $statusResponse = $this->processMessageStatusUpdate($request);
        if ($statusResponse !== null) {
            return $statusResponse;
        }

        // 2. Fonnte mengirimkan pesan masuk chatbot: sender & message
        $payload = $this->extractWebhookPayload($request);
        $sender = $payload['sender'] ?? $payload['from'] ?? $request->input('sender') ?: $request->input('from');
        $message = $payload['message'] ?? $payload['body'] ?? $request->input('message') ?: $request->input('body');

        // Jika request kosong (Fonnte ping verifikasi webhook saat disimpan)
        if (empty($sender) && empty($message)) {
            return response()->json([
                'status' => true,
                'success' => true,
                'message' => 'Fonnte webhook endpoint is active and ready (ping acknowledged).',
            ], 200);
        }

        if (! $sender) {
            return response()->json([
                'success' => false,
                'message' => 'Sender is required',
            ], 400);
        }

        // 3. Abaikan pesan yang berasal dari Grup WhatsApp agar bot hanya merespons chat pribadi wali santri
        if ($this->isGroupMessage($payload, (string) $sender)) {
            return response()->json([
                'success' => true,
                'ignored' => true,
                'reason' => 'group_message',
                'message' => 'Pesan grup diabaikan oleh chatbot.',
            ], 200);
        }

        // 4. Cegah pemrosesan pesan masuk dua kali (Idempotency / Deduplication) jika Fonnte me-retry webhook
        $incomingMsgId = $this->extractIncomingMessageId($payload);
        if ($incomingMsgId !== null) {
            $dedupKey = "fonnte_inbox_dedup:{$incomingMsgId}";
            if (Cache::has($dedupKey)) {
                return response()->json([
                    'success' => true,
                    'duplicate' => true,
                    'inbox_id' => $incomingMsgId,
                    'message' => 'Pesan webhook duplikat diabaikan (sudah diproses).',
                ], 200);
            }
            Cache::put($dedupKey, true, now()->addMinutes(10));
        }

        // Jika penerima membalas chat ke bot, otomatis pesan sebelumnya ke nomor tersebut pasti sudah dibaca
        $senderNorm = WhatsAppService::normalizePhone((string) $sender);
        if (! empty($senderNorm)) {
            WhatsAppLog::where('nomor_tujuan', $senderNorm)
                ->whereIn('status', ['sent', 'delivered'])
                ->get()
                ->each(fn (WhatsAppLog $log) => $log->markAsRead());
        }

        $targetPhone = $senderNorm !== '' ? $senderNorm : (string) $sender;

        // 5. Rate Limiting per nomor WhatsApp pengirim (Maksimal 25 pesan / menit per nomor)
        $rateKey = "chatbot_sender:{$targetPhone}";
        if (RateLimiter::tooManyAttempts($rateKey, 25)) {
            return response()->json([
                'success' => false,
                'rate_limited' => true,
                'message' => 'Terlalu banyak permintaan dari nomor ini. Silakan tunggu beberapa saat.',
            ], 429);
        }
        RateLimiter::hit($rateKey, 60);

        $result = $this->chatbotService->handleIncomingMessage($targetPhone, (string) $message);

        // Kirim balasan otomatis via Fonnte Send API ke pengirim
        $sendResult = null;
        if (! empty($targetPhone) && ! empty($result['reply'])) {
            $namaPenerima = ! empty($result['siswa']['nama_lengkap'])
                ? 'Wali dari '.$result['siswa']['nama_lengkap']
                : 'Pengirim Chatbot';

            $sendResult = $this->whatsAppService->sendViaFonnte(
                $targetPhone,
                $result['reply'],
                null,
                [
                    'tipe_pesan' => 'chatbot_reply',
                    'nama_penerima' => $namaPenerima,
                ]
            );

            // Jika pengiriman ke gateway Fonnte gagal karena penolakan gateway
            if (! ($sendResult['success'] ?? false) && isset($sendResult['response'])) {
                if (! empty($result['log_id'])) {
                    $safeError = WhatsAppService::sanitizeSensitiveText(
                        (string) ($sendResult['message'] ?? 'Gateway Fonnte gagal mengirim pesan.')
                    );
                    ChatbotLog::where('id', $result['log_id'])->update([
                        'status' => 'gagal',
                        'keterangan_error' => $safeError,
                    ]);
                }
            }
        }

        return response()->json([
            'reply' => $result['reply'],
            'success' => true,
            'intent' => $result['intent'],
            'gateway_send' => $sendResult,
        ]);
    }

    /**
     * Endpoint Simulator interaktif untuk dicoba di Web Admin (Alat demo sidang skripsi)
     */
    public function simulate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $result = $this->chatbotService->handleIncomingMessage($validated['phone'], $validated['message']);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Endpoint pengujian koneksi Google Gemini API (Khusus Admin)
     */
    public function testGeminiConnection(GeminiService $geminiService): JsonResponse
    {
        $result = $geminiService->testConnection();

        return response()->json([
            'success' => $result['success'],
            'data' => $result,
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Daftar seluruh entri Knowledge Base TPQ
     */
    public function knowledgeBaseIndex(KnowledgeBaseService $knowledgeBaseService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $knowledgeBaseService->getAll(false),
        ]);
    }

    /**
     * Tambah entri Knowledge Base TPQ baru
     */
    public function knowledgeBaseStore(Request $request, KnowledgeBaseService $knowledgeBaseService): JsonResponse
    {
        $validated = $request->validate([
            'kategori' => 'required|string|max:50',
            'judul' => 'required|string|max:150',
            'kata_kunci' => 'nullable|string|max:255',
            'konten' => 'required|string',
            'is_active' => 'sometimes|boolean',
            'urutan' => 'sometimes|integer',
        ]);

        $entry = $knowledgeBaseService->store($validated);

        return response()->json([
            'success' => true,
            'message' => 'Informasi Knowledge Base TPQ berhasil ditambahkan.',
            'data' => $entry,
        ], 201);
    }

    /**
     * Perbarui entri Knowledge Base TPQ
     */
    public function knowledgeBaseUpdate(Request $request, int $id, KnowledgeBaseService $knowledgeBaseService): JsonResponse
    {
        $validated = $request->validate([
            'kategori' => 'sometimes|required|string|max:50',
            'judul' => 'sometimes|required|string|max:150',
            'kata_kunci' => 'nullable|string|max:255',
            'konten' => 'sometimes|required|string',
            'is_active' => 'sometimes|boolean',
            'urutan' => 'sometimes|integer',
        ]);

        $entry = $knowledgeBaseService->update($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Informasi Knowledge Base TPQ berhasil diperbarui.',
            'data' => $entry,
        ]);
    }

    /**
     * Hapus entri Knowledge Base TPQ
     */
    public function knowledgeBaseDestroy(int $id, KnowledgeBaseService $knowledgeBaseService): JsonResponse
    {
        $knowledgeBaseService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Informasi Knowledge Base TPQ berhasil dihapus.',
        ]);
    }

    /**
     * Mengambil daftar riwayat interaksi chatbot (audit trail)
     */
    public function logs(Request $request): JsonResponse
    {
        $query = ChatbotLog::with(['siswa.kelas'])->orderBy('id', 'desc');

        if ($request->filled('intent')) {
            $query->where('intent_terdeteksi', $request->input('intent'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('created_at', $request->input('tanggal'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('no_pengirim', 'like', "%{$search}%")
                    ->orWhere('pesan_masuk', 'like', "%{$search}%")
                    ->orWhere('pesan_keluar', 'like', "%{$search}%")
                    ->orWhereHas('siswa', function ($sq) use ($search) {
                        $sq->where('nama_lengkap', 'like', "%{$search}%")
                            ->orWhere('nisn', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Ringkasan statistik performa chatbot (Single Aggregate Query)
     */
    public function stats(): JsonResponse
    {
        $todayStart = Carbon::today()->startOfDay()->toDateTimeString();
        $monthStart = Carbon::now()->startOfMonth()->toDateTimeString();

        $agg = ChatbotLog::query()
            ->selectRaw("
                COUNT(*) as total_all,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as total_today,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as total_month,
                SUM(CASE WHEN status = 'sukses' THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN intent_terdeteksi = 'absensi' THEN 1 ELSE 0 END) as absensi_count,
                SUM(CASE WHEN intent_terdeteksi = 'spp_notice' THEN 1 ELSE 0 END) as spp_count,
                SUM(CASE WHEN intent_terdeteksi = 'agenda' THEN 1 ELSE 0 END) as agenda_count,
                SUM(CASE WHEN intent_terdeteksi = 'info_tpq' THEN 1 ELSE 0 END) as info_tpq_count,
                SUM(CASE WHEN intent_terdeteksi = 'izin_sakit' THEN 1 ELSE 0 END) as izin_sakit_count,
                SUM(CASE WHEN intent_terdeteksi = 'bantuan' THEN 1 ELSE 0 END) as bantuan_count,
                SUM(CASE WHEN intent_terdeteksi = 'unregistered' THEN 1 ELSE 0 END) as unregistered_count
            ", [$todayStart, $monthStart])
            ->first();

        $totalCount = (int) ($agg->total_all ?? 0);
        $successCount = (int) ($agg->success_count ?? 0);
        $successRate = $totalCount > 0 ? round(($successCount / $totalCount) * 100, 1) : 100;

        return response()->json([
            'success' => true,
            'data' => [
                'total_today' => (int) ($agg->total_today ?? 0),
                'total_month' => (int) ($agg->total_month ?? 0),
                'total_all' => $totalCount,
                'success_rate' => $successRate,
                'intent_distribution' => [
                    'absensi' => (int) ($agg->absensi_count ?? 0),
                    'spp_notice' => (int) ($agg->spp_count ?? 0),
                    'agenda' => (int) ($agg->agenda_count ?? 0),
                    'info_tpq' => (int) ($agg->info_tpq_count ?? 0),
                    'izin_sakit' => (int) ($agg->izin_sakit_count ?? 0),
                    'bantuan' => (int) ($agg->bantuan_count ?? 0),
                    'unregistered' => (int) ($agg->unregistered_count ?? 0),
                ],
            ],
        ]);
    }

    /**
     * Endpoint khusus Webhook Message Status dari Fonnte (Kolom: Webhook Message Status ?)
     */
    public function messageStatusWebhook(Request $request): JsonResponse
    {
        if ($request->isMethod('get')) {
            return response()->json([
                'status' => true,
                'message' => 'Fonnte message status webhook endpoint is active and ready.',
            ]);
        }

        $statusResponse = $this->processMessageStatusUpdate($request);
        if ($statusResponse !== null) {
            return $statusResponse;
        }

        return response()->json([
            'success' => true,
            'message' => 'Message status event acknowledged.',
        ]);
    }

    /**
     * Endpoint khusus Webhook Connect dari Fonnte (Kolom: Webhook Connect ?)
     */
    public function connectWebhook(Request $request): JsonResponse
    {
        if ($request->isMethod('get')) {
            return response()->json([
                'status' => true,
                'message' => 'Fonnte connect webhook endpoint is active.',
            ]);
        }

        $data = $this->extractWebhookPayload($request);

        $device = (string) ($data['device'] ?? $request->input('device') ?? '');
        $status = strtolower(trim((string) ($data['status'] ?? $request->input('status') ?? '')));

        Log::info('Fonnte Webhook Connect Event', [
            'device' => $device,
            'status' => $status,
            'payload' => $this->sanitizePayload($data),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status koneksi perangkat berhasil diterima.',
            'device' => $device,
            'status' => $status,
        ]);
    }

    /**
     * Periksa apakah pesan berasal dari Grup WhatsApp (@g.us atau flag isgroup).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function isGroupMessage(array $payload, string $sender): bool
    {
        if (str_contains(strtolower($sender), '@g.us')) {
            return true;
        }

        $isGroup = $payload['isgroup'] ?? $payload['is_group'] ?? null;
        if ($isGroup === true || $isGroup === 1 || $isGroup === '1' || strtolower((string) $isGroup) === 'true') {
            return true;
        }

        return false;
    }

    /**
     * Ekstrak ID unik pesan masuk (inboxid / message_id) untuk mencegah duplikasi pemrosesan.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractIncomingMessageId(array $payload): ?string
    {
        $rawId = $payload['inboxid'] ?? $payload['inbox_id'] ?? $payload['message_id'] ?? $payload['id'] ?? null;
        if ($rawId === null || $rawId === '') {
            return null;
        }

        $idStr = trim((string) (is_array($rawId) ? ($rawId[0] ?? '') : $rawId));

        return $idStr !== '' ? $idStr : null;
    }

    /**
     * Sanitasi array payload webhook agar token rahasia tidak pernah tercatat di file log.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizePayload(array $payload): array
    {
        $sanitized = $payload;
        foreach (['token', 'authorization', 'secret', 'api_key', 'apikey'] as $sensitiveKey) {
            foreach ($sanitized as $k => $v) {
                if (strtolower((string) $k) === $sensitiveKey) {
                    $sanitized[$k] = '[REDACTED]';
                }
            }
        }

        return $sanitized;
    }

    /**
     * Ekstrak payload JSON maupun form-urlencoded dari request webhook Fonnte.
     * Fonnte mengirimkan JSON mentah di php://input yang sering kali bertipe Content-Type form-urlencoded.
     */
    protected function extractWebhookPayload(Request $request): array
    {
        $data = [];
        $rawContent = $request->getContent();
        if (! empty($rawContent)) {
            $decoded = json_decode($rawContent, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        $all = $request->all();
        if (is_array($all) && ! empty($all)) {
            foreach ($all as $k => $v) {
                if (is_string($k) && str_starts_with(trim($k), '{') && empty($data)) {
                    $keyJson = json_decode($k, true);
                    if (is_array($keyJson)) {
                        $data = array_merge($keyJson, $data);
                    }
                }
            }
            $data = array_merge($all, $data);
        }

        return $data;
    }

    /**
     * Ambil peta relasi stateid WhatsApp -> log_id dari tabel pengaturan
     *
     * @return array<string, int>
     */
    protected function getStateIdMap(): array
    {
        try {
            $raw = Pengaturan::getValue('fonnte_stateid_map');
            if (! empty($raw)) {
                $decoded = json_decode((string) $raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (\Throwable) {
        }

        return [];
    }

    /**
     * Simpan relasi stateid WhatsApp -> log_id ke Cache dan tabel pengaturan
     */
    protected function saveStateIdMapping(string $stateId, int $logId): void
    {
        if ($stateId === '' || in_array($stateId, ['0', '1', '2', '3'], true)) {
            return;
        }

        try {
            Cache::put("fonnte_stateid:{$stateId}", $logId, now()->addDays(7));
            $map = $this->getStateIdMap();
            $map[$stateId] = $logId;
            if (count($map) > 250) {
                $map = array_slice($map, -250, null, true);
            }
            Pengaturan::setValue('fonnte_stateid_map', json_encode($map));
        } catch (\Throwable) {
        }
    }

    /**
     * Catat jejak debug payload webhook terakhir untuk diagnostik
     */
    protected function recordWebhookDebug(array $entry): void
    {
        try {
            $raw = Pengaturan::getValue('fonnte_webhook_debug');
            $list = ! empty($raw) ? (json_decode((string) $raw, true) ?: []) : [];
            if (! is_array($list)) {
                $list = [];
            }
            array_unshift($list, $entry);
            $list = array_slice($list, 0, 15);
            Pengaturan::setValue('fonnte_webhook_debug', json_encode($list));
        } catch (\Throwable) {
        }
    }

    /**
     * Logika internal pemrosesan status pesan Fonnte (Delivery Log).
     * Mendukung 3 skema resmi Fonnte:
     * 1) id + stateid (saat pesan terkirim ke WA dan mendapat stateid)
     * 2) id saja (status antrean/gagal awal)
     * 3) stateid + state saja tanpa id (saat WA mengirim receipt delivered / read)
     */
    protected function processMessageStatusUpdate(Request $request): ?JsonResponse
    {
        $data = $this->extractWebhookPayload($request);

        // Jika ini adalah pesan masuk chatbot murni (ada sender & message tanpa state/stateid), lewati
        if (! empty($data['sender']) && isset($data['message']) && empty($data['state']) && empty($data['stateid'])) {
            return null;
        }

        $rawId = $data['id'] ?? $data['message_id'] ?? $request->input('id');
        $msgId = ! empty($rawId) ? trim((string) (is_array($rawId) ? ($rawId[0] ?? '') : $rawId)) : '';

        $rawStateId = $data['stateid'] ?? $data['state_id'] ?? $request->input('stateid');
        $stateId = ($rawStateId !== null && $rawStateId !== '') ? trim((string) (is_array($rawStateId) ? ($rawStateId[0] ?? '') : $rawStateId)) : '';

        if ($msgId === '' && $stateId === '') {
            return null;
        }

        $stateVal = strtolower(trim((string) ($data['state'] ?? $request->input('state') ?? '')));
        $statusVal = strtolower(trim((string) ($data['status'] ?? $request->input('status') ?? '')));
        $msgStatusVal = strtolower(trim((string) ($data['message_status'] ?? $request->input('message_status') ?? '')));

        if ($stateVal === '' && $statusVal === '' && $msgStatusVal === '' && $stateId === '') {
            return null;
        }

        Log::info('Fonnte Webhook Message Status update received', [
            'id' => $msgId,
            'state' => $stateVal,
            'status' => $statusVal,
            'message_status' => $msgStatusVal,
            'stateid' => $stateId,
            'payload' => $this->sanitizePayload($data),
        ]);

        $stateIdMap = $this->getStateIdMap();
        $log = null;

        // 1. Cari berdasarkan Fonnte ID (id) jika tersedia
        if ($msgId !== '') {
            $log = WhatsAppLog::where('fonnte_id', $msgId)
                ->orWhere('id', is_numeric($msgId) ? (int) $msgId : -1)
                ->first();
        }

        // 2. Cari berdasarkan WhatsApp State ID (stateid) jika id kosong atau belum ketemu
        if (! $log && $stateId !== '' && ! in_array($stateId, ['0', '1', '2', '3'], true)) {
            $cachedLogId = Cache::get("fonnte_stateid:{$stateId}") ?? ($stateIdMap[$stateId] ?? null);
            if ($cachedLogId) {
                $log = WhatsAppLog::find((int) $cachedLogId);
            }
            if (! $log) {
                $log = WhatsAppLog::where('fonnte_id', $stateId)->first();
            }
        }

        // 3. Fallback pencarian berdasarkan nomor tujuan (target) jika ada
        if (! $log) {
            $target = $data['target'] ?? $data['recipient'] ?? $data['to'] ?? $request->input('target') ?? null;
            if ($target) {
                $targetNorm = WhatsAppService::normalizePhone((string) $target);
                if (! empty($targetNorm)) {
                    $log = WhatsAppLog::where('nomor_tujuan', $targetNorm)
                        ->where('status', '!=', 'read')
                        ->latest('id')
                        ->first();
                    if ($log && empty($log->fonnte_id) && $msgId !== '') {
                        $log->update(['fonnte_id' => $msgId]);
                    }
                }
            }
        }

        // 4. Fallback ketika Fonnte mengirim webhook delivered/read yang hanya berisi stateid (tanpa id & tanpa target)
        // untuk pesan yang baru saja dikirim namun belum sempat terpetakan stateid-nya
        if (! $log && $msgId === '' && $stateId !== '') {
            $mappedLogIds = array_values($stateIdMap);
            $query = WhatsAppLog::whereIn('status', ['pending', 'sent', 'delivered'])
                ->where('created_at', '>=', Carbon::now('Asia/Jakarta')->subHours(24));

            if (! empty($mappedLogIds)) {
                $query->whereNotIn('id', $mappedLogIds);
            }

            $log = $query->latest('id')->first();

            if (! $log) {
                $log = WhatsAppLog::whereIn('status', ['pending', 'sent', 'delivered'])
                    ->where('created_at', '>=', Carbon::now('Asia/Jakarta')->subHours(6))
                    ->latest('id')
                    ->first();
            }
        }

        $matchedAction = 'none';
        if ($log) {
            // Simpan relasi stateid -> log_id agar callback berikutnya (delivered -> read) selalu tepat sasaran
            if ($stateId !== '') {
                $this->saveStateIdMapping($stateId, $log->id);
            }

            $isRead = $stateVal === 'read' ||
                $statusVal === 'read' ||
                $msgStatusVal === 'read' ||
                str_contains($stateVal, 'read') ||
                str_contains($statusVal, 'read') ||
                $stateVal === '3' ||
                $stateId === '3';

            $isDelivered = $stateVal === 'delivered' ||
                $statusVal === 'delivered' ||
                $msgStatusVal === 'delivered' ||
                str_contains($stateVal, 'deliver') ||
                str_contains($statusVal, 'deliver') ||
                $stateVal === '2' ||
                $stateId === '2';

            $isFailed = $stateVal === '0' ||
                $stateId === '0' ||
                in_array($stateVal, ['failed', 'invalid', 'expired', 'error', 'rejected'], true) ||
                in_array($statusVal, ['failed', 'invalid', 'expired', 'error', 'rejected'], true) ||
                in_array($msgStatusVal, ['failed', 'invalid', 'expired', 'error', 'rejected'], true);

            $isSent = $stateVal === 'sent' ||
                $statusVal === 'sent' ||
                $stateVal === 'success' ||
                $statusVal === 'processing' ||
                str_contains($statusVal, 'sent') ||
                $stateVal === '1' ||
                $stateId === '1';

            if ($isRead) {
                $log->markAsRead();
                $matchedAction = 'read';
            } elseif ($isDelivered) {
                // Cegah penurunan jika status sudah read
                if ($log->status !== 'read') {
                    $log->markAsDelivered();
                }
                $matchedAction = 'delivered';
            } elseif ($isFailed) {
                $reason = $data['reason'] ?? $data['detail'] ?? ($stateVal === '0' ? 'Pesan tidak terkirim (Fonnte state: 0 / limit WhatsApp)' : "Fonnte status: {$stateVal}/{$statusVal}");
                $log->markAsFailed((string) $reason);
                $matchedAction = 'failed';
            } elseif ($isSent) {
                // Cegah penurunan jika status sudah delivered atau read
                if (! in_array($log->status, ['delivered', 'read'], true)) {
                    $log->markAsSent($msgId !== '' ? $msgId : null);
                }
                $matchedAction = 'sent';
            }

            Log::info("Fonnte Webhook Status: WhatsAppLog #{$log->id} updated to '{$log->status}' via action '{$matchedAction}'");
        } else {
            Log::warning("Fonnte Webhook Status: WhatsAppLog not found for ID '{$msgId}' / stateid '{$stateId}'", [
                'payload' => $this->sanitizePayload($data),
            ]);
        }

        $this->recordWebhookDebug([
            'time' => Carbon::now('Asia/Jakarta')->toIso8601String(),
            'id' => $msgId,
            'stateid' => $stateId,
            'state' => $stateVal,
            'status' => $statusVal,
            'matched_log_id' => $log?->id,
            'action' => $matchedAction,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status pesan berhasil diperbarui.',
            'matched' => (bool) $log,
            'fonnte_id' => $msgId !== '' ? $msgId : ($log?->fonnte_id ?? $stateId),
            'state' => $stateVal ?: $statusVal,
            'action' => $matchedAction,
            'current_status' => $log?->status,
        ]);
    }
}
