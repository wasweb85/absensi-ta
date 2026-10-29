<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\WhatsAppLog;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function __construct(
        protected WhatsAppService $waService
    ) {}

    /**
     * Preview pesan WhatsApp dan buat link wa.me
     */
    public function preview(Request $request): JsonResponse
    {
        $type = $request->input('type', 'custom');

        if ($type === 'presensi') {
            $idSiswa = (int) $request->input('id_siswa');
            $siswa = Siswa::with('kelas')->findOrFail($idSiswa);
            $status = (string) $request->input('status', 'Hadir');
            $waktu = (string) $request->input('waktu', Carbon::now('Asia/Jakarta')->format('H:i'));
            $tanggal = (string) $request->input('tanggal', Carbon::now('Asia/Jakarta')->format('d-m-Y'));

            $message = $this->waService->buildPesanPresensi($siswa->nama_lengkap, $status, $waktu, $tanggal);
            $waMeLink = $siswa->no_hp_ortu ? $this->waService->generateWaMeLink($siswa->no_hp_ortu, $message) : null;

            return response()->json([
                'success' => true,
                'target_phone' => $siswa->no_hp_ortu,
                'message' => $message,
                'wa_me_link' => $waMeLink,
            ]);
        }

        if ($type === 'pengumuman') {
            $judul = (string) $request->input('judul', 'Pengumuman Kegiatan TPQ');
            $isi = (string) $request->input('isi', '');
            $message = $this->waService->buildPesanPengumuman($judul, $isi);

            $phone = (string) $request->input('phone', '');
            $waMeLink = $phone ? $this->waService->generateWaMeLink($phone, $message) : null;

            return response()->json([
                'success' => true,
                'target_phone' => $phone,
                'message' => $message,
                'wa_me_link' => $waMeLink,
            ]);
        }

        // Default: Preview pesan kustom
        $phone = (string) $request->input('phone', '');
        $message = (string) $request->input('message', '');
        $waMeLink = $phone ? $this->waService->generateWaMeLink($phone, $message) : null;

        return response()->json([
            'success' => true,
            'target_phone' => $phone,
            'message' => $message,
            'wa_me_link' => $waMeLink,
        ]);
    }

    /**
     * Kirim pesan via Fonnte Gateway API (dapat dikirim langsung atau via antrean background)
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
            'async' => 'nullable|boolean',
            'nama_penerima' => 'nullable|string|max:100',
            'tipe_pesan' => 'nullable|string|max:50',
        ]);

        $phone = (string) $request->input('phone');
        $message = (string) $request->input('message');
        $metadata = [
            'nama_penerima' => $request->input('nama_penerima'),
            'tipe_pesan' => $request->input('tipe_pesan', 'manual'),
        ];

        if ($request->boolean('async')) {
            SendWhatsAppMessageJob::dispatch($phone, $message, null, null, $metadata);

            return response()->json([
                'success' => true,
                'message' => 'Pesan berhasil dimasukkan ke dalam antrean background.',
                'status' => 'queued',
            ], 202);
        }

        $result = $this->waService->sendViaFonnte($phone, $message, null, $metadata);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Kirim pesan broadcast ke banyak nomor via queue job
     */
    public function broadcast(Request $request): JsonResponse
    {
        $request->validate([
            'phones' => 'required|array|min:1',
            'phones.*' => 'required|string',
            'message' => 'required|string',
            'tipe_pesan' => 'nullable|string|max:50',
            'nama_penerima_map' => 'nullable|array',
        ]);

        $phones = (array) $request->input('phones');
        $message = (string) $request->input('message');
        $tipePesan = (string) $request->input('tipe_pesan', 'broadcast');
        $namaMap = (array) $request->input('nama_penerima_map', []);
        $count = 0;

        foreach ($phones as $targetPhone) {
            $trimmed = trim((string) $targetPhone);
            if (! empty($trimmed)) {
                $meta = [
                    'nama_penerima' => $namaMap[$trimmed] ?? null,
                    'tipe_pesan' => $tipePesan,
                ];
                SendWhatsAppMessageJob::dispatch($trimmed, $message, null, null, $meta);
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'queued_count' => $count,
            'message' => "Sebanyak {$count} pesan berhasil dimasukkan ke antrean pengiriman background.",
        ]);
    }

    /**
     * Cek token Fonnte
     */
    public function testKoneksi(Request $request): JsonResponse
    {
        $token = $request->input('token') ?: Pengaturan::getValue('fonnte_token');

        if (empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Token Fonnte kosong.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token terkonfigurasi. Silakan uji coba kirim ke nomor ponsel.',
        ]);
    }

    /**
     * Daftar log pengiriman WhatsApp (Delivery Log) dengan filter dan summary agregat
     */
    public function logs(Request $request): JsonResponse
    {
        $summary = WhatsAppLog::query()
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
                COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered,
                COUNT(CASE WHEN status = 'read' THEN 1 END) as `read`,
                COUNT(CASE WHEN status IN ('failed', 'pending') THEN 1 END) as failed
            ")
            ->first();

        $query = WhatsAppLog::query()->latest();

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $status = $request->input('status');
            if ($status === 'failed') {
                $query->whereIn('status', ['failed', 'pending']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('tipe_pesan') && $request->input('tipe_pesan') !== 'all') {
            $query->where('tipe_pesan', $request->input('tipe_pesan'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'summary' => [
                'total' => (int) ($summary->total ?? 0),
                'sent' => (int) ($summary->sent ?? 0),
                'delivered' => (int) ($summary->delivered ?? 0),
                'read' => (int) ($summary->read ?? 0),
                'failed' => (int) ($summary->failed ?? 0),
            ],
        ]);
    }

    /**
     * Kirim ulang satu pesan spesifik
     */
    public function retry(int $id): JsonResponse
    {
        $log = WhatsAppLog::findOrFail($id);

        $result = $this->waService->sendViaFonnte(
            $log->nomor_tujuan,
            $log->pesan,
            null,
            [
                'log_id' => $log->id,
                'nama_penerima' => $log->nama_penerima,
                'tipe_pesan' => $log->tipe_pesan,
            ]
        );

        return response()->json([
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? 'Proses kirim ulang selesai.',
            'log' => $log->fresh(),
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * Kirim ulang massal semua pesan yang berstatus failed / pending
     */
    public function retryFailed(): JsonResponse
    {
        $failedLogs = WhatsAppLog::whereIn('status', ['failed', 'pending'])->get();
        $count = 0;

        foreach ($failedLogs as $log) {
            SendWhatsAppMessageJob::dispatch(
                $log->nomor_tujuan,
                $log->pesan,
                null,
                $log->id,
                [
                    'nama_penerima' => $log->nama_penerima,
                    'tipe_pesan' => $log->tipe_pesan,
                ]
            );
            $count++;
        }

        return response()->json([
            'success' => true,
            'retried_count' => $count,
            'message' => "Sebanyak {$count} pesan berhasil dimasukkan ke dalam antrean pengiriman ulang.",
        ]);
    }

    /**
     * Hapus satu baris log pengiriman
     */
    public function deleteLog(int $id): JsonResponse
    {
        $log = WhatsAppLog::findOrFail($id);
        $log->delete();

        return response()->json([
            'success' => true,
            'message' => 'Catatan log berhasil dihapus.',
        ]);
    }
}
