<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_graceful_fallback_when_api_key_is_not_configured(): void
    {
        config(['services.gemini.api_key' => '']);

        $service = app(GeminiService::class);

        $this->assertFalse($service->isConfigured());

        $result = $service->generateContent([
            ['role' => 'user', 'parts' => [['text' => 'Halo']]],
        ]);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['fallback']);
        $this->assertNull($result['reply']);
    }

    public function test_connection_succeeds_and_uses_x_goog_api_key_header_without_exposing_key_in_url(): void
    {
        config([
            'services.gemini.api_key' => 'secret-gemini-key-12345',
            'services.gemini.model' => 'gemini-2.5-flash',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'role' => 'model',
                            'parts' => [
                                ['text' => 'TERHUBUNG'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(GeminiService::class);
        $result = $service->testConnection();

        $this->assertTrue($result['success']);
        $this->assertTrue($result['configured']);
        $this->assertSame('gemini-2.5-flash', $result['model']);
        $this->assertSame('TERHUBUNG', $result['reply']);

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('x-goog-api-key', 'secret-gemini-key-12345')
                && ! str_contains($request->url(), 'secret-gemini-key-12345');
        });
    }

    public function test_supports_function_calling_and_returns_natural_reply(): void
    {
        config([
            'services.gemini.api_key' => 'secret-gemini-key-12345',
            'services.gemini.model' => 'gemini-2.5-flash',
        ]);

        Http::fakeSequence()
            ->push([
                'candidates' => [
                    [
                        'content' => [
                            'role' => 'model',
                            'parts' => [
                                [
                                    'functionCall' => [
                                        'name' => 'check_attendance',
                                        'args' => [
                                            'santri_id' => 10,
                                            'period' => 'today',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200)
            ->push([
                'candidates' => [
                    [
                        'content' => [
                            'role' => 'model',
                            'parts' => [
                                [
                                    'text' => 'Alhamdulillah Bapak/Ibu, ananda Ahmad Fauzi sudah hadir hari ini pukul 15:30 WIB.',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200);

        $service = app(GeminiService::class);

        $declarations = [
            [
                'name' => 'check_attendance',
                'description' => 'Memeriksa kehadiran santri',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'santri_id' => ['type' => 'INTEGER'],
                        'period' => ['type' => 'STRING'],
                    ],
                ],
            ],
        ];

        $result = $service->generateContent(
            contents: [
                ['role' => 'user', 'parts' => [['text' => 'Apakah anak saya sudah hadir hari ini?']]],
            ],
            systemInstruction: 'Anda adalah asisten ramah TPQ Nurul Mun\'im.',
            functionDeclarations: $declarations,
            toolExecutor: function (string $name, array $args): array {
                $this->assertSame('check_attendance', $name);
                $this->assertSame(10, $args['santri_id']);

                return [
                    'nama_santri' => 'Ahmad Fauzi',
                    'status_hari_ini' => 'Hadir',
                    'waktu_masuk' => '15:30:00',
                ];
            }
        );

        $this->assertTrue($result['success']);
        $this->assertFalse($result['fallback']);
        $this->assertCount(1, $result['tool_calls']);
        $this->assertSame('check_attendance', $result['tool_calls'][0]['name']);
        $this->assertStringContainsString('Ahmad Fauzi', (string) $result['reply']);
        $this->assertStringContainsString('15:30 WIB', (string) $result['reply']);
    }

    public function test_limits_conversation_history_and_ensures_first_turn_is_user(): void
    {
        config(['services.gemini.max_history_messages' => 4]);

        $service = app(GeminiService::class);

        $history = [
            ['role' => 'user', 'parts' => [['text' => 'Pesan 1']]],
            ['role' => 'model', 'parts' => [['text' => 'Balasan 1']]],
            ['role' => 'user', 'parts' => [['text' => 'Pesan 2']]],
            ['role' => 'model', 'parts' => [['text' => 'Balasan 2']]],
            ['role' => 'user', 'parts' => [['text' => 'Pesan 3']]],
            ['role' => 'model', 'parts' => [['text' => 'Balasan 3']]],
            ['role' => 'user', 'parts' => [['text' => 'Pesan 4']]],
        ];

        $limited = $service->limitConversationHistory($history, 4);

        // Karena 4 elemen terakhir dimulai dari 'Balasan 2' (role: model), maka 'Balasan 2' otomatis di-shift agar dimulai dengan 'user'
        $this->assertCount(3, $limited);
        $this->assertSame('user', $limited[0]['role']);
        $this->assertSame('Pesan 3', $limited[0]['parts'][0]['text']);
    }

    public function test_sanitizes_api_key_from_error_messages_and_handles_http_errors(): void
    {
        config([
            'services.gemini.api_key' => 'AIzaSySecretKey99999',
            'services.gemini.model' => 'gemini-2.5-flash',
        ]);

        Http::fake([
            '*' => Http::response([
                'error' => [
                    'message' => 'Invalid key AIzaSySecretKey99999 provided in request ?key=AIzaSySecretKey99999',
                ],
            ], 403),
        ]);

        $service = app(GeminiService::class);
        $result = $service->generateContent([
            ['role' => 'user', 'parts' => [['text' => 'Halo']]],
        ]);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['fallback']);
        $this->assertStringNotContainsString('AIzaSySecretKey99999', (string) $result['error']);
        $this->assertStringContainsString('[REDACTED_GEMINI_KEY]', (string) $result['error']);
    }

    public function test_admin_can_call_test_gemini_endpoint(): void
    {
        config([
            'services.gemini.api_key' => 'secret-gemini-key-12345',
            'services.gemini.model' => 'gemini-2.5-flash',
        ]);

        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'role' => 'model',
                            'parts' => [
                                ['text' => 'TERHUBUNG'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $admin = User::factory()->create([
            'username' => 'admin_gemini',
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/chatbot/test-gemini');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'success' => true,
                    'configured' => true,
                    'model' => 'gemini-2.5-flash',
                    'reply' => 'TERHUBUNG',
                ],
            ]);
    }
}
