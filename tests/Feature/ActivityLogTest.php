<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\TagihanSpp;
use App\Models\User;
use App\Services\AbsensiScannerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $guruUser;

    protected Kelas $kelas;

    protected Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'name' => 'Admin Utama',
            'username' => 'admin_utama',
            'email' => 'admin@tpq.id',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->guruUser = User::create([
            'name' => 'Ustadz Guru',
            'username' => 'guru_test',
            'email' => 'guru@tpq.id',
            'password' => bcrypt('password123'),
            'role' => 'guru',
        ]);

        $this->kelas = Kelas::create([
            'nama_kelas' => 'JILID 1',
            'nominal_spp' => 15000,
            'wali_kelas' => 'Ustadz Guru',
        ]);

        $this->siswa = Siswa::create([
            'nisn' => '1001',
            'nama_lengkap' => 'Muhammad Santri',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
            'no_hp_ortu' => '081234567890',
            'nama_ortu' => 'Bapak Santri',
            'status' => 'aktif',
        ]);
    }

    public function test_login_and_logout_records_activity_log(): void
    {
        // 1. Test Login
        $loginRes = $this->postJson('/api/login', [
            'username' => 'admin_utama',
            'password' => 'password123',
        ]);

        $loginRes->assertStatus(200);

        $loginLog = ActivityLog::where('modul', 'auth')
            ->where('aksi', 'login')
            ->latest('id')
            ->first();

        $this->assertNotNull($loginLog);
        $this->assertStringContainsString('admin_utama', $loginLog->deskripsi);
        $this->assertEquals($this->adminUser->id, $loginLog->id_user);

        // 2. Test Logout
        Sanctum::actingAs($this->adminUser);
        $logoutRes = $this->postJson('/api/logout');
        $logoutRes->assertStatus(200);

        $logoutLog = ActivityLog::where('modul', 'auth')
            ->where('aksi', 'logout')
            ->latest('id')
            ->first();

        $this->assertNotNull($logoutLog);
        $this->assertStringContainsString('logout', $logoutLog->deskripsi);
    }

    public function test_santri_crud_records_activity_log(): void
    {
        Sanctum::actingAs($this->adminUser);

        // 1. Tambah santri
        $resStore = $this->postJson('/api/siswa', [
            'nisn' => '1002',
            'nama_lengkap' => 'Fatimah Zahra',
            'jenis_kelamin' => 'P',
            'id_kelas' => $this->kelas->id,
            'no_hp_ortu' => '081234567891',
        ]);
        $resStore->assertStatus(201);

        $this->assertDatabaseHas('activity_logs', [
            'modul' => 'santri',
            'aksi' => 'tambah',
        ]);

        $createdSiswa = Siswa::where('nisn', '1002')->first();

        // 2. Ubah santri
        $resUpdate = $this->putJson("/api/siswa/{$createdSiswa->id}", [
            'nisn' => '1002',
            'nama_lengkap' => 'Fatimah Az-Zahra',
            'id_kelas' => $this->kelas->id,
            'jenis_kelamin' => 'P',
            'no_hp_ortu' => '081234567891',
            'status' => 'aktif',
        ]);
        $resUpdate->assertStatus(200);

        $this->assertDatabaseHas('activity_logs', [
            'modul' => 'santri',
            'aksi' => 'ubah',
        ]);

        // 3. Hapus santri
        $resDelete = $this->deleteJson("/api/siswa/{$createdSiswa->id}");
        $resDelete->assertStatus(200);

        $this->assertDatabaseHas('activity_logs', [
            'modul' => 'santri',
            'aksi' => 'hapus',
        ]);
    }

    public function test_scan_presensi_records_activity_log(): void
    {
        Pengaturan::setValue('jam_masuk', '07:00');
        Pengaturan::setValue('jam_terlambat', '07:15');

        $scanner = app(AbsensiScannerService::class);
        $result = $scanner->scan('1001');

        $this->assertTrue($result['success']);

        $this->assertDatabaseHas('activity_logs', [
            'modul' => 'presensi',
            'aksi' => 'scan',
        ]);

        $log = ActivityLog::where('modul', 'presensi')->latest('id')->first();
        $this->assertStringContainsString('Muhammad Santri', $log->deskripsi);
    }

    public function test_pembayaran_spp_records_activity_log(): void
    {
        Sanctum::actingAs($this->adminUser);

        $now = Carbon::now('Asia/Jakarta');
        $tagihan = TagihanSpp::create([
            'id_siswa' => $this->siswa->id,
            'bulan' => (int) $now->month,
            'tahun' => (int) $now->year,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 15000,
            'status' => 'belum_lunas',
        ]);

        $resBayar = $this->postJson('/api/spp/bayar', [
            'id_tagihan' => $tagihan->id,
            'jumlah_bayar' => 15000,
            'metode_pembayaran' => 'Tunai',
            'catatan' => 'Lunas SPP',
        ]);

        $resBayar->assertStatus(200);

        $this->assertDatabaseHas('activity_logs', [
            'modul' => 'spp',
            'aksi' => 'bayar',
        ]);

        $log = ActivityLog::where('modul', 'spp')->latest('id')->first();
        $this->assertStringContainsString('Muhammad Santri', $log->deskripsi);
        $this->assertStringContainsString('15.000', $log->deskripsi);
    }

    public function test_perubahan_pengaturan_records_activity_log(): void
    {
        Sanctum::actingAs($this->adminUser);

        $res = $this->postJson('/api/pengaturan', [
            'settings' => [
                'nama_instansi' => "TPQ Nurul Mun'im Hebat",
                'kontak_instansi' => '081234567899',
            ],
        ]);

        $res->assertStatus(200);

        $this->assertDatabaseHas('activity_logs', [
            'modul' => 'pengaturan',
            'aksi' => 'ubah',
        ]);

        $log = ActivityLog::where('modul', 'pengaturan')->latest('id')->first();
        $this->assertStringContainsString('nama_instansi', $log->deskripsi);
    }

    public function test_activity_logs_api_accessible_by_admin_and_denied_for_guru(): void
    {
        // 1. Buat beberapa record log
        ActivityLog::record('auth', 'login', 'Admin login ke sistem', null, $this->adminUser);
        ActivityLog::record('presensi', 'scan', 'Scan santri 1', null, $this->adminUser);

        // 2. Akses sebagai Admin
        Sanctum::actingAs($this->adminUser);
        $resAdmin = $this->getJson('/api/activity-logs');
        $resAdmin->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'total'],
            ]);

        $resStats = $this->getJson('/api/activity-logs/stats');
        $resStats->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_hari_ini',
                    'total_auth',
                    'total_presensi',
                    'total_spp',
                    'total_master',
                    'total_pengaturan',
                    'total_whatsapp',
                ],
            ]);

        // 3. Akses sebagai Guru (harus 403 Forbidden)
        Sanctum::actingAs($this->guruUser);
        $resGuru = $this->getJson('/api/activity-logs');
        $resGuru->assertStatus(403);
    }

    public function test_failed_login_records_activity_log(): void
    {
        $res = $this->postJson('/api/login', [
            'username' => 'user_tidak_ada',
            'password' => 'salah_password',
        ]);

        $res->assertStatus(422);

        $this->assertDatabaseHas('activity_logs', [
            'modul' => 'auth',
            'aksi' => 'login_gagal',
        ]);
    }

    public function test_ensure_schema_exists_creates_table_when_missing(): void
    {
        // Reset static property schemaChecked via Reflection
        $reflection = new \ReflectionClass(ActivityLog::class);
        $prop = $reflection->getProperty('schemaChecked');
        $prop->setValue(null, false);

        Schema::dropIfExists('activity_logs');
        $this->assertFalse(Schema::hasTable('activity_logs'));

        ActivityLog::ensureSchemaExists();

        $this->assertTrue(Schema::hasTable('activity_logs'));
        $this->assertDatabaseHas('activity_logs', [
            'modul' => 'pengaturan',
            'aksi' => 'inisialisasi',
        ]);
    }
}
