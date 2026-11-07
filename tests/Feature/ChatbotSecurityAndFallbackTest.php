<?php

namespace Tests\Feature;

use App\Models\ChatbotLog;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use App\Services\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatbotSecurityAndFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected Kelas $kelas;

    protected Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kelas = Kelas::create([
            'nama_kelas' => 'JILID 5',
            'urutan' => 5,
        ]);

        $this->siswa = Siswa::create([
            'nisn' => '5010',
            'nama_lengkap' => 'Muhammad Ilham',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
            'nama_ortu' => 'Bapak Ilham',
            'no_hp_ortu' => '6281912345678',
        ]);
    }

    public function test_per_sender_rate_limiting_blocks_excessive_messages(): void
    {
        $rateKey = 'chatbot_sender:6281912345678';
        RateLimiter::clear($rateKey);

        for ($i = 0; $i < 25; $i++) {
            RateLimiter::hit($rateKey, 60);
        }

        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '0819-1234-5678',
            'message' => 'Cek absensi hari ini',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('rate_limited', true);

        RateLimiter::clear($rateKey);
    }

    public function test_sanitize_tool_arguments_strips_malicious_input(): void
    {
        $chatbot = app(ChatbotService::class);

        $dirtyArgs = [
            'santri_id' => '99; DROP TABLE siswa;',
            'nama_santri' => "<script>alert('xss')</script>Ilham\x00\x1F",
            'periode' => str_repeat('A', 300),
            'unexpected_sql' => 'SELECT * FROM users',
        ];

        $clean = $chatbot->sanitizeToolArguments($dirtyArgs);

        $this->assertSame(99, $clean['santri_id']);
        $this->assertSame("alert('xss')Ilham", $clean['nama_santri']);
        $this->assertSame(120, mb_strlen($clean['periode']));
        $this->assertArrayNotHasKey('unexpected_sql', $clean);
    }

    public function test_overly_long_incoming_message_is_truncated_safely(): void
    {
        $chatbot = app(ChatbotService::class);
        $longMessage = 'Cek absensi hari ini '.str_repeat('x', 1000);

        $result = $chatbot->handleIncomingMessage('081912345678', $longMessage);

        $this->assertTrue($result['success']);
        $log = ChatbotLog::latest('id')->first();
        $this->assertNotNull($log);
        $this->assertLessThanOrEqual(500, mb_strlen((string) $log->pesan_masuk));
    }

    public function test_chatbot_stats_endpoint_includes_all_hybrid_intents(): void
    {
        $admin = User::create([
            'name' => 'Admin Stats',
            'username' => 'admin_stats',
            'email' => 'admin_stats@tpq.test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        ChatbotLog::create([
            'no_pengirim' => '6281912345678',
            'pesan_masuk' => 'Cek SPP',
            'intent_terdeteksi' => 'spp_notice',
            'id_siswa' => $this->siswa->id,
            'pesan_keluar' => 'Status SPP Lunas',
            'status' => 'sukses',
        ]);

        ChatbotLog::create([
            'no_pengirim' => '6281912345678',
            'pesan_masuk' => 'Jam masuk TPQ',
            'intent_terdeteksi' => 'info_tpq',
            'id_siswa' => $this->siswa->id,
            'pesan_keluar' => 'Jam Masuk 15:30',
            'status' => 'sukses',
        ]);

        Sanctum::actingAs($admin);

        $res = $this->getJson('/api/chatbot/stats');
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent_distribution.spp_notice', 1)
            ->assertJsonPath('data.intent_distribution.info_tpq', 1);
    }
}
