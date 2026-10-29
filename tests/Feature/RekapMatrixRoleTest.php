<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RekapMatrixRoleTest extends TestCase
{
    use RefreshDatabase;

    protected Kelas $kelas1;

    protected Kelas $kelas2;

    protected Siswa $siswa1;

    protected Siswa $siswa2;

    protected User $admin;

    protected User $kepala;

    protected User $sekretaris;

    protected User $guruKelas1;

    protected User $guruTanpaKelas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kelas1 = Kelas::create([
            'nama_kelas' => 'JILID 1',
            'urutan' => 1,
            'warna_label' => '#10b981',
        ]);

        $this->kelas2 = Kelas::create([
            'nama_kelas' => 'JILID 2',
            'urutan' => 2,
            'warna_label' => '#3b82f6',
        ]);

        $this->siswa1 = Siswa::create([
            'nisn' => '1001',
            'nama_lengkap' => 'Santri Jilid Satu',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas1->id,
            'status' => 'aktif',
        ]);

        $this->siswa2 = Siswa::create([
            'nisn' => '2002',
            'nama_lengkap' => 'Santri Jilid Dua',
            'jenis_kelamin' => 'P',
            'id_kelas' => $this->kelas2->id,
            'status' => 'aktif',
        ]);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'username' => 'admintest',
            'email' => 'admin@test.id',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->sekretaris = User::create([
            'name' => 'Sekretaris Test',
            'username' => 'sekretaristest',
            'email' => 'sekretaris@test.id',
            'password' => Hash::make('password'),
            'role' => 'sekretaris',
        ]);

        $this->kepala = User::create([
            'name' => 'Kepala Test',
            'username' => 'kepalatest',
            'email' => 'kepala@test.id',
            'password' => Hash::make('password'),
            'role' => 'kepala',
        ]);

        $this->guruKelas1 = User::create([
            'name' => 'Ustadz Jilid 1',
            'username' => 'gurukelas1',
            'email' => 'guru1@test.id',
            'password' => Hash::make('password'),
            'role' => 'guru',
            'id_kelas' => $this->kelas1->id,
        ]);

        $this->guruTanpaKelas = User::create([
            'name' => 'Ustadz Tanpa Kelas',
            'username' => 'gurutanpakelas',
            'email' => 'gurunone@test.id',
            'password' => Hash::make('password'),
            'role' => 'guru',
            'id_kelas' => null,
        ]);
    }

    public function test_admin_bisa_melihat_semua_kelas_atau_memfilter_kelas_di_matrix(): void
    {
        // 1. Tanpa filter id_kelas -> admin melihat semua siswa
        $responseAll = $this->actingAs($this->admin)->getJson('/api/absensi/matrix');
        $responseAll->assertOk();
        $itemsAll = $responseAll->json('data.data');
        $this->assertCount(2, $itemsAll);

        // 2. Filter id_kelas = kelas2 -> admin hanya melihat siswa kelas 2
        $responseKelas2 = $this->actingAs($this->admin)->getJson('/api/absensi/matrix?id_kelas='.$this->kelas2->id);
        $responseKelas2->assertOk();
        $itemsKelas2 = $responseKelas2->json('data.data');
        $this->assertCount(1, $itemsKelas2);
        $this->assertEquals('2002', $itemsKelas2[0]['nisn']);
    }

    public function test_sekretaris_bisa_memfilter_kelas_bebas_di_matrix(): void
    {
        // Sekretaris filter kelas 1
        $response = $this->actingAs($this->sekretaris)->getJson('/api/absensi/matrix?id_kelas='.$this->kelas1->id);
        $response->assertOk();
        $items = $response->json('data.data');
        $this->assertCount(1, $items);
        $this->assertEquals('1001', $items[0]['nisn']);
    }

    public function test_kepala_bisa_melihat_semua_kelas_dan_memfilter_kelas_di_matrix(): void
    {
        $responseAll = $this->actingAs($this->kepala)->getJson('/api/absensi/matrix');
        $responseAll->assertOk();
        $this->assertCount(2, $responseAll->json('data.data'));

        $responseFilter = $this->actingAs($this->kepala)->getJson('/api/absensi/matrix?id_kelas='.$this->kelas2->id);
        $responseFilter->assertOk();
        $items = $responseFilter->json('data.data');
        $this->assertCount(1, $items);
        $this->assertEquals('2002', $items[0]['nisn']);
    }

    public function test_guru_hanya_melihat_santri_jilid_binaannya_dan_dikunci_meskipun_request_kelas_lain(): void
    {
        // Guru kelas 1 request tanpa filter kelas -> tetap dikunci hanya ke kelas 1
        $responseNoFilter = $this->actingAs($this->guruKelas1)->getJson('/api/absensi/matrix');
        $responseNoFilter->assertOk();
        $items1 = $responseNoFilter->json('data.data');
        $this->assertCount(1, $items1);
        $this->assertEquals('1001', $items1[0]['nisn']);

        // Guru kelas 1 mencoba meminta id_kelas kelas 2 -> backend tetap mengunci ke kelas 1
        $responseAttemptHijack = $this->actingAs($this->guruKelas1)->getJson('/api/absensi/matrix?id_kelas='.$this->kelas2->id);
        $responseAttemptHijack->assertOk();
        $itemsAttempt = $responseAttemptHijack->json('data.data');
        $this->assertCount(1, $itemsAttempt);
        $this->assertEquals('1001', $itemsAttempt[0]['nisn']); // Tetap hanya santri kelas 1
    }

    public function test_guru_tanpa_kelas_binaan_tidak_melihat_santri_kelas_lain(): void
    {
        $response = $this->actingAs($this->guruTanpaKelas)->getJson('/api/absensi/matrix');
        $response->assertOk();
        $items = $response->json('data.data');
        $this->assertCount(0, $items);
    }
}
