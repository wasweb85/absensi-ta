<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\WhatsAppLog;
use App\Services\ChatbotService;
use App\Services\CheckAttendance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckAttendanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Kelas $kelas;

    protected Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kelas = Kelas::create([
            'nama_kelas' => 'JILID 2',
            'urutan' => 2,
        ]);

        $this->siswa = Siswa::create([
            'nisn' => '2055',
            'nama_lengkap' => 'Rizky Pratama',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
            'nama_ortu' => 'Ibu Rizky',
            'no_hp_ortu' => '6281577778888',
        ]);
    }

    public function test_scenario_1_and_2_check_today_presence_and_arrival_time(): void
    {
        $today = Carbon::today('Asia/Jakarta')->toDateString();

        Absensi::create([
            'id_siswa' => $this->siswa->id,
            'tanggal' => $today,
            'waktu_masuk' => '15:18:00',
            'status' => 'Hadir',
        ]);

        $chatbot = app(ChatbotService::class);

        $res1 = $chatbot->handleIncomingMessage('081577778888', 'Apakah anak saya sudah hadir hari ini?');
        $this->assertTrue($res1['success']);
        $this->assertSame('absensi', $res1['intent']);
        $this->assertStringContainsString('Rizky Pratama', $res1['reply']);
        $this->assertStringContainsString('HADIR TEPAT WAKTU', $res1['reply']);
        $this->assertStringContainsString('15:18:00 WIB', $res1['reply']);

        $res2 = $chatbot->handleIncomingMessage('081577778888', 'Anak saya tadi masuk jam berapa?');
        $this->assertTrue($res2['success']);
        $this->assertSame('absensi', $res2['intent']);
        $this->assertStringContainsString('15:18:00 WIB', $res2['reply']);
    }

    public function test_scenario_3_why_not_receiving_attendance_notification(): void
    {
        $chatbot = app(ChatbotService::class);

        // Kondisi A: Anak belum scan kartu QR hari ini
        $resBeforeScan = $chatbot->handleIncomingMessage('081577778888', 'Kenapa saya belum menerima notifikasi absensi?');
        $this->assertTrue($resBeforeScan['success']);
        $this->assertSame('absensi', $resBeforeScan['intent']);
        $this->assertStringContainsString('Info Notifikasi WA:', $resBeforeScan['reply']);
        $this->assertStringContainsString('belum melakukan scan QR Code', $resBeforeScan['reply']);

        // Kondisi B: Anak sudah scan QR namun pesan WA masih pending di antrean gateway
        $today = Carbon::today('Asia/Jakarta')->toDateString();
        Absensi::create([
            'id_siswa' => $this->siswa->id,
            'tanggal' => $today,
            'waktu_masuk' => '15:22:00',
            'status' => 'Hadir',
        ]);

        WhatsAppLog::create([
            'nomor_tujuan' => '6281577778888',
            'nama_penerima' => 'Ibu Rizky',
            'pesan' => 'Notifikasi presensi Rizky',
            'tipe_pesan' => 'presensi',
            'status' => 'pending',
        ]);

        $resAfterScan = $chatbot->handleIncomingMessage('081577778888', 'Kenapa saya belum dapat notifikasi absensi hari ini?');
        $this->assertTrue($resAfterScan['success']);
        $this->assertStringContainsString('15:22:00 WIB', $resAfterScan['reply']);
        $this->assertStringContainsString('antrean pengiriman', $resAfterScan['reply']);
    }

    public function test_scenario_4_was_child_on_permission_yesterday(): void
    {
        $yesterday = Carbon::yesterday('Asia/Jakarta')->toDateString();

        Absensi::create([
            'id_siswa' => $this->siswa->id,
            'tanggal' => $yesterday,
            'waktu_masuk' => null,
            'status' => 'Izin',
            'keterangan' => 'Acara keluarga',
        ]);

        $chatbot = app(ChatbotService::class);
        $res = $chatbot->handleIncomingMessage('081577778888', 'Apakah anak saya izin kemarin?');

        $this->assertTrue($res['success']);
        $this->assertSame('absensi', $res['intent']);
        $this->assertStringContainsString('Kemarin', $res['reply']);
        $this->assertStringContainsString('*IZIN*', $res['reply']);
    }

    public function test_scenario_5_how_many_times_child_was_absent_this_month(): void
    {
        $now = Carbon::now('Asia/Jakarta');
        $start = $now->copy()->startOfMonth();

        // 4 Hadir, 1 Izin, 1 Sakit, 1 Alpa -> Total Tidak Masuk = 3 kali
        for ($i = 0; $i < 4; $i++) {
            Absensi::create([
                'id_siswa' => $this->siswa->id,
                'tanggal' => $start->copy()->addDays($i)->toDateString(),
                'waktu_masuk' => '15:15:00',
                'status' => 'Hadir',
            ]);
        }

        Absensi::create([
            'id_siswa' => $this->siswa->id,
            'tanggal' => $start->copy()->addDays(4)->toDateString(),
            'status' => 'Izin',
        ]);
        Absensi::create([
            'id_siswa' => $this->siswa->id,
            'tanggal' => $start->copy()->addDays(5)->toDateString(),
            'status' => 'Sakit',
        ]);
        Absensi::create([
            'id_siswa' => $this->siswa->id,
            'tanggal' => $start->copy()->addDays(6)->toDateString(),
            'status' => 'Alpa',
        ]);

        $chatbot = app(ChatbotService::class);
        $res = $chatbot->handleIncomingMessage('081577778888', 'Berapa kali anak saya tidak masuk bulan ini?');

        $this->assertTrue($res['success']);
        $this->assertSame('absensi', $res['intent']);
        $this->assertStringContainsString('Total Tidak Masuk: *3 kali* (Izin: 1, Sakit: 1, Alpa: 1)', $res['reply']);

        // Pastikan struktur data mentah CheckAttendance juga akurat untuk AI Function Calling
        $checkAttendance = app(CheckAttendance::class);
        $period = $chatbot->parseTargetPeriod('bulan ini');
        $inspected = $checkAttendance->inspectStudentAttendance($this->siswa, $period, 'Berapa kali anak saya tidak masuk bulan ini?');

        $this->assertSame(4, $inspected['rekap_bulanan']['total_masuk']);
        $this->assertSame(3, $inspected['rekap_bulanan']['total_tidak_masuk']);
    }
}
