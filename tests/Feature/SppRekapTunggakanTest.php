<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TagihanSpp;
use App\Models\User;
use App\Services\SppBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SppRekapTunggakanTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Kelas $kelas;

    protected Siswa $siswa1;

    protected Siswa $siswa2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Test',
            'username' => 'admin_test',
            'email' => 'admin@test.local',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->kelas = Kelas::create([
            'nama_kelas' => 'Jilid 1',
            'nominal_spp' => 50000,
        ]);

        $this->siswa1 = Siswa::create([
            'nisn' => '1001',
            'nama_lengkap' => 'Ahmad Fauzi',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
            'no_hp_ortu' => '081234567890',
            'status' => 'aktif',
        ]);

        $this->siswa2 = Siswa::create([
            'nisn' => '1002',
            'nama_lengkap' => 'Fatimah Az-Zahra',
            'jenis_kelamin' => 'P',
            'id_kelas' => $this->kelas->id,
            'no_hp_ortu' => '081234567891',
            'status' => 'aktif',
        ]);
    }

    public function test_dashboard_finansial_excludes_future_months(): void
    {
        // Tagihan Juli, Agustus, September, Oktober (Masa lalu & Sekarang)
        foreach ([7, 8, 9, 10] as $bln) {
            TagihanSpp::create([
                'id_siswa' => $this->siswa1->id,
                'bulan' => $bln,
                'tahun' => 2026,
                'tahun_ajaran' => '2026/2027',
                'nominal_tagihan' => 50000,
                'status' => 'belum_lunas',
            ]);
        }

        // Tagihan November, Desember (Masa depan)
        foreach ([11, 12] as $bln) {
            TagihanSpp::create([
                'id_siswa' => $this->siswa1->id,
                'bulan' => $bln,
                'tahun' => 2026,
                'tahun_ajaran' => '2026/2027',
                'nominal_tagihan' => 50000,
                'status' => 'belum_lunas',
            ]);
        }

        $service = app(SppBillingService::class);
        $dashboard = $service->getDashboardFinansial(10, 2026);

        // Hanya menghitung 4 bulan (Juli - Oktober) = 200.000, bukan 300.000 (6 bulan)
        $this->assertEquals(200000, $dashboard['total_tunggakan_aktif']);
        $this->assertEquals(1, $dashboard['santri_belum_lunas']);
    }

    public function test_rekap_tunggakan_api_endpoint(): void
    {
        Sanctum::actingAs($this->admin);

        // Siswa 1 nunggak Juli - Oktober (4 bulan = 200.000)
        foreach ([7, 8, 9, 10] as $bln) {
            TagihanSpp::create([
                'id_siswa' => $this->siswa1->id,
                'bulan' => $bln,
                'tahun' => 2026,
                'tahun_ajaran' => '2026/2027',
                'nominal_tagihan' => 50000,
                'status' => 'belum_lunas',
            ]);
        }

        // Tagihan masa depan (November) tidak boleh dihitung
        TagihanSpp::create([
            'id_siswa' => $this->siswa1->id,
            'bulan' => 11,
            'tahun' => 2026,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 50000,
            'status' => 'belum_lunas',
        ]);

        // Siswa 2 nunggak Oktober saja (1 bulan = 50.000)
        TagihanSpp::create([
            'id_siswa' => $this->siswa2->id,
            'bulan' => 10,
            'tahun' => 2026,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 50000,
            'status' => 'belum_lunas',
        ]);

        $response = $this->getJson('/api/spp/rekap-tunggakan?bulan=10&tahun=2026');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_santri_menunggak',
                        'total_nominal_tunggakan',
                        'rata_rata_bulan',
                        'cut_off_bulan',
                        'cut_off_tahun',
                    ],
                    'santri' => [
                        '*' => [
                            'id_siswa',
                            'nama_lengkap',
                            'nama_kelas',
                            'total_bulan',
                            'total_tunggakan',
                            'daftar_bulan',
                        ],
                    ],
                ],
            ]);

        $summary = $response->json('data.summary');
        $this->assertEquals(2, $summary['total_santri_menunggak']);
        $this->assertEquals(250000, $summary['total_nominal_tunggakan']);

        $santriList = $response->json('data.santri');
        // Siswa 1 harus paling atas karena memiliki 4 bulan tunggakan
        $this->assertEquals('Ahmad Fauzi', $santriList[0]['nama_lengkap']);
        $this->assertEquals(4, $santriList[0]['total_bulan']);
        $this->assertEquals(200000, $santriList[0]['total_tunggakan']);
    }
}

