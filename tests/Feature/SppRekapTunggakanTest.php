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

    public function test_rekap_tunggakan_excludes_months_before_semester_start_juli_2026(): void
    {
        Sanctum::actingAs($this->admin);

        // Tagihan bulan Mei & Juni 2026 (Sebelum awal semester ajaran aktif)
        foreach ([5, 6] as $bln) {
            TagihanSpp::create([
                'id_siswa' => $this->siswa1->id,
                'bulan' => $bln,
                'tahun' => 2026,
                'tahun_ajaran' => '2025/2026',
                'nominal_tagihan' => 50000,
                'status' => 'belum_lunas',
            ]);
        }

        // Tagihan bulan Juli & Agustus 2026 (Awal semester berjalan)
        foreach ([7, 8] as $bln) {
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
        $rekap = $service->getRekapTunggakanSantri(null, 8, 2026);

        // Hanya menghitung 2 bulan (Juli dan Agustus), Mei & Juni tidak dihitung
        $this->assertEquals(1, $rekap['summary']['total_santri_menunggak']);
        $this->assertEquals(100000, $rekap['summary']['total_nominal_tunggakan']);
        $this->assertEquals(2, $rekap['santri'][0]['total_bulan']);
        $this->assertEquals(['Juli', 'Agustus'], array_column($rekap['santri'][0]['daftar_bulan'], 'nama_bulan'));
    }

    public function test_sinkronkan_semester_generates_all_bills_for_active_santri(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/spp/sinkronkan-semester', [
            'from_bulan' => 7,
            'from_tahun' => 2026,
            'to_bulan' => 12,
            'to_tahun' => 2026,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'created' => 12, // 2 santri aktif * 6 bulan
                    'total_santri' => 2,
                ],
            ]);

        $this->assertDatabaseCount('tagihan_spp', 12);
    }

    public function test_santri_paid_until_december_2026_shows_arrears_in_2027(): void
    {
        Sanctum::actingAs($this->admin);

        // Siswa 1 lunas seluruh bulan 7-12 di tahun 2026
        foreach ([7, 8, 9, 10, 11, 12] as $bln) {
            TagihanSpp::create([
                'id_siswa' => $this->siswa1->id,
                'bulan' => $bln,
                'tahun' => 2026,
                'tahun_ajaran' => '2026/2027',
                'nominal_tagihan' => 50000,
                'status' => 'lunas',
            ]);
        }

        // Siswa 2 juga lunas seluruh bulan 7-12 di tahun 2026
        foreach ([7, 8, 9, 10, 11, 12] as $bln) {
            TagihanSpp::create([
                'id_siswa' => $this->siswa2->id,
                'bulan' => $bln,
                'tahun' => 2026,
                'tahun_ajaran' => '2026/2027',
                'nominal_tagihan' => 50000,
                'status' => 'lunas',
            ]);
        }

        // Jika cut-off Desember 2026, maka tidak ada tunggakan
        $service = app(SppBillingService::class);
        $rekapDes = $service->getRekapTunggakanSantri(null, 12, 2026);
        $this->assertEquals(0, $rekapDes['summary']['total_santri_menunggak']);

        // Ketika cut-off Januari 2027, sistem otomatis menyinkronkan 2027 dan menampilkan 1 bulan tunggakan
        $rekapJan = $service->getRekapTunggakanSantri(null, 1, 2027);
        $this->assertEquals(2, $rekapJan['summary']['total_santri_menunggak']);
        $this->assertEquals(100000, $rekapJan['summary']['total_nominal_tunggakan']);
        $this->assertEquals(1, $rekapJan['santri'][0]['total_bulan']);
        $this->assertEquals(50000, $rekapJan['santri'][0]['total_tunggakan']);
        $this->assertEquals(['Januari'], array_column($rekapJan['santri'][0]['daftar_bulan'], 'nama_bulan'));

        // Ketika cut-off Februari 2027, menampilkan 2 bulan tunggakan (Januari & Februari)
        $rekapFeb = $service->getRekapTunggakanSantri(null, 2, 2027);
        $this->assertEquals(2, $rekapFeb['summary']['total_santri_menunggak']);
        $this->assertEquals(200000, $rekapFeb['summary']['total_nominal_tunggakan']);
        $this->assertEquals(2, $rekapFeb['santri'][0]['total_bulan']);
        $this->assertEquals(100000, $rekapFeb['santri'][0]['total_tunggakan']);
        $this->assertEquals(['Januari', 'Februari'], array_column($rekapFeb['santri'][0]['daftar_bulan'], 'nama_bulan'));
    }
}
