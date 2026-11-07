<?php

namespace App\Services;

use App\Models\Pengaturan;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    /**
     * Ambil API Key Gemini dari konfigurasi environment atau database pengaturan.
     */
    protected function getApiKey(): string
    {
        $envKey = trim((string) config('services.gemini.api_key', ''));
        if ($envKey !== '') {
            return $envKey;
        }

        try {
            $dbKey = trim((string) Pengaturan::getValue('gemini_api_key', ''));
            if ($dbKey !== '') {
                return $dbKey;
            }
        } catch (\Throwable) {
            // Abaikan jika tabel pengaturan belum dimigrasi saat unit test awal
        }

        return '';
    }

    /**
     * Periksa apakah Gemini API Key sudah dikonfigurasi.
     */
    public function isConfigured(): bool
    {
        return $this->getApiKey() !== '';
    }

    /**
     * Ambil nama model Gemini yang digunakan.
     */
    public function getModel(): string
    {
        $model = trim((string) config('services.gemini.model', 'gemini-2.5-flash'));

        if ($model === '') {
            return 'gemini-2.5-flash';
        }

        return $model;
    }

    /**
     * Ambil base URL endpoint Google Gemini API.
     */
    protected function getBaseUrl(): string
    {
        $url = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');

        if ($url === '') {
            return 'https://generativelanguage.googleapis.com/v1beta';
        }

        return $url;
    }

    /**
     * Ambil batas waktu HTTP request (detik).
     */
    protected function getTimeout(): int
    {
        $timeout = (int) config('services.gemini.timeout', 10);

        return $timeout > 0 ? $timeout : 10;
    }

    /**
     * Ambil batas waktu koneksi HTTP (detik).
     */
    protected function getConnectTimeout(): int
    {
        $connectTimeout = (int) config('services.gemini.connect_timeout', 4);

        return $connectTimeout > 0 ? $connectTimeout : 4;
    }

    /**
     * Ambil batas maksimal pesan riwayat percakapan yang dikirim sebagai konteks.
     */
    public function getMaxHistoryMessages(): int
    {
        $max = (int) config('services.gemini.max_history_messages', 8);

        return $max > 0 ? $max : 8;
    }

    /**
     * Sensor API Key dari teks pesan atau log agar tidak pernah bocor ke log sistem.
     */
    public function sanitizeSensitiveText(string $text): string
    {
        $apiKey = $this->getApiKey();
        if ($apiKey !== '' && strlen($apiKey) >= 4) {
            $text = str_replace($apiKey, '[REDACTED_GEMINI_KEY]', $text);
        }

        $clean = preg_replace('/([?&]key=)[^&\s]+/i', '$1[REDACTED]', $text);

        return is_string($clean) ? $clean : $text;
    }

    /**
     * Batasi jumlah riwayat percakapan yang dikirim ke Gemini agar efisien dan hemat token.
     *
     * @param  array<int, array{role: string, parts: array<int, array<string, mixed>>}>  $contents
     * @return array<int, array{role: string, parts: array<int, array<string, mixed>>}>
     */
    public function limitConversationHistory(array $contents, ?int $maxMessages = null): array
    {
        $limit = $maxMessages ?? $this->getMaxHistoryMessages();
        if (count($contents) <= $limit) {
            return array_values($contents);
        }

        $sliced = array_slice($contents, -$limit);

        // Pastikan giliran pertama pada konteks Gemini dimulai oleh 'user'
        while (! empty($sliced) && ($sliced[0]['role'] ?? '') !== 'user') {
            array_shift($sliced);
        }

        return array_values($sliced);
    }

    /**
     * Uji koneksi ke Google Gemini API dengan prompt minimal.
     *
     * @return array{
     *     success: bool,
     *     configured: bool,
     *     model: string,
     *     reply: string|null,
     *     error: string|null,
     *     latency_ms: int
     * }
     */
    public function testConnection(): array
    {
        $startTime = microtime(true);
        $model = $this->getModel();

        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'configured' => false,
                'model' => $model,
                'reply' => null,
                'error' => 'GEMINI_API_KEY belum dikonfigurasi.',
                'latency_ms' => 0,
            ];
        }

        $result = $this->generateContent([
            [
                'role' => 'user',
                'parts' => [
                    ['text' => 'Balas dengan satu kata: TERHUBUNG'],
                ],
            ],
        ]);

        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'success' => $result['success'],
            'configured' => true,
            'model' => $model,
            'reply' => $result['reply'],
            'error' => $result['error'],
            'latency_ms' => $latencyMs,
        ];
    }

    /**
     * Kirim percakapan ke Gemini API dengan dukungan System Instruction dan Function Calling.
     *
     * @param  array<int, array{role: string, parts: array<int, array<string, mixed>>}>  $contents
     * @param  array<int, array<string, mixed>>  $functionDeclarations
     * @return array{
     *     success: bool,
     *     fallback: bool,
     *     reply: string|null,
     *     tool_calls: array<int, array{name: string, args: array<string, mixed>}>,
     *     error: string|null
     * }
     */
    public function generateContent(
        array $contents,
        ?string $systemInstruction = null,
        array $functionDeclarations = [],
        ?callable $toolExecutor = null,
        int $maxToolIterations = 2
    ): array {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'fallback' => true,
                'reply' => null,
                'tool_calls' => [],
                'error' => 'GEMINI_API_KEY belum dikonfigurasi.',
            ];
        }

        $workingContents = $this->limitConversationHistory($contents);
        $executedTools = [];
        $iteration = 0;

        while ($iteration <= $maxToolIterations) {
            $iteration++;

            $payload = [
                'contents' => $workingContents,
                'generationConfig' => [
                    'temperature' => (float) config('services.gemini.temperature', 0.3),
                    'maxOutputTokens' => 700,
                ],
            ];

            if ($systemInstruction !== null && trim($systemInstruction) !== '') {
                $payload['system_instruction'] = [
                    'parts' => [
                        ['text' => trim($systemInstruction)],
                    ],
                ];
            }

            if (! empty($functionDeclarations)) {
                $payload['tools'] = [
                    [
                        'functionDeclarations' => array_values($functionDeclarations),
                    ],
                ];
            }

            $response = $this->sendGenerateRequest($payload);
            if (! $response['success']) {
                return [
                    'success' => false,
                    'fallback' => true,
                    'reply' => null,
                    'tool_calls' => $executedTools,
                    'error' => $response['error'],
                ];
            }

            $candidateContent = $response['data']['candidates'][0]['content'] ?? null;
            $parts = $candidateContent['parts'] ?? [];

            if (! is_array($parts) || empty($parts)) {
                return [
                    'success' => false,
                    'fallback' => true,
                    'reply' => null,
                    'tool_calls' => $executedTools,
                    'error' => 'Respons Gemini tidak memiliki kandidat konten teks.',
                ];
            }

            // Periksa apakah Gemini meminta pemanggilan fungsi (functionCall)
            $functionCalls = [];
            $textParts = [];

            foreach ($parts as $part) {
                if (isset($part['functionCall']) && is_array($part['functionCall'])) {
                    $functionCalls[] = $part['functionCall'];
                }
                if (isset($part['text']) && is_string($part['text']) && trim($part['text']) !== '') {
                    $textParts[] = trim($part['text']);
                }
            }

            // Jika tidak ada functionCall, kembalikan jawaban teks akhir
            if (empty($functionCalls) || $toolExecutor === null || $iteration > $maxToolIterations) {
                $finalReply = trim(implode("\n\n", $textParts));
                if ($finalReply === '') {
                    return [
                        'success' => false,
                        'fallback' => true,
                        'reply' => null,
                        'tool_calls' => $executedTools,
                        'error' => 'Gemini tidak mengembalikan teks jawaban akhir.',
                    ];
                }

                return [
                    'success' => true,
                    'fallback' => false,
                    'reply' => $finalReply,
                    'tool_calls' => $executedTools,
                    'error' => null,
                ];
            }

            // Tambahkan giliran model (berisi functionCall) ke riwayat workingContents
            $workingContents[] = [
                'role' => 'model',
                'parts' => $parts,
            ];

            // Eksekusi setiap fungsi di sisi Laravel (tanpa akses SQL langsung dari AI)
            $functionResponseParts = [];
            foreach ($functionCalls as $fc) {
                $funcName = (string) ($fc['name'] ?? '');
                $funcArgs = is_array($fc['args'] ?? null) ? $fc['args'] : [];

                if ($funcName === '') {
                    continue;
                }

                $executedTools[] = [
                    'name' => $funcName,
                    'args' => $funcArgs,
                ];

                try {
                    $toolResult = $toolExecutor($funcName, $funcArgs);
                    if (! is_array($toolResult)) {
                        $toolResult = ['result' => $toolResult];
                    }
                } catch (\Throwable $e) {
                    $safeErr = $this->sanitizeSensitiveText($e->getMessage());
                    Log::warning('Eksekusi tool chatbot gagal', [
                        'tool' => $funcName,
                        'error' => $safeErr,
                    ]);
                    $toolResult = [
                        'error' => 'Gagal mengambil data internal.',
                    ];
                }

                $functionResponseParts[] = [
                    'functionResponse' => [
                        'name' => $funcName,
                        'response' => $toolResult,
                    ],
                ];
            }

            $workingContents[] = [
                'role' => 'user',
                'parts' => $functionResponseParts,
            ];
        }

        return [
            'success' => false,
            'fallback' => true,
            'reply' => null,
            'tool_calls' => $executedTools,
            'error' => 'Melewati batas iterasi pemanggilan fungsi Gemini.',
        ];
    }

    /**
     * Kirim HTTP POST request ke endpoint resmi Google Gemini API menggunakan header x-goog-api-key.
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, data: array<string, mixed>|null, error: string|null}
     */
    protected function sendGenerateRequest(array $payload): array
    {
        $apiKey = $this->getApiKey();
        $model = $this->getModel();
        $endpoint = "{$this->getBaseUrl()}/models/{$model}:generateContent";

        try {
            $response = Http::connectTimeout($this->getConnectTimeout())
                ->timeout($this->getTimeout())
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $json = $response->json();

                return [
                    'success' => is_array($json),
                    'data' => is_array($json) ? $json : null,
                    'error' => is_array($json) ? null : 'Format JSON Gemini tidak valid.',
                ];
            }

            $status = $response->status();
            $rawError = (string) ($response->json('error.message') ?? "HTTP Error {$status}");
            $safeError = $this->sanitizeSensitiveText($rawError);

            Log::warning('Gemini API mengembalikan status non-200', [
                'status' => $status,
                'model' => $model,
                'message' => $safeError,
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => $safeError,
            ];
        } catch (ConnectionException $e) {
            $safeError = $this->sanitizeSensitiveText($e->getMessage());
            Log::warning('Koneksi ke Gemini API mengalami timeout / kegagalan jaringan', [
                'model' => $model,
                'error' => $safeError,
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => 'Koneksi ke layanan AI melampaui batas waktu tunggu (timeout).',
            ];
        } catch (\Throwable $e) {
            $safeError = $this->sanitizeSensitiveText($e->getMessage());
            Log::error('Kesalahan tak terduga saat menghubungi Gemini API', [
                'model' => $model,
                'error' => $safeError,
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => 'Terjadi gangguan pada layanan AI.',
            ];
        }
    }
}
