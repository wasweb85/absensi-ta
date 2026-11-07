<?php

namespace Tests\Feature;

use App\Models\ChatbotLog;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\WhatsAppLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FonnteWebhookIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Kelas $kelas;

    protected Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        Pengaturan::setValue('fonnte_token', 'secret-fonnte-token-xyz987');

        $this->kelas = Kelas::create([
            'nama_kelas' => 'JILID 3',
            'urutan' => 3,
        ]);

        $this->siswa = Siswa::create([
            'nisn' => '3001',
            'nama_lengkap' => 'Nabila Zahra',
            'jenis_kelamin' => 'P',
            'id_kelas' => $this->kelas->id,
            'nama_ortu' => 'Ibu Nabila',
            'no_hp_ortu' => '6281299998888',
        ]);
    }

    public function test_webhook_processes_incoming_message_and_records_conversation_and_logs(): void
    {
        Http::fake([
            'api.fonnte.com/send' => Http::response([
                'status' => true,
                'id' => ['fonnte-msg-101'],
            ], 200),
        ]);

        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '0812-9999-8888',
            'message' => 'Gimana absensi anak saya hari ini?',
            'inboxid' => 'inbox-unique-001',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('intent', 'absensi')
            ->assertJsonPath('gateway_send.success', true);

        // Pastikan tercatat di chat_conversations & chat_messages
        $conversation = ChatConversation::where('phone_number', '6281299998888')->first();
        $this->assertNotNull($conversation);
        $this->assertSame($this->siswa->id, $conversation->santri_id);

        $messages = ChatMessage::where('conversation_id', $conversation->id)->orderBy('id')->get();
        $this->assertCount(2, $messages);
        $this->assertSame('wali', $messages[0]->sender_type);
        $this->assertSame('Gimana absensi anak saya hari ini?', $messages[0]->message);
        $this->assertSame('bot', $messages[1]->sender_type);

        // Pastikan tercatat di chatbot_logs & whatsapp_logs
        $this->assertDatabaseHas('chatbot_logs', [
            'no_pengirim' => '6281299998888',
            'id_siswa' => $this->siswa->id,
            'intent_terdeteksi' => 'absensi',
            'status' => 'sukses',
        ]);

        $this->assertDatabaseHas('whatsapp_logs', [
            'nomor_tujuan' => '6281299998888',
            'tipe_pesan' => 'chatbot_reply',
            'status' => 'sent',
            'fonnte_id' => 'fonnte-msg-101',
        ]);
    }

    public function test_webhook_prevents_duplicate_message_processing_by_inboxid(): void
    {
        Http::fake([
            'api.fonnte.com/send' => Http::response([
                'status' => true,
                'id' => ['fonnte-msg-202'],
            ], 200),
        ]);

        $payload = [
            'sender' => '081299998888',
            'message' => 'Cek SPP anak saya',
            'inboxid' => 'inbox-dup-777',
        ];

        $first = $this->postJson('/api/webhook/whatsapp', $payload);
        $first->assertOk()->assertJsonPath('success', true);

        $second = $this->postJson('/api/webhook/whatsapp', $payload);
        $second->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('inbox_id', 'inbox-dup-777');

        // Hanya 1 kali pengiriman ke Fonnte dan 1 entri di ChatbotLog
        Http::assertSentCount(1);
        $this->assertSame(1, ChatbotLog::count());
        $this->assertSame(2, ChatMessage::count());
    }

    public function test_webhook_ignores_whatsapp_group_messages(): void
    {
        Http::fake();

        $res1 = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '12036302999999@g.us',
            'message' => 'Halo semua',
        ]);

        $res1->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('ignored', true)
            ->assertJsonPath('reason', 'group_message');

        $res2 = $this->postJson('/api/webhook/fonnte', [
            'sender' => '6281299998888',
            'message' => 'Halo grup',
            'isgroup' => true,
        ]);

        $res2->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('ignored', true);

        Http::assertNothingSent();
        $this->assertSame(0, ChatbotLog::count());
    }

    public function test_webhook_handles_fonnte_failure_and_redacts_token(): void
    {
        Http::fake([
            'api.fonnte.com/send' => Http::response([
                'status' => false,
                'reason' => 'Error authenticating token secret-fonnte-token-xyz987 on gateway',
            ], 200),
        ]);

        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281299998888',
            'message' => 'Absensi hari ini',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('gateway_send.success', false);

        $chatLog = ChatbotLog::latest('id')->first();
        $this->assertNotNull($chatLog);
        $this->assertSame('gagal', $chatLog->status);
        $this->assertStringNotContainsString('secret-fonnte-token-xyz987', (string) $chatLog->keterangan_error);
        $this->assertStringContainsString('[REDACTED_FONNTE_TOKEN]', (string) $chatLog->keterangan_error);

        $waLog = WhatsAppLog::latest('id')->first();
        $this->assertNotNull($waLog);
        $this->assertSame('failed', $waLog->status);
        $this->assertStringNotContainsString('secret-fonnte-token-xyz987', (string) $waLog->keterangan_error);
        $this->assertStringContainsString('[REDACTED_FONNTE_TOKEN]', (string) $waLog->keterangan_error);
    }

    public function test_webhook_enforces_secret_token_when_configured(): void
    {
        Pengaturan::setValue('fonnte_webhook_secret', 'my-webhook-secret-2026');

        $unauthorized = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281299998888',
            'message' => 'Halo',
        ]);
        $unauthorized->assertStatus(401);

        Http::fake([
            'api.fonnte.com/send' => Http::response(['status' => true, 'id' => ['msg-1']], 200),
        ]);

        $authorized = $this->withHeaders([
            'X-Webhook-Token' => 'my-webhook-secret-2026',
        ])->postJson('/api/webhook/whatsapp', [
            'sender' => '6281299998888',
            'message' => 'Halo',
        ]);
        $authorized->assertOk()->assertJsonPath('success', true);
    }
}
