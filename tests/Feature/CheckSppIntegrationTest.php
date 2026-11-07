<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\PembayaranSpp;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\TagihanSpp;
use App\Services\ChatbotService;
use App\Services\CheckSppArrears;
use App\Services\CheckSppPayment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckSppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Kelas $kelas;

    protected Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        Pengaturan::setValue('no_hp_bendahara', '085235567818');

        $this->kelas = Kelas::create([
            'nama_kelas' => 'JILID 3',
            'urutan' => 3,
        ]);

        $this->siswa = Siswa::create([
            'nisn' => '3099',
            'nama_lengkap' => 'Zaidan Alfarizi',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
            'nama_ortu' => 'Bapak Zaidan',
            'no_hp_ortu' => '6281799990000',
        ]);
    }

    public function test_check_spp_payment_scenarios_last_payment_and_transfer_confirmation(): void
    {
        $now = Carbon::now('Asia/Jakarta');
        $prevMonth = $now->copy()->subMonth();

        // Tagihan bulan lalu (Lunas) + transaksi pembayaran_spp
        $tagihanLalu = TagihanSpp::create([
            'id_siswa' => $this->siswa->id,
            'bulan' => (int) $prevMonth->month,
            'tahun' => (int) $prevMonth->year,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 50000,
            'status' => 'lunas',
        ]);

        PembayaranSpp::create([
            'id_tagihan' => $tagihanLalu->id,
            'id_siswa' => $this->siswa->id,
            'nomor_kuitansi' => 'KW-202610-0088',
            'tanggal_bayar' => Carbon::create((int) $prevMonth->year, (int) $prevMonth->month, 10, 14, 30, 0, 'Asia/Jakarta'),
            'jumlah_bayar' => 50000,
            'metode_pembayaran' => 'Transfer',
            'status_transaksi' => 'valid',
        ]);

        // Tagihan bulan ini (Belum Lunas)
        TagihanSpp::create([
            'id_siswa' => $this->siswa->id,
            'bulan' => (int) $now->month,
            'tahun' => (int) $now->year,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 50000,
            'status' => 'belum_lunas',
        ]);

        $chatbot = app(ChatbotService::class);

        // Skenario 1: Apakah SPP bulan ini sudah dibayar?
        $res1 = $chatbot->handleIncomingMessage('081799990000', 'Apakah SPP bulan ini sudah dibayar?');
        $this->assertTrue($res1['success']);
        $this->assertSame('spp_notice', $res1['intent']);
        $this->assertStringContainsString('Zaidan Alfarizi', $res1['reply']);
        $this->assertStringContainsString('Menunggak 1 bulan', $res1['reply']);

        // Skenario 2: Pembayaran terakhir anak saya kapan?
        $res2 = $chatbot->handleIncomingMessage('081799990000', 'Pembayaran terakhir anak saya kapan?');
        $this->assertTrue($res2['success']);
        $this->assertSame('spp_notice', $res2['intent']);
        $this->assertStringContainsString('Pembayaran Terakhir Tercatat:', $res2['reply']);
        $this->assertStringContainsString('KW-202610-0088', $res2['reply']);
        $this->assertStringContainsString('Rp 50.000', $res2['reply']);

        // Skenario 3: Apakah pembayaran saya sudah tercatat?
        $res3 = $chatbot->handleIncomingMessage('081799990000', 'Apakah pembayaran SPP saya sudah tercatat?');
        $this->assertTrue($res3['success']);
        $this->assertStringContainsString('Pembayaran Terakhir Tercatat:', $res3['reply']);
        $this->assertStringContainsString('KW-202610-0088', $res3['reply']);

        // Skenario 4: Saya sudah transfer tetapi belum ada konfirmasi
        $res4 = $chatbot->handleIncomingMessage('081799990000', 'Saya sudah transfer tetapi belum ada konfirmasi');
        $this->assertTrue($res4['success']);
        $this->assertSame('spp_notice', $res4['intent']);
        $this->assertStringContainsString('bukti transfer', $res4['reply']);
        $this->assertStringContainsString('085235567818', $res4['reply']);
    }

    public function test_check_spp_arrears_scenarios_total_months_and_how_to_pay(): void
    {
        $now = Carbon::now('Asia/Jakarta');
        $prevMonth = $now->copy()->subMonth();

        TagihanSpp::create([
            'id_siswa' => $this->siswa->id,
            'bulan' => (int) $prevMonth->month,
            'tahun' => (int) $prevMonth->year,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 50000,
            'status' => 'belum_lunas',
        ]);

        TagihanSpp::create([
            'id_siswa' => $this->siswa->id,
            'bulan' => (int) $now->month,
            'tahun' => (int) $now->year,
            'tahun_ajaran' => '2026/2027',
            'nominal_tagihan' => 50000,
            'status' => 'belum_lunas',
        ]);

        $chatbot = app(ChatbotService::class);

        // Skenario 1, 2 & 3: Apakah masih punya tunggakan, berapa total, dan bulan apa saja yang belum dibayar?
        $resArrears = $chatbot->handleIncomingMessage('081799990000', 'Apakah anak saya masih mempunyai tunggakan? Bulan apa saja yang belum dibayar?');
        $this->assertTrue($resArrears['success']);
        $this->assertSame('spp_notice', $resArrears['intent']);
        $this->assertStringContainsString('Menunggak 2 bulan', $resArrears['reply']);
        $this->assertStringContainsString('Rp 100.000', $resArrears['reply']);
        $this->assertStringContainsString('Rincian Bulan Belum Lunas:', $resArrears['reply']);

        // Skenario 4: Bagaimana cara melunasi tunggakan?
        $resGuide = $chatbot->handleIncomingMessage('081799990000', 'Bagaimana cara melunasi SPP?');
        $this->assertTrue($resGuide['success']);
        $this->assertSame('spp_notice', $resGuide['intent']);
        $this->assertStringContainsString('Bendahara', $resGuide['reply']);
        $this->assertStringContainsString('085235567818', $resGuide['reply']);

        // Verifikasi struktur payload tool CheckSppPayment & CheckSppArrears untuk AI Function Calling
        $paymentTool = app(CheckSppPayment::class);
        $arrearsTool = app(CheckSppArrears::class);
        $period = $chatbot->parseTargetPeriod('bulan ini');

        $arrearsPayload = $arrearsTool->buildToolPayload($this->siswa, collect([$this->siswa]), $period, 'cek tunggakan');
        $this->assertTrue($arrearsPayload['has_tunggakan']);
        $this->assertSame(2, $arrearsPayload['total_bulan_tunggakan']);
        $this->assertEquals(100000.0, $arrearsPayload['total_nominal_tunggakan']);

        $paymentPayload = $paymentTool->buildToolPayload($this->siswa, collect([$this->siswa]), $period, 'cek pembayaran');
        $this->assertSame('belum_lunas', $paymentPayload['status_bulan_target']['status']);
    }
}
