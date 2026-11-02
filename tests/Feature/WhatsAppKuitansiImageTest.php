<?php

namespace Tests\Feature;

use App\Models\Pengaturan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppKuitansiImageTest extends TestCase
{
    use RefreshDatabase;

    protected User $bendahara;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bendahara = User::create([
            'name' => 'Bendahara TPQ',
            'username' => 'bendahara_test',
            'email' => 'bendahara@test.local',
            'password' => bcrypt('password'),
            'role' => 'bendahara',
        ]);

        Pengaturan::setValue('fonnte_token', 'mock_token_kuitansi');
    }

    public function test_send_kuitansi_image_via_multipart_file(): void
    {
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'id' => ['kw_msg_001'],
                'target' => ['628123456789'],
            ], 200),
        ]);

        Sanctum::actingAs($this->bendahara);

        $file = UploadedFile::fake()->image('kuitansi.png', 400, 600);

        $response = $this->postJson('/api/wa/send-kuitansi-image', [
            'phone' => '08123456789',
            'message' => 'Bukti Pembayaran SPP Resmi',
            'nomor_kuitansi' => 'KW-202611-0001',
            'nama_penerima' => 'Khowas',
            'image' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'fonnte_id' => 'kw_msg_001',
        ]);

        $this->assertDatabaseHas('whatsapp_logs', [
            'nomor_tujuan' => '628123456789',
            'nama_penerima' => 'Khowas',
            'tipe_pesan' => 'kuitansi_spp',
            'status' => 'sent',
        ]);
    }

    public function test_send_kuitansi_image_via_base64(): void
    {
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'id' => ['kw_msg_002'],
                'target' => ['628123456789'],
            ], 200),
        ]);

        Sanctum::actingAs($this->bendahara);

        $dummyPngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response = $this->postJson('/api/wa/send-kuitansi-image', [
            'phone' => '08123456789',
            'message' => '',
            'nomor_kuitansi' => 'KW-202611-0002',
            'nama_penerima' => 'Khowas',
            'image_base64' => $dummyPngBase64,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'fonnte_id' => 'kw_msg_002',
        ]);
    }

    public function test_send_kuitansi_image_without_message(): void
    {
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'id' => ['kw_msg_003'],
                'target' => ['628123456789'],
            ], 200),
        ]);

        Sanctum::actingAs($this->bendahara);

        $dummyPngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response = $this->postJson('/api/wa/send-kuitansi-image', [
            'phone' => '08123456789',
            'nomor_kuitansi' => 'KW-202611-0003',
            'nama_penerima' => 'Khowas',
            'image_base64' => $dummyPngBase64,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'fonnte_id' => 'kw_msg_003',
        ]);
    }
}
