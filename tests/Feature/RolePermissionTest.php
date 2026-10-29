<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $kepala;

    protected User $sekretaris;

    protected User $bendahara;

    protected User $guru;

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
            'status' => 'aktif',
        ]);

        $this->admin = User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@sekolah.id',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->kepala = User::create([
            'name' => 'Kepala TPQ',
            'username' => 'kepala',
            'email' => 'kepala@sekolah.id',
            'password' => Hash::make('password'),
            'role' => 'kepala',
        ]);

        $this->sekretaris = User::create([
            'name' => 'Sekretaris',
            'username' => 'sekretaris',
            'email' => 'sekretaris@sekolah.id',
            'password' => Hash::make('password'),
            'role' => 'sekretaris',
            'id_kelas' => $this->kelas->id,
        ]);

        $this->bendahara = User::create([
            'name' => 'Bendahara',
            'username' => 'bendahara',
            'email' => 'bendahara@sekolah.id',
            'password' => Hash::make('password'),
            'role' => 'bendahara',
            'id_kelas' => $this->kelas->id,
        ]);

        $this->guru = User::create([
            'name' => 'Guru',
            'username' => 'guru',
            'email' => 'guru@sekolah.id',
            'password' => Hash::make('password'),
            'role' => 'guru',
            'id_kelas' => $this->kelas->id,
        ]);

        Pengaturan::setValue('jam_masuk', '07:00');
        Pengaturan::setValue('jam_terlambat', '07:15');
        Pengaturan::setValue('jam_pulang', '12:00');
    }

    public function test_guru_and_sekretaris_can_update_absensi_status(): void
    {
        $today = Carbon::now('Asia/Jakarta')->format('Y-m-d');

        // 1. Guru updates absensi status -> 200
        $resGuru = $this->actingAs($this->guru, 'sanctum')->postJson('/api/absensi/status', [
            'id_siswa' => $this->siswa->id,
            'tanggal' => $today,
            'status' => 'Hadir',
        ]);
        $resGuru->assertStatus(200);

        // 2. Sekretaris updates absensi status -> 200
        $resSekretaris = $this->actingAs($this->sekretaris, 'sanctum')->postJson('/api/absensi/status', [
            'id_siswa' => $this->siswa->id,
            'tanggal' => $today,
            'status' => 'Sakit',
            'keterangan' => 'Demam',
        ]);
        $resSekretaris->assertStatus(200);
    }

    public function test_kepala_and_staff_can_view_absensi_riwayat(): void
    {
        // Kepala can view riwayat
        $resKepala = $this->actingAs($this->kepala, 'sanctum')->getJson('/api/absensi/riwayat');
        $resKepala->assertStatus(200);

        // Guru can view riwayat
        $resGuru = $this->actingAs($this->guru, 'sanctum')->getJson('/api/absensi/riwayat');
        $resGuru->assertStatus(200);
    }

    public function test_sekretaris_can_create_siswa_but_guru_bendahara_kepala_cannot(): void
    {
        // Sekretaris creates student -> 201
        $resSekretaris = $this->actingAs($this->sekretaris, 'sanctum')->postJson('/api/siswa', [
            'nisn' => '1009',
            'nama_lengkap' => 'Santri Baru',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
        ]);
        $resSekretaris->assertStatus(201);

        // Guru tries to create student -> 403 Forbidden
        $resGuru = $this->actingAs($this->guru, 'sanctum')->postJson('/api/siswa', [
            'nisn' => '1010',
            'nama_lengkap' => 'Santri Guru',
            'jenis_kelamin' => 'P',
            'id_kelas' => $this->kelas->id,
        ]);
        $resGuru->assertStatus(403);

        // Bendahara tries to create student -> 403 Forbidden
        $resBendahara = $this->actingAs($this->bendahara, 'sanctum')->postJson('/api/siswa', [
            'nisn' => '1011',
            'nama_lengkap' => 'Santri Bendahara',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
        ]);
        $resBendahara->assertStatus(403);

        // Kepala tries to create student -> 403 Forbidden
        $resKepala = $this->actingAs($this->kepala, 'sanctum')->postJson('/api/siswa', [
            'nisn' => '1012',
            'nama_lengkap' => 'Santri Kepala',
            'jenis_kelamin' => 'P',
            'id_kelas' => $this->kelas->id,
        ]);
        $resKepala->assertStatus(403);
    }

    public function test_guru_cannot_view_siswa_data_but_struktural_can(): void
    {
        // 1. Guru pengajar mencoba melihat daftar santri -> 403 Forbidden
        $resGuru = $this->actingAs($this->guru, 'sanctum')->getJson('/api/siswa');
        $resGuru->assertStatus(403);

        // 2. Guru pengajar mencoba melihat detail santri -> 403 Forbidden
        $resGuruDetail = $this->actingAs($this->guru, 'sanctum')->getJson("/api/siswa/{$this->siswa->id}");
        $resGuruDetail->assertStatus(403);

        // 3. Sekretaris melihat daftar santri -> 200 OK
        $resSekretaris = $this->actingAs($this->sekretaris, 'sanctum')->getJson('/api/siswa');
        $resSekretaris->assertStatus(200);

        // 4. Bendahara melihat daftar santri -> 200 OK
        $resBendahara = $this->actingAs($this->bendahara, 'sanctum')->getJson('/api/siswa');
        $resBendahara->assertStatus(200);

        // 5. Kepala melihat daftar santri -> 200 OK
        $resKepala = $this->actingAs($this->kepala, 'sanctum')->getJson('/api/siswa');
        $resKepala->assertStatus(200);

        // 6. Admin melihat daftar santri -> 200 OK
        $resAdmin = $this->actingAs($this->admin, 'sanctum')->getJson('/api/siswa');
        $resAdmin->assertStatus(200);
    }

    public function test_bendahara_cannot_update_absensi_status(): void
    {
        $today = Carbon::now('Asia/Jakarta')->format('Y-m-d');

        // Bendahara tries to update absensi status -> 403 Forbidden
        $resBendahara = $this->actingAs($this->bendahara, 'sanctum')->postJson('/api/absensi/status', [
            'id_siswa' => $this->siswa->id,
            'tanggal' => $today,
            'status' => 'Izin',
        ]);
        $resBendahara->assertStatus(403);
    }

    public function test_kepala_and_bendahara_can_view_wa_logs_but_cannot_broadcast(): void
    {
        // Kepala can view logs -> 200
        $resKepala = $this->actingAs($this->kepala, 'sanctum')->getJson('/api/wa/logs');
        $resKepala->assertStatus(200);

        // Bendahara can view logs -> 200
        $resBendahara = $this->actingAs($this->bendahara, 'sanctum')->getJson('/api/wa/logs');
        $resBendahara->assertStatus(200);

        // Bendahara tries to broadcast -> 403 Forbidden
        $resBroadcast = $this->actingAs($this->bendahara, 'sanctum')->postJson('/api/wa/broadcast', [
            'judul' => 'Pengumuman',
            'isi' => 'Tes pesan',
        ]);
        $resBroadcast->assertStatus(403);
    }

    public function test_only_admin_can_modify_settings(): void
    {
        // Admin updates settings -> 200
        $resAdmin = $this->actingAs($this->admin, 'sanctum')->postJson('/api/pengaturan', [
            'settings' => ['nama_instansi' => 'TPQ Nurul Mun\'im Berkah'],
        ]);
        $resAdmin->assertStatus(200);

        // Kepala tries to update settings -> 403 Forbidden
        $resKepala = $this->actingAs($this->kepala, 'sanctum')->postJson('/api/pengaturan', [
            'settings' => ['nama_instansi' => 'Hacked'],
        ]);
        $resKepala->assertStatus(403);

        // Bendahara tries to update settings -> 403 Forbidden
        $resBendahara = $this->actingAs($this->bendahara, 'sanctum')->postJson('/api/pengaturan', [
            'settings' => ['nama_instansi' => 'Hacked'],
        ]);
        $resBendahara->assertStatus(403);
    }

    public function test_admin_can_manage_users_and_assign_roles(): void
    {
        // 1. Admin lists staff users -> 200
        $resList = $this->actingAs($this->admin, 'sanctum')->getJson('/api/users');
        $resList->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);

        // 2. Admin creates a new Pengurus assigned to Jilid 1 -> 201
        $resCreate = $this->actingAs($this->admin, 'sanctum')->postJson('/api/users', [
            'name' => 'Ustadzah Baru',
            'username' => 'ustadzah_baru',
            'email' => 'baru@sekolah.id',
            'password' => 'secret123',
            'role' => 'bendahara',
            'id_kelas' => $this->kelas->id,
        ]);
        $resCreate->assertStatus(201)
            ->assertJsonPath('data.role', 'bendahara')
            ->assertJsonPath('data.id_kelas', $this->kelas->id);

        $newUserId = $resCreate->json('data.id');

        // 3. Admin updates role to Sekretaris
        $resUpdate = $this->actingAs($this->admin, 'sanctum')->putJson("/api/users/{$newUserId}", [
            'name' => 'Ustadzah Baru Update',
            'username' => 'ustadzah_baru',
            'role' => 'sekretaris',
            'id_kelas' => $this->kelas->id,
        ]);
        $resUpdate->assertStatus(200)
            ->assertJsonPath('data.role', 'sekretaris');

        // 4. Non-admin (Guru) cannot create or update users (403)
        $resNonAdmin = $this->actingAs($this->guru, 'sanctum')->postJson('/api/users', [
            'name' => 'Hacker',
            'username' => 'hacker',
            'password' => '123456',
            'role' => 'admin',
        ]);
        $resNonAdmin->assertStatus(403);

        // 5. Kepala TPQ can view Data Guru (200 OK) but cannot create/update/delete users (403)
        $resKepalaView = $this->actingAs($this->kepala, 'sanctum')->getJson('/api/users');
        $resKepalaView->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);

        $resKepalaCreate = $this->actingAs($this->kepala, 'sanctum')->postJson('/api/users', [
            'name' => 'Guru Baru',
            'username' => 'gurubaru',
            'password' => '123456',
            'role' => 'guru',
        ]);
        $resKepalaCreate->assertStatus(403);
    }
}
