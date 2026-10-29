<?php

namespace Tests\Feature;

use App\Http\Resources\SiswaResource;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Absensi;
use App\Models\Agenda;
use App\Models\HariLibur;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use App\Models\WhatsAppLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PriorityThreeArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_model_factories_create_valid_records(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create();
        $bendahara = User::factory()->bendahara()->create();
        $kepala = User::factory()->kepala()->create();

        $this->assertEquals('admin', $admin->role);
        $this->assertEquals('guru', $guru->role);
        $this->assertEquals('bendahara', $bendahara->role);
        $this->assertEquals('kepala', $kepala->role);

        $kelas = Kelas::factory()->create();
        $this->assertDatabaseHas('kelas', ['id' => $kelas->id]);

        $siswaL = Siswa::factory()->lakiLaki()->create(['id_kelas' => $kelas->id]);
        $this->assertEquals('L', $siswaL->jenis_kelamin);
        $this->assertEquals('aktif', $siswaL->status);

        $siswaP = Siswa::factory()->perempuan()->lulus()->create(['id_kelas' => $kelas->id]);
        $this->assertEquals('P', $siswaP->jenis_kelamin);
        $this->assertEquals('lulus', $siswaP->status);

        $absensi = Absensi::factory()->hadir()->create(['id_siswa' => $siswaL->id]);
        $this->assertEquals('Hadir', $absensi->status);

        $libur = HariLibur::factory()->create();
        $this->assertDatabaseHas('hari_libur', ['id' => $libur->id]);

        $agenda = Agenda::factory()->create();
        $this->assertDatabaseHas('agenda', ['id' => $agenda->id]);

        $waLog = WhatsAppLog::factory()->create([
            'tipe_pesan' => 'presensi',
        ]);
        $this->assertDatabaseHas('whatsapp_logs', ['id' => $waLog->id]);
    }

    public function test_whatsapp_queue_job_dispatches_properly(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();

        // 1. Kirim async via /api/wa/send
        $response = $this->actingAs($admin)->postJson('/api/wa/send', [
            'phone' => '081234567890',
            'message' => 'Halo dari queue',
            'async' => true,
        ]);

        $response->assertStatus(202)
            ->assertJson(['success' => true, 'status' => 'queued']);

        Queue::assertPushed(SendWhatsAppMessageJob::class, function ($job) {
            return $job->phone === '081234567890' && $job->message === 'Halo dari queue';
        });

        // 2. Broadcast via /api/wa/broadcast
        $broadcastResponse = $this->actingAs($admin)->postJson('/api/wa/broadcast', [
            'phones' => ['08111111111', '08222222222', '08333333333'],
            'message' => 'Pesan pengumuman penting',
        ]);

        $broadcastResponse->assertOk()
            ->assertJson([
                'success' => true,
                'queued_count' => 3,
            ]);

        Queue::assertPushed(SendWhatsAppMessageJob::class, 4);
    }

    public function test_form_requests_validate_siswa(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = Kelas::factory()->create();

        // Test StoreSiswaRequest validation
        $invalidSiswa = $this->actingAs($admin)->postJson('/api/siswa', [
            'nisn' => '', // kosong
            'nama_lengkap' => '',
        ]);
        $invalidSiswa->assertStatus(422)
            ->assertJsonValidationErrors(['nisn', 'nama_lengkap', 'jenis_kelamin', 'id_kelas']);

        // Test valid StoreSiswaRequest
        $validSiswa = $this->actingAs($admin)->postJson('/api/siswa', [
            'nisn' => '9988776655',
            'nama_lengkap' => 'Santri Baru Teladan',
            'jenis_kelamin' => 'L',
            'id_kelas' => $kelas->id,
            'status' => 'aktif',
        ]);
        $validSiswa->assertStatus(201);
        $newSiswaId = $validSiswa->json('data.id');

        // Test UpdateSiswaRequest unique validation allows same NISN for current student
        $updateSelf = $this->actingAs($admin)->putJson("/api/siswa/{$newSiswaId}", [
            'nisn' => '9988776655',
            'nama_lengkap' => 'Santri Baru Teladan Diubah',
            'jenis_kelamin' => 'L',
            'id_kelas' => $kelas->id,
            'status' => 'aktif',
        ]);
        $updateSelf->assertOk();
    }

    public function test_api_resources_transform_models_correctly(): void
    {
        $kelas = Kelas::factory()->create(['nama_kelas' => 'Jilid 5']);
        $siswa = Siswa::factory()->create(['id_kelas' => $kelas->id, 'nama_lengkap' => 'Muhammad Ali']);

        $siswa->load('kelas');
        $resource = (new SiswaResource($siswa))->response()->getData(true);
        $this->assertEquals('Muhammad Ali', $resource['data']['nama_lengkap']);
        $this->assertEquals('Jilid 5', $resource['data']['kelas']['nama_kelas']);
    }
}
