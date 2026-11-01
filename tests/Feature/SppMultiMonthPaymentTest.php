<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\PembayaranSpp;
use App\Models\Siswa;
use App\Models\TagihanSpp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SppMultiMonthPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $bendahara;

    protected User $sekretaris;

    protected User $guru;

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

        $this->bendahara = User::create([
            'name' => 'Bendahara Test',
            'username' => 'bendahara_test',
            'email' => 'bendahara@test.local',
            'password' => bcrypt('password'),
            'role' => 'bendahara',
        ]);

        $this->sekretaris = User::create([
            'name' => 'Sekretaris Test',
            'username' => 'sekretaris_test',
            'email' => 'sekretaris@test.local',
            'password' => bcrypt('password'),
            'role' => 'sekretaris',
        ]);

        $this->guru = User::create([
            'name' => 'Guru Test',
            'username' => 'guru_test',
            'email' => 'guru@test.local',
            'password' => bcrypt('password'),
            'role' => 'guru',
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

    public function test_admin_can_pay_multiple_months_in_single_transaction(): void
    {
        Sanctum::actingAs($this->admin);

        // Buat 3 tagihan bulan Juli, Agustus, September
        $t1 = TagihanSpp::create([
            'id_siswa' => $this->siswa1->id,
            'bulan' => 7,
            'tahun' => 2026,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 50000,
            'status' => 'belum_lunas',
        ]);

        $t2 = TagihanSpp::create([
            'id_siswa' => $this->siswa1->id,
            'bulan' => 8,
            'tahun' => 2026,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 50000,
            'status' => 'belum_lunas',
        ]);

        $t3 = TagihanSpp::create([
            'id_siswa' => $this->siswa1->id,
            'bulan' => 9,
            'tahun' => 2026,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 50000,
            'status' => 'belum_lunas',
        ]);

        $payload = [
            'id_siswa' => $this->siswa1->id,
            'tagihan_ids' => [$t1->id, $t2->id, $t3->id],
            'metode_pembayaran' => 'Tunai',
            'catatan' => 'Pelunasan 3 Bulan Ganjil',
        ];

        $response = $this->postJson('/api/spp/bayar-multi', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'kuitansi' => [
                    'nomor_kuitansi',
                    'tanggal_bayar',
                    'nama_santri',
                    'nisn',
                    'kelas',
                    'total_bulan',
                    'daftar_bulan',
                    'total_bayar',
                    'metode',
                    'catatan',
                ],
            ]);

        // Verifikasi semua tagihan berubah menjadi lunas
        $this->assertEquals('lunas', $t1->fresh()->status);
        $this->assertEquals('lunas', $t2->fresh()->status);
        $this->assertEquals('lunas', $t3->fresh()->status);

        // Verifikasi ada 3 record pembayaran_spp dan semuanya memiliki nomor kuitansi yang sama
        $payments = PembayaranSpp::where('id_siswa', $this->siswa1->id)->get();
        $this->assertCount(3, $payments);

        $kuitansi = $response->json('kuitansi.nomor_kuitansi');
        $this->assertNotEmpty($kuitansi);
        foreach ($payments as $p) {
            $this->assertStringStartsWith($kuitansi, $p->nomor_kuitansi);
            $this->assertEquals('valid', $p->status_transaksi);
            $this->assertEquals('Tunai', $p->metode_pembayaran);
        }

        $this->assertEquals(150000, $response->json('kuitansi.total_bayar'));
        $this->assertEquals(3, $response->json('kuitansi.total_bulan'));
    }

    public function test_bendahara_and_sekretaris_can_record_spp_payments(): void
    {
        $tBendahara = TagihanSpp::create([
            'id_siswa' => $this->siswa1->id,
            'bulan' => 10,
            'tahun' => 2026,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 50000,
            'status' => 'belum_lunas',
        ]);

        // 1. Bendahara mencatat pembayaran
        Sanctum::actingAs($this->bendahara);
        $resBendahara = $this->postJson('/api/spp/bayar', [
            'id_tagihan' => $tBendahara->id,
            'jumlah_bayar' => 50000,
            'metode_pembayaran' => 'Tunai',
        ]);
        $resBendahara->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals('lunas', $tBendahara->fresh()->status);

        // 2. Sekretaris mencatat pembayaran (input cepat & bayar multi)
        Sanctum::actingAs($this->sekretaris);
        $resSekretaris = $this->postJson('/api/spp/input-cepat', [
            'bulan' => 11,
            'tahun' => 2026,
            'id_kelas' => $this->kelas->id,
            'items' => [
                [
                    'id_siswa' => $this->siswa2->id,
                    'nominal' => 50000,
                    'metode' => 'Tunai',
                ],
            ],
        ]);
        $resSekretaris->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_cannot_pay_tagihan_belonging_to_another_student(): void
    {
        Sanctum::actingAs($this->admin);

        $t1 = TagihanSpp::create([
            'id_siswa' => $this->siswa1->id,
            'bulan' => 7,
            'tahun' => 2026,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 50000,
            'status' => 'belum_lunas',
        ]);

        $t2 = TagihanSpp::create([
            'id_siswa' => $this->siswa2->id,
            'bulan' => 7,
            'tahun' => 2026,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 50000,
            'status' => 'belum_lunas',
        ]);

        $payload = [
            'id_siswa' => $this->siswa1->id,
            'tagihan_ids' => [$t1->id, $t2->id], // t2 bukan milik siswa1
            'metode_pembayaran' => 'Transfer',
        ];

        $response = $this->postJson('/api/spp/bayar-multi', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        // Tagihan tidak berubah
        $this->assertEquals('belum_lunas', $t1->fresh()->status);
        $this->assertEquals('belum_lunas', $t2->fresh()->status);
        $this->assertEquals(0, PembayaranSpp::count());
    }

    public function test_cannot_pay_already_paid_tagihan(): void
    {
        Sanctum::actingAs($this->admin);

        $t1 = TagihanSpp::create([
            'id_siswa' => $this->siswa1->id,
            'bulan' => 7,
            'tahun' => 2026,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 50000,
            'status' => 'lunas',
        ]);

        $payload = [
            'id_siswa' => $this->siswa1->id,
            'tagihan_ids' => [$t1->id],
            'metode_pembayaran' => 'Tunai',
        ];

        $response = $this->postJson('/api/spp/bayar-multi', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertEquals(0, PembayaranSpp::count());
    }

    public function test_unauthorized_role_cannot_process_multi_payment(): void
    {
        Sanctum::actingAs($this->guru);

        $t1 = TagihanSpp::create([
            'id_siswa' => $this->siswa1->id,
            'bulan' => 7,
            'tahun' => 2026,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 50000,
            'status' => 'belum_lunas',
        ]);

        $payload = [
            'id_siswa' => $this->siswa1->id,
            'tagihan_ids' => [$t1->id],
            'metode_pembayaran' => 'Tunai',
        ];

        $response = $this->postJson('/api/spp/bayar-multi', $payload);

        $response->assertStatus(403);
    }
}

