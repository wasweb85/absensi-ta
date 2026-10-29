<?php

namespace App\Jobs;

use App\Models\WhatsAppLog;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public int $timeout = 30;

    public function __construct(
        public string $phone,
        public string $message,
        public ?string $customToken = null,
        public ?int $logId = null,
        public array $metadata = []
    ) {}

    public function handle(WhatsAppService $waService): void
    {
        $meta = $this->metadata;
        if ($this->logId) {
            $meta['log_id'] = $this->logId;
        }

        $result = $waService->sendViaFonnte($this->phone, $this->message, $this->customToken, $meta);

        if (! ($result['success'] ?? false)) {
            Log::warning('Gagal mengirim pesan WhatsApp via queue job', [
                'phone' => $this->phone,
                'result' => $result,
                'attempt' => $this->attempts(),
            ]);

            // Release back to queue with delay if not last attempt
            if ($this->attempts() < $this->tries) {
                $delay = $this->backoff[$this->attempts() - 1] ?? 60;
                $this->release($delay);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Kekal total mengirim pesan WhatsApp setelah retry habis', [
            'phone' => $this->phone,
            'exception' => $exception?->getMessage(),
        ]);

        if ($this->logId) {
            WhatsAppLog::where('id', $this->logId)->update([
                'status' => 'failed',
                'keterangan_error' => 'Gagal total setelah retry habis: '.($exception?->getMessage() ?? 'Queue timeout/failure'),
            ]);
        }
    }
}
