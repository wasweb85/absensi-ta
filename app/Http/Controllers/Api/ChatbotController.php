<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatbotLog;
use App\Models\Pengaturan;
use App\Models\WhatsAppLog;
use App\Services\ChatbotService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        if ($request->filled('id') && $request->filled('state')) {
            $msgId = (string) $request->input('id');
            $state = strtolower(trim((string) $request->input('state')));
            $log = WhatsAppLog::where('fonnte_id', $msgId)->first();

            if ($log) {
                if ($state === 'read') {
                    $log->markAsRead();
                } elseif ($state === 'delivered') {
                    $log->markAsDelivered();
                } elseif ($state === 'sent') {
                    $log->markAsSent($msgId);
                } elseif (in_array($state, ['failed', 'invalid', 'expired', 'error'], true)) {
                    $log->markAsFailed("Fonnte status: {$state}");
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Status pesan berhasil diperbarui.',
                'matched' => (bool) $log,
                'state' => $state,
            ]);
        }

        // 2. Fonnte mengirimkan pesan masuk chatbot: sender & message
        $sender = $request->input('sender') ?: $request->input('from');
        $message = $request->input('message') ?: $request->input('body') ?: '';

        if (! $sender) {
            return response()->json([
                'success' => false,
                'message' => 'Sender is required',
            ], 400);
        }

        $result = $this->chatbotService->handleIncomingMessage((string) $sender, (string) $message);

        // Kirim balasan otomatis via Fonnte Send API ke pengirim
        $sendResult = null;
        if (! empty($sender) && ! empty($result['reply'])) {
            $sendResult = $this->whatsAppService->sendViaFonnte(
                (string) $sender,
                $result['reply'],
                null,
                [
                    'tipe_pesan' => 'chatbot_reply',
                    'nama_penerima' => 'Pengirim Chatbot',
                ]
            );

            // Jika pengiriman ke gateway Fonnte gagal karena penolakan gateway
            if (! ($sendResult['success'] ?? false) && isset($sendResult['response'])) {
                if (! empty($result['log_id'])) {
                    ChatbotLog::where('id', $result['log_id'])->update([
                        'status' => 'gagal',
                        'keterangan_error' => $sendResult['message'] ?? 'Gateway Fonnte gagal mengirim pesan.',
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
                SUM(CASE WHEN intent_terdeteksi = 'agenda' THEN 1 ELSE 0 END) as agenda_count,
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
                    'agenda' => (int) ($agg->agenda_count ?? 0),
                    'bantuan' => (int) ($agg->bantuan_count ?? 0),
                    'unregistered' => (int) ($agg->unregistered_count ?? 0),
                ],
            ],
        ]);
    }
}
