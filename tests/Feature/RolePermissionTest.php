<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\TagihanSpp;
use App\Models\User;
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

    protected TagihanSpp $tagihan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kelas = Kelas::create([
            'nama_kelas' => 'JILID 1',
            'nominal_spp' => 50000,
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

        $this->tagihan = TagihanSpp::create([
            'id_siswa' => $this->siswa->id,
            'bulan' => 10,
            'tahun' => 2026,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 50000,
            'status' => 'belum_lunas',
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

    public function test_bendahara_can_pay_spp_but_guru_and_sekretaris_cannot(): void
    {
        // 1. Bendahara pays -> 200
        $resBendahara = $this->actingAs($this->bendahara, 'sanctum')->postJson('/api/spp/bayar', [
            'id_tagihan' => $this->tagihan->id,
            'jumlah_bayar' => 50000,
            'metode_pembayaran' => 'Tunai',
        ]);
        $resBendahara->assertStatus(200);

        // 2. Guru tries to pay -> 403 Forbidden
        $resGuru = $this->actingAs($this->guru, 'sanctum')->postJson('/api/spp/bayar', [
            'id_tagihan' => $this->tagihan->id,
            'jumlah_bayar' => 50000,
            'metode_pembayaran' => 'Tunai',
        ]);
        $resGuru->assertStatus(403);

        // 3. Sekretaris tries to pay -> 403 Forbidden
        $resSekretaris = $this->actingAs($this->sekretaris, 'sanctum')->postJson('/api/spp/bayar', [
            'id_tagihan' => $this->tagihan->id,
            'jumlah_bayar' => 50000,
            'metode_pembayaran' => 'Tunai',
        ]);
        $resSekretaris->assertStatus(403);
    }

    public function test_kepala_can_view_spp_dashboard_and_tagihan_but_cannot_pay(): void
    {
        // Kepala can view SPP dashboard
        $resDash = $this->actingAs($this->kepala, 'sanctum')->getJson('/api/spp/dashboard');
        $resDash->assertStatus(200);

        // Kepala can view SPP tagihan
        $resTagihan = $this->actingAs($this->kepala, 'sanctum')->getJson('/api/spp/tagihan');
        $resTagihan->assertStatus(200);

        // Kepala cannot pay SPP (403)
        $resPay = $this->actingAs($this->kepala, 'sanctum')->postJson('/api/spp/bayar', [
            'id_tagihan' => $this->tagihan->id,
            'jumlah_bayar' => 50000,
        ]);
        $resPay->assertStatus(403);
    }

    public function test_sekretaris_can_create_siswa_but_guru_cannot(): void
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
}
