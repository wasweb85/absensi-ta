<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\PembayaranSpp;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\TagihanSpp;
use App\Models\User;
use App\Services\CloudinaryInvoiceService;
use App\Services\SppBillingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CloudinaryInvoiceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Kelas $kelas;

    protected Siswa $siswa;

    protected TagihanSpp $tagihan;

    protected function setUp(): void
    {
        parent::setUp();

        Pengaturan::setValue('nama_sekolah', "TPQ Nurul Mun'im");
        Pengaturan::setValue('no_hp_bendahara', '085235567818');

        $this->admin = User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@tpq.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->kelas = Kelas::create([
            'nama_kelas' => 'JILID 3',
            'urutan' => 3,
            'nominal_spp' => 20000,
        ]);

        $this->siswa = Siswa::create([
            'nisn' => '3001',
            'nama_lengkap' => 'Muhammad Fariduddin Attar Jalili',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
            'nama_ortu' => 'Bapak Attar',
            'no_hp_ortu' => '082235764322',
            'status' => 'aktif',
        ]);

        $this->tagihan = TagihanSpp::create([
            'id_siswa' => $this->siswa->id,
            'bulan' => 11,
            'tahun' => 2026,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 20000,
            'status' => 'belum_lunas',
        ]);
    }

    public function test_pembayaran_spp_automatically_generates_cryptographic_invoice_token_and_secure_url(): void
    {
        $pembayaran = PembayaranSpp::create([
            'id_tagihan' => $this->tagihan->id,
            'id_siswa' => $this->siswa->id,
            'nomor_kuitansi' => 'KW-202611-00001',
            'tanggal_bayar' => Carbon::now('Asia/Jakarta'),
            'jumlah_bayar' => 20000,
            'metode_pembayaran' => 'Tunai',
            'id_petugas' => $this->admin->id,
            'status_transaksi' => 'valid',
        ]);

        $this->assertNotEmpty($pembayaran->invoice_token);
        $this->assertGreaterThanOrEqual(32, strlen($pembayaran->invoice_token));
        $this->assertStringContainsString('/invoice/'.$pembayaran->invoice_token, $pembayaran->secure_invoice_url);
    }

    public function test_public_invoice_view_renders_correctly_with_masked_phone(): void
    {
        $pembayaran = PembayaranSpp::create([
            'id_tagihan' => $this->tagihan->id,
            'id_siswa' => $this->siswa->id,
            'nomor_kuitansi' => 'KW-202611-00002',
            'tanggal_bayar' => Carbon::now('Asia/Jakarta'),
            'jumlah_bayar' => 20000,
            'metode_pembayaran' => 'Tunai',
            'id_petugas' => $this->admin->id,
            'status_transaksi' => 'valid',
            'cloudinary_url' => 'https://res.cloudinary.com/demo/image/upload/sample_receipt.png',
        ]);

        $response = $this->get('/invoice/'.$pembayaran->invoice_token);

        $response->assertStatus(200);
        $response->assertSee('Muhammad Fariduddin Attar Jalili');
        $response->assertSee('KW-202611-00002');
        $response->assertSee('LUNAS');
        // Pastikan nomor HP disensor/dimasking (0822*****322 bukan nomor utuh)
        $response->assertSee('0822*****322');
        $response->assertDontSee('082235764322');
        // Pastikan gambar Cloudinary ada di tampilan
        $response->assertSee('https://res.cloudinary.com/demo/image/upload/sample_receipt.png');
    }

    public function test_public_invoice_view_aborts_404_for_invalid_token(): void
    {
        $response = $this->get('/invoice/invalid_non_existent_token_1234567890');
        $response->assertStatus(404);
    }

    public function test_spp_upload_invoice_endpoint_updates_payment_record_with_cloudinary_data(): void
    {
        $pembayaran = PembayaranSpp::create([
            'id_tagihan' => $this->tagihan->id,
            'id_siswa' => $this->siswa->id,
            'nomor_kuitansi' => 'KW-202611-00003',
            'tanggal_bayar' => Carbon::now('Asia/Jakarta'),
            'jumlah_bayar' => 20000,
            'metode_pembayaran' => 'Tunai',
            'id_petugas' => $this->admin->id,
            'status_transaksi' => 'valid',
        ]);

        $mockCloudinary = Mockery::mock(CloudinaryInvoiceService::class);
        $mockCloudinary->shouldReceive('uploadInvoiceImage')
            ->once()
            ->andReturn([
                'success' => true,
                'public_id' => 'tpq_invoices/inv_test_123',
                'secure_url' => 'https://res.cloudinary.com/demo/image/upload/tpq_invoices/inv_test_123.png',
            ]);

        $this->app->instance(CloudinaryInvoiceService::class, $mockCloudinary);

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/spp/upload-invoice', [
            'id_pembayaran' => $pembayaran->id,
            'image_base64' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAA=',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id_pembayaran' => $pembayaran->id,
                'cloudinary_url' => 'https://res.cloudinary.com/demo/image/upload/tpq_invoices/inv_test_123.png',
            ],
        ]);

        $pembayaran->refresh();
        $this->assertEquals('tpq_invoices/inv_test_123', $pembayaran->cloudinary_public_id);
        $this->assertEquals('https://res.cloudinary.com/demo/image/upload/tpq_invoices/inv_test_123.png', $pembayaran->cloudinary_url);
        $this->assertEquals('success', $pembayaran->upload_status);
    }

    public function test_spp_billing_service_returns_invoice_token_and_secure_url_in_kuitansi_payload(): void
    {
        /** @var SppBillingService $service */
        $service = app(SppBillingService::class);

        $result = $service->prosesPembayaran([
            'id_tagihan' => $this->tagihan->id,
            'jumlah_bayar' => 20000,
            'metode_pembayaran' => 'Tunai',
        ], $this->admin->id);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('kuitansi', $result);
        $this->assertNotEmpty($result['kuitansi']['id_pembayaran']);
        $this->assertNotEmpty($result['kuitansi']['invoice_token']);
        $this->assertNotEmpty($result['kuitansi']['invoice_url']);
    }

    public function test_spp_get_invoice_link_endpoint_returns_valid_token_and_url(): void
    {
        $pembayaran = PembayaranSpp::create([
            'id_tagihan' => $this->tagihan->id,
            'id_siswa' => $this->siswa->id,
            'nomor_kuitansi' => 'KW-202611-00004',
            'tanggal_bayar' => Carbon::now('Asia/Jakarta'),
            'jumlah_bayar' => 20000,
            'metode_pembayaran' => 'Tunai',
            'id_petugas' => $this->admin->id,
            'status_transaksi' => 'valid',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/spp/invoice-link', [
            'nomor_kuitansi' => 'KW-202611-00004',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'nomor_kuitansi' => 'KW-202611-00004',
            ],
        ]);
        $this->assertNotEmpty($response->json('data.secure_invoice_url'));
        $this->assertStringContainsString('/invoice/', $response->json('data.secure_invoice_url'));
    }

    public function test_public_invoice_can_be_accessed_via_nomor_kuitansi_fallback(): void
    {
        $pembayaran = PembayaranSpp::create([
            'id_tagihan' => $this->tagihan->id,
            'id_siswa' => $this->siswa->id,
            'nomor_kuitansi' => 'KW-202611-00005',
            'tanggal_bayar' => Carbon::now('Asia/Jakarta'),
            'jumlah_bayar' => 20000,
            'metode_pembayaran' => 'Tunai',
            'id_petugas' => $this->admin->id,
            'status_transaksi' => 'valid',
        ]);

        $response = $this->get('/invoice/KW-202611-00005');

        // Jika memiliki invoice_token, di-redirect ke URL invoice_token canonical
        if ($pembayaran->invoice_token) {
            $response->assertRedirect(route('public.invoice.show', ['token' => $pembayaran->invoice_token]));
        } else {
            $response->assertStatus(200);
            $response->assertSee('KW-202611-00005');
        }
    }

    public function test_single_month_payment_only_displays_its_own_period_and_amount(): void
    {
        // Tagihan Oktober 2026
        $tagihanOkt = TagihanSpp::create([
            'id_siswa' => $this->siswa->id,
            'bulan' => 10,
            'tahun' => 2026,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 20000,
            'status' => 'lunas',
        ]);

        // Pembayaran masa lalu di bulan November (nomor kuitansi KW-202611-00010)
        PembayaranSpp::create([
            'id_tagihan' => $tagihanOkt->id,
            'id_siswa' => $this->siswa->id,
            'nomor_kuitansi' => 'KW-202611-00010',
            'tanggal_bayar' => Carbon::now('Asia/Jakarta')->subDays(2),
            'jumlah_bayar' => 20000,
            'metode_pembayaran' => 'Tunai',
            'id_petugas' => $this->admin->id,
            'status_transaksi' => 'valid',
        ]);

        // Pembayaran saat ini HANYA untuk November 2026 (KW-202611-00096)
        $pembayaranNov = PembayaranSpp::create([
            'id_tagihan' => $this->tagihan->id, // November 2026
            'id_siswa' => $this->siswa->id,
            'nomor_kuitansi' => 'KW-202611-00096',
            'tanggal_bayar' => Carbon::now('Asia/Jakarta'),
            'jumlah_bayar' => 20000,
            'metode_pembayaran' => 'Tunai',
            'id_petugas' => $this->admin->id,
            'status_transaksi' => 'valid',
        ]);

        $response = $this->get('/invoice/'.$pembayaranNov->invoice_token);

        $response->assertStatus(200);
        $response->assertSee('November 2026');
        // Pastikan TIDAK mencantumkan Oktober 2026 yang merupakan transaksi kuitansi lain
        $response->assertDontSee('Oktober 2026');
        // Pastikan total terbayar adalah Rp 20.000, BUKAN akumulasi Rp 40.000
        $response->assertSee('20.000');
        $response->assertDontSee('40.000');
    }

    public function test_multi_month_payment_sorts_months_chronologically_not_alphabetically(): void
    {
        $siswaMulti = Siswa::create([
            'nisn' => '3099',
            'nama_lengkap' => 'Naziha Najwa Haniyah',
            'jenis_kelamin' => 'P',
            'id_kelas' => $this->kelas->id,
            'nama_ortu' => 'Wali Naziha',
            'no_hp_ortu' => '085812345514',
            'status' => 'aktif',
        ]);

        // Simulasi 12 bulan pembayaran: Juli 2026 s/d Juni 2027
        $months = [
            ['bulan' => 7, 'tahun' => 2026],
            ['bulan' => 8, 'tahun' => 2026],
            ['bulan' => 9, 'tahun' => 2026],
            ['bulan' => 10, 'tahun' => 2026],
            ['bulan' => 11, 'tahun' => 2026],
            ['bulan' => 12, 'tahun' => 2026],
            ['bulan' => 1, 'tahun' => 2027],
            ['bulan' => 2, 'tahun' => 2027],
            ['bulan' => 3, 'tahun' => 2027],
            ['bulan' => 4, 'tahun' => 2027],
            ['bulan' => 5, 'tahun' => 2027],
            ['bulan' => 6, 'tahun' => 2027],
        ];

        $baseKuitansi = 'KW-202611-00104';
        $pembayaran12 = null;

        foreach ($months as $idx => $m) {
            $tagihanItem = TagihanSpp::create([
                'id_siswa' => $siswaMulti->id,
                'bulan' => $m['bulan'],
                'tahun' => $m['tahun'],
                'tahun_ajaran' => '2026/2027',
                'nominal_tagihan' => 15000,
                'status' => 'lunas',
            ]);

            $p = PembayaranSpp::create([
                'id_tagihan' => $tagihanItem->id,
                'id_siswa' => $siswaMulti->id,
                'nomor_kuitansi' => "{$baseKuitansi}-".($idx + 1),
                'tanggal_bayar' => Carbon::now('Asia/Jakarta'),
                'jumlah_bayar' => 15000,
                'metode_pembayaran' => 'Tunai',
                'id_petugas' => $this->admin->id,
                'status_transaksi' => 'valid',
            ]);

            if ($idx === 11) {
                $pembayaran12 = $p; // Item ke-12: KW-202611-00104-12
            }
        }

        // Buka halaman kwitansi publik lewat nomor sub-kuitansi ke-12 (otomatis mengikuti alihan ke token aman)
        $response = $this->followingRedirects()->get('/invoice/KW-202611-00104-12');

        $response->assertStatus(200);
        // Pastikan nomor kuitansi yang ditampilkan adalah nomor induk kuitansi
        $response->assertSee('KW-202611-00104');
        // Total 12 x 15.000 = 180.000
        $response->assertSee('180.000');

        $content = $response->getContent();

        // Pastikan posisi Juli 2026 sebelum Agustus 2026
        $posJuli = strpos($content, 'Juli 2026');
        $posAgustus = strpos($content, 'Agustus 2026');
        $posDesember = strpos($content, 'Desember 2026');
        $posJanuari = strpos($content, 'Januari 2027');
        $posApril = strpos($content, 'April 2027');
        $posJuni = strpos($content, 'Juni 2027');

        $this->assertNotFalse($posJuli);
        $this->assertNotFalse($posAgustus);
        $this->assertNotFalse($posDesember);
        $this->assertNotFalse($posJanuari);
        $this->assertNotFalse($posApril);
        $this->assertNotFalse($posJuni);

        // Verifikasi urutan kronologis: Juli < Agustus < Desember < Januari < April < Juni
        $this->assertTrue($posJuli < $posAgustus, 'Juli 2026 harus sebelum Agustus 2026');
        $this->assertTrue($posAgustus < $posDesember, 'Agustus 2026 harus sebelum Desember 2026');
        $this->assertTrue($posDesember < $posJanuari, 'Desember 2026 harus sebelum Januari 2027');
        $this->assertTrue($posJanuari < $posApril, 'Januari 2027 harus sebelum April 2027');
        $this->assertTrue($posApril < $posJuni, 'April 2027 harus sebelum Juni 2027');
    }
}
