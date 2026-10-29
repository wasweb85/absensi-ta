<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityAndIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Kelas $kelas;

    protected Siswa $siswa;

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
            'no_hp_ortu' => '081234567890',
            'status' => 'aktif',
        ]);

        $this->admin = User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@sekolah.id',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
    }

    public function test_student_soft_deletion_preserves_attendance_records(): void
    {
        // 1. Catat absensi untuk siswa
        $absensi = Absensi::create([
            'id_siswa' => $this->siswa->id,
            'tanggal' => Carbon::now()->format('Y-m-d'),
            'waktu_masuk' => '07:10:00',
            'status' => 'Hadir',
            'metode_scan' => 'qr_kamera',
        ]);

        // 2. Hapus santri melalui endpoint API
        $response = $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/siswa/{$this->siswa->id}");
        $response->assertStatus(200);

        // 3. Pastikan santri ter-soft delete
        $this->assertSoftDeleted('siswa', ['id' => $this->siswa->id]);

        // 4. Pastikan rekam jejak absensi TIDAK hilang dari database (audit preserved)
        $this->assertDatabaseHas('absensi', [
            'id' => $absensi->id,
            'id_siswa' => $this->siswa->id,
            'status' => 'Hadir',
        ]);
    }

    public function test_webhook_rejects_unauthorized_token_when_secret_is_configured(): void
    {
        Pengaturan::setValue('fonnte_webhook_secret', 'secret_token_123');

        // Request tanpa token
        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '081234567890',
            'message' => 'Halo',
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);

        // Request dengan token salah
        $responseWrong = $this->withHeader('X-Webhook-Token', 'salah')->postJson('/api/webhook/whatsapp', [
            'sender' => '081234567890',
            'message' => 'Halo',
        ]);

        $responseWrong->assertStatus(401);
    }

    public function test_webhook_allows_valid_secret_token(): void
    {
        Pengaturan::setValue('fonnte_webhook_secret', 'secret_token_123');

        $response = $this->withHeader('X-Webhook-Token', 'secret_token_123')->postJson('/api/webhook/whatsapp', [
            'sender' => '081234567890',
            'message' => 'Halo',
        ]);

        $response->assertStatus(200);
    }

    public function test_guru_is_scoped_to_assigned_class_in_siswa_api(): void
    {
        $kelasLain = Kelas::create([
            'nama_kelas' => 'JILID 2',
            'urutan' => 2,
            'warna_label' => '#2196f3',
        ]);

        $siswaLain = Siswa::create([
            'nisn' => '2001',
            'nama_lengkap' => 'Santri Kelas Lain',
            'jenis_kelamin' => 'L',
            'id_kelas' => $kelasLain->id,
            'status' => 'aktif',
        ]);

        $guru = User::create([
            'name' => 'Ustadz Jilid 1',
            'username' => 'guru1',
            'email' => 'guru1@sekolah.id',
            'password' => Hash::make('password'),
            'role' => 'guru',
            'id_kelas' => $this->kelas->id,
        ]);

        // 1. Guru get /api/siswa dilarang (403 Forbidden) karena guru biasa tidak mengelola data santri
        $response = $this->actingAs($guru, 'sanctum')->getJson('/api/siswa');
        $response->assertStatus(403);

        // 2. Guru mencoba akses santri langsung via ID -> 403 Forbidden
        $responseDetail = $this->actingAs($guru, 'sanctum')->getJson("/api/siswa/{$siswaLain->id}");
        $responseDetail->assertStatus(403);
    }

    public function test_artisan_absensi_close_daily_command_marks_unattended_as_alpa(): void
    {
        $today = Carbon::now('Asia/Jakarta')->format('Y-m-d');
        Pengaturan::setValue('hari_aktif', 'Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu');

        $this->artisan('absensi:close-daily')
            ->assertSuccessful();

        $this->assertDatabaseHas('absensi', [
            'id_siswa' => $this->siswa->id,
            'tanggal' => $today,
            'status' => 'Alpa',
        ]);
    }
}
