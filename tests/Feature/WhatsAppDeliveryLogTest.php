<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Pengaturan;
use App\Models\User;
use App\Models\WhatsAppLog;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppDeliveryLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'name' => 'Admin Test',
            'username' => 'admin_test',
            'email' => 'admin@test.local',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        Pengaturan::setValue('fonnte_token', 'mock_test_token_123');
    }

    public function test_whatsapp_service_creates_log_when_sending(): void
    {
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'id' => ['msg_123456'],
                'target' => ['6281234567890'],
            ], 200),
        ]);

        $service = app(WhatsAppService::class);
        $result = $service->sendViaFonnte(
            '081234567890',
            'Halo, ini pesan uji coba.',
            null,
            [
                'nama_penerima' => 'Ahmad Fauzi',
                'tipe_pesan' => 'presensi',
            ]
        );

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('whatsapp_logs', [
            'fonnte_id' => 'msg_123456',
            'nomor_tujuan' => '6281234567890',
            'nama_penerima' => 'Ahmad Fauzi',
            'tipe_pesan' => 'presensi',
            'status' => 'sent',
        ]);
    }

    public function test_webhook_updates_status_to_delivered(): void
    {
        $log = WhatsAppLog::factory()->sent()->create([
            'fonnte_id' => 'msg_status_test_01',
            'nomor_tujuan' => '6281234567890',
        ]);

        $response = $this->postJson('/api/webhook/whatsapp', [
            'id' => 'msg_status_test_01',
            'state' => 'delivered',
            'stateid' => '1234',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'state' => 'delivered',
            'matched' => true,
        ]);

        $log->refresh();
        $this->assertEquals('delivered', $log->status);
        $this->assertNotNull($log->delivered_at);
    }

    public function test_webhook_updates_status_to_read(): void
    {
        $log = WhatsAppLog::factory()->delivered()->create([
            'fonnte_id' => 'msg_status_test_02',
            'nomor_tujuan' => '6281234567890',
        ]);

        $response = $this->postJson('/api/webhook/whatsapp', [
            'id' => 'msg_status_test_02',
            'state' => 'read',
            'stateid' => '5678',
        ]);

        $response->assertStatus(200);

        $log->refresh();
        $this->assertEquals('read', $log->status);
        $this->assertNotNull($log->read_at);
    }

    public function test_webhook_updates_status_to_failed(): void
    {
        $log = WhatsAppLog::factory()->sent()->create([
            'fonnte_id' => 'msg_status_test_03',
        ]);

        $response = $this->postJson('/api/webhook/whatsapp', [
            'id' => 'msg_status_test_03',
            'state' => 'failed',
        ]);

        $response->assertStatus(200);

        $log->refresh();
        $this->assertEquals('failed', $log->status);
    }

    public function test_api_returns_paginated_logs_with_summary_counts(): void
    {
        Sanctum::actingAs($this->adminUser);

        WhatsAppLog::factory()->count(2)->sent()->create();
        WhatsAppLog::factory()->count(3)->delivered()->create();
        WhatsAppLog::factory()->count(4)->read()->create();
        WhatsAppLog::factory()->count(1)->failed()->create();

        $response = $this->getJson('/api/wa/logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
                'summary' => ['total', 'sent', 'delivered', 'read', 'failed'],
            ]);

        $summary = $response->json('summary');
        $this->assertEquals(10, $summary['total']);
        $this->assertEquals(2, $summary['sent']);
        $this->assertEquals(3, $summary['delivered']);
        $this->assertEquals(4, $summary['read']);
        $this->assertEquals(1, $summary['failed']);
    }

    public function test_api_filters_logs_by_status(): void
    {
        Sanctum::actingAs($this->adminUser);

        WhatsAppLog::factory()->create(['status' => 'sent']);
        WhatsAppLog::factory()->create(['status' => 'failed']);

        $response = $this->getJson('/api/wa/logs?status=failed');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('failed', $data[0]['status']);
    }

    public function test_api_retries_single_message(): void
    {
        Sanctum::actingAs($this->adminUser);

        $log = WhatsAppLog::factory()->failed()->create([
            'nomor_tujuan' => '6281234567890',
            'pesan' => 'Notifikasi presensi santri belum tersampaikan',
            'retry_count' => 0,
        ]);

        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'id' => ['msg_retried_999'],
            ], 200),
        ]);

        $response = $this->postJson("/api/wa/logs/{$log->id}/retry");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $log->refresh();
        $this->assertEquals('sent', $log->status);
        $this->assertEquals('msg_retried_999', $log->fonnte_id);
        $this->assertEquals(1, $log->retry_count);
        $this->assertNotNull($log->last_retried_at);
    }

    public function test_api_bulk_retries_failed_messages(): void
    {
        Sanctum::actingAs($this->adminUser);
        Queue::fake();

        WhatsAppLog::factory()->count(3)->failed()->create();
        WhatsAppLog::factory()->count(2)->sent()->create(); // bukan failed

        $response = $this->postJson('/api/wa/logs/retry-failed');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'retried_count' => 3,
            ]);

        Queue::assertPushed(SendWhatsAppMessageJob::class, 3);
    }

    public function test_api_deletes_log(): void
    {
        Sanctum::actingAs($this->adminUser);

        $log = WhatsAppLog::factory()->create();

        $response = $this->deleteJson("/api/wa/logs/{$log->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('whatsapp_logs', ['id' => $log->id]);
    }

    public function test_dedicated_status_webhook_endpoint(): void
    {
        $log = WhatsAppLog::factory()->sent()->create([
            'fonnte_id' => 'msg_dedicated_status_01',
        ]);

        // Uji GET request untuk verifikasi Fonnte
        $getRes = $this->getJson('/api/webhook/whatsapp/status');
        $getRes->assertStatus(200);

        // Uji POST callback dengan parameter status
        $postRes = $this->postJson('/api/webhook/whatsapp/status', [
            'id' => 'msg_dedicated_status_01',
            'status' => 'delivered',
            'stateid' => '99',
        ]);

        $postRes->assertStatus(200);
        $postRes->assertJson(['success' => true]);

        $log->refresh();
        $this->assertEquals('delivered', $log->status);
    }

    public function test_webhook_status_works_without_api_prefix_and_bypasses_csrf(): void
    {
        $log = WhatsAppLog::factory()->sent()->create([
            'fonnte_id' => 'msg_no_api_prefix_01',
        ]);

        $res = $this->postJson('/webhook/whatsapp/status', [
            'id' => 'msg_no_api_prefix_01',
            'state' => 'read',
        ]);

        $res->assertStatus(200);
        $res->assertJson(['success' => true]);

        $log->refresh();
        $this->assertEquals('read', $log->status);
    }

    public function test_webhook_handles_numeric_states_delivered_and_read(): void
    {
        $log1 = WhatsAppLog::factory()->sent()->create([
            'fonnte_id' => 'msg_numeric_state_01',
        ]);

        // Fonnte mengirim state: "2" atau stateid: "2" untuk Delivered
        $resDelivered = $this->postJson('/api/webhook/whatsapp/status', [
            'id' => 'msg_numeric_state_01',
            'status' => 'sent',
            'state' => '2',
            'stateid' => '2',
        ]);

        $resDelivered->assertStatus(200);
        $log1->refresh();
        $this->assertEquals('delivered', $log1->status);

        // Fonnte mengirim state: "3" atau stateid: "3" untuk Read
        $resRead = $this->postJson('/api/webhook/whatsapp/status', [
            'id' => 'msg_numeric_state_01',
            'status' => 'sent',
            'state' => '3',
            'stateid' => '3',
        ]);

        $resRead->assertStatus(200);
        $log1->refresh();
        $this->assertEquals('read', $log1->status);

        // Callback terlambat state: "2" tidak boleh menurunkan status dari "read" ke "delivered"
        $resLate = $this->postJson('/api/webhook/whatsapp/status', [
            'id' => 'msg_numeric_state_01',
            'status' => 'sent',
            'state' => '2',
            'stateid' => '2',
        ]);

        $resLate->assertStatus(200);
        $log1->refresh();
        $this->assertEquals('read', $log1->status);
    }

    public function test_api_sync_status_updates_logs_from_fonnte(): void
    {
        Sanctum::actingAs($this->adminUser);

        $log = WhatsAppLog::factory()->sent()->create([
            'fonnte_id' => 'msg_sync_test_888',
        ]);

        Http::fake([
            'https://api.fonnte.com/status' => Http::response([
                'status' => true,
                'id' => 'msg_sync_test_888',
                'message_status' => 'delivered',
            ], 200),
        ]);

        $res = $this->postJson('/api/wa/logs/sync-status');

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'synced_count' => 1,
            ]);

        $log->refresh();
        $this->assertEquals('delivered', $log->status);
    }

    public function test_api_sync_single_status(): void
    {
        Sanctum::actingAs($this->adminUser);

        $log = WhatsAppLog::factory()->sent()->create([
            'fonnte_id' => 'msg_single_sync_999',
        ]);

        Http::fake([
            'https://api.fonnte.com/status' => Http::response([
                'status' => true,
                'id' => 'msg_single_sync_999',
                'message_status' => 'read',
            ], 200),
        ]);

        $res = $this->postJson("/api/wa/logs/{$log->id}/sync-status");

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'read',
                ],
            ]);

        $log->refresh();
        $this->assertEquals('read', $log->status);
    }
}
