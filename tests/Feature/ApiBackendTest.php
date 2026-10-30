<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiBackendTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Siswa $siswa;

    protected Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kelas = Kelas::create([
            'nama_kelas' => 'JILID 1',
            'urutan' => 1,
            'warna_label' => '#f44336',
        ]);

        $this->siswa = Siswa::create([
            'nisn' => '1001',
            'nama_lengkap' => 'Ahmad Fauzi',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
            'status' => 'aktif',
            'no_hp_ortu' => '081234567890',
        ]);

        $this->admin = User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@sekolah.id',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
        ]);

        Pengaturan::setValue('jam_masuk', '07:00');
        Pengaturan::setValue('jam_terlambat', '07:15');
        Pengaturan::setValue('jam_pulang', '12:00');
        Pengaturan::setValue('tahun_ajaran_aktif', '2026/2027');
    }

    public function test_login_successful_with_admin(): void
    {
        $response = $this->postJson('/api/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'username' => 'admin',
                    'role' => 'admin',
                ],
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'username' => 'admin',
            'password' => 'password_salah',
        ]);

        $response->assertStatus(422);
    }

    public function test_absensi_config_endpoint(): void
    {
        $response = $this->getJson('/api/absensi/config');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'jam_masuk' => '07:00',
                'jam_terlambat' => '07:15',
                'jam_pulang' => '12:00',
            ]);
    }

    public function test_absensi_scan_and_monitoring(): void
    {
        // 1. Scan masuk siswa 1001
        $scanResponse = $this->postJson('/api/absensi/scan', [
            'nisn' => '1001',
            'scanner_role' => 'admin',
        ]);

        $scanResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'nisn' => '1001',
                    'nama_lengkap' => 'Ahmad Fauzi',
                ],
            ]);

        $this->assertDatabaseHas('absensi', [
            'id_siswa' => $this->siswa->id,
        ]);

        // 2. Monitoring kehadiran
        $monResponse = $this->getJson('/api/absensi/monitoring');
        $monResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tanggal',
                    'stats' => ['total', 'hadir', 'terlambat', 'belum_absen'],
                    'students',
                ],
            ]);
    }

    public function test_wa_preview_presensi_and_custom(): void
    {
        // 1. Preview notifikasi presensi
        $presensiRes = $this->actingAs($this->admin, 'sanctum')->getJson('/api/wa/preview?type=presensi&id_siswa='.$this->siswa->id);
        $presensiRes->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'wa_me_link']);

        // 2. Preview kustom
        $customRes = $this->actingAs($this->admin, 'sanctum')->getJson('/api/wa/preview?message=Pengumuman&phone=081234567890');
        $customRes->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'wa_me_link']);
    }

    public function test_settings_get_and_update(): void
    {
        $getRes = $this->actingAs($this->admin, 'sanctum')->getJson('/api/pengaturan');
        $getRes->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);

        $postRes = $this->actingAs($this->admin, 'sanctum')->postJson('/api/pengaturan', [
            'settings' => [
                'nama_instansi' => 'TPQ Nurul Mun\'im Baru',
            ],
        ]);

        $postRes->assertStatus(200)
            ->assertJsonPath('data.nama_instansi', 'TPQ Nurul Mun\'im Baru');
    }

    public function test_kelas_crud(): void
    {
        // Create
        $createRes = $this->actingAs($this->admin, 'sanctum')->postJson('/api/kelas', [
            'nama_kelas' => 'JILID 2',
            'wali_kelas' => 'Ustadzah Aisyah',
            'urutan' => 2,
        ]);
        $createRes->assertStatus(201);
        $newId = $createRes->json('data.id');

        // Update
        $updateRes = $this->actingAs($this->admin, 'sanctum')->putJson('/api/kelas/'.$newId, [
            'nama_kelas' => 'JILID 2A',
            'urutan' => 2,
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.nama_kelas', 'JILID 2A');

        // Delete
        $deleteRes = $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/kelas/'.$newId);
        $deleteRes->assertStatus(200);
    }
}
