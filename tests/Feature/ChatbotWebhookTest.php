<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Agenda;
use App\Models\HariLibur;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChatbotWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected Kelas $kelas;

    protected Siswa $siswa1;

    protected Siswa $siswa2;

    protected User $adminUser;

    protected User $kepalaUser;

    protected User $guruUser;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->kelas = Kelas::create([
            'nama_kelas' => 'JILID 1',
            'warna_label' => '#f44336',
        ]);

        $this->siswa1 = Siswa::create([
            'nisn' => '1001',
            'nama_lengkap' => 'Ahmad Fauzi',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
            'no_hp_ortu' => '081234567890',
            'nama_ortu' => 'Bapak Fauzi',
            'status' => 'aktif',
        ]);

        $this->siswa2 = Siswa::create([
            'nisn' => '1002',
            'nama_lengkap' => 'Fatimah Az-Zahra',
            'jenis_kelamin' => 'P',
            'id_kelas' => $this->kelas->id,
            'no_hp_ortu' => '081234567890', // Nomor sama (Kakak-Adik)
            'nama_ortu' => 'Bapak Fauzi',
            'status' => 'aktif',
        ]);

        $this->adminUser = User::factory()->create([
            'username' => 'admin_test',
            'role' => 'admin',
        ]);

        $this->kepalaUser = User::factory()->create([
            'username' => 'kepala_test',
            'role' => 'kepala',
        ]);

        $this->guruUser = User::factory()->create([
            'username' => 'guru_test',
            'role' => 'guru',
        ]);
    }

    public function test_webhook_requires_sender(): void
    {
        $response = $this->postJson('/api/webhook/whatsapp', [
            'message' => 'Halo',
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_webhook_responds_spp_notice_for_registered_student(): void
    {
        // Berikan nomor unik khusus siswa1 untuk tes single student
        $this->siswa2->update(['no_hp_ortu' => '089999999999']);

        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'Assalamu\'alaikum, berapa tunggakan anak saya?',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'spp_notice',
            ]);

        $reply = $response->json('reply');
        $this->assertStringContainsString('Ahmad Fauzi', $reply);
        $this->assertStringContainsString('Presensi / Kehadiran Santri Berbasis QR Code', $reply);
        $this->assertStringContainsString('administrasi, SPP, atau keuangan', $reply);
    }

    public function test_webhook_responds_spp_notice_directing_to_administration(): void
    {
        $this->siswa2->update(['no_hp_ortu' => '089999999999']);

        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'Cek tagihan spp anak saya',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'spp_notice',
            ]);

        $reply = $response->json('reply');
        $this->assertStringContainsString('administrasi', $reply);
    }

    public function test_webhook_responds_attendance_today_and_monthly_recap(): void
    {
        $this->siswa2->update(['no_hp_ortu' => '089999999999']);

        Absensi::create([
            'id_siswa' => $this->siswa1->id,
            'tanggal' => Carbon::today()->toDateString(),
            'waktu_masuk' => '15:30:00',
            'status' => 'Hadir',
            'metode_scan' => 'qr_kamera',
        ]);

        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'Gimana presensi dan kehadiran Ahmad hari ini?',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'absensi',
            ]);

        $reply = $response->json('reply');
        $this->assertStringContainsString('Ahmad Fauzi', $reply);
        $this->assertStringContainsString('HADIR TEPAT WAKTU', $reply);
        $this->assertStringContainsString('15:30:00', $reply);
    }

    public function test_webhook_responds_agenda_and_holiday_info(): void
    {
        HariLibur::create([
            'tanggal_mulai' => Carbon::tomorrow()->toDateString(),
            'tanggal_selesai' => Carbon::tomorrow()->toDateString(),
            'keterangan' => 'Libur Isra Miraj',
        ]);

        Agenda::create([
            'tanggal' => Carbon::today()->addDays(3)->toDateString(),
            'judul' => 'Ujian Tahfidz Bersama',
            'keterangan' => 'Seluruh santri hadir pukul 08.00 WIB',
        ]);

        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'Kapan libur dan ada agenda apa minggu ini?',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'agenda',
            ]);

        $reply = $response->json('reply');
        $this->assertStringContainsString('Libur Isra Miraj', $reply);
        $this->assertStringContainsString('Ujian Tahfidz Bersama', $reply);
    }

    public function test_webhook_handles_unregistered_phone(): void
    {
        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '628999111222',
            'message' => 'Halo assalamu\'alaikum ustadz',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'unregistered',
            ]);

        $reply = $response->json('reply');
        $this->assertStringContainsString('belum terdaftar', $reply);
        $this->assertStringContainsString('NISN', $reply);
    }

    public function test_webhook_handles_direct_nisn_in_message(): void
    {
        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '628999111222',
            'message' => 'NISN 1001 cek kehadiran',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'absensi',
            ]);

        $reply = $response->json('reply');
        $this->assertStringContainsString('Ahmad Fauzi', $reply);
    }

    public function test_webhook_handles_multi_student_selection(): void
    {
        // Nomor 081234567890 memiliki 2 anak: Ahmad Fauzi & Fatimah Az-Zahra
        $response = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'Halo ustadz',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'multi_santri',
            ]);

        $reply = $response->json('reply');
        $this->assertStringContainsString('Ahmad Fauzi', $reply);
        $this->assertStringContainsString('Fatimah Az-Zahra', $reply);
        $this->assertStringContainsString('*1*', $reply);

        // Pilih anak ke-2 (Fatimah)
        $responseSelection = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => '2',
        ]);

        $responseSelection->assertStatus(200);
        $this->assertStringContainsString('Fatimah Az-Zahra', $responseSelection->json('reply'));

        // Pertanyaan berikutnya otomatis untuk Fatimah
        $responseNext = $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'Cek absensi anak saya',
        ]);

        $responseNext->assertStatus(200)
            ->assertJson(['intent' => 'absensi']);
        $this->assertStringContainsString('Fatimah Az-Zahra', $responseNext->json('reply'));
    }

    public function test_chatbot_logs_recorded_in_database(): void
    {
        $this->siswa2->update(['no_hp_ortu' => '089999999999']);

        $this->postJson('/api/webhook/whatsapp', [
            'sender' => '6281234567890',
            'message' => 'Cek kehadiran anak saya',
        ]);

        $this->assertDatabaseHas('chatbot_logs', [
            'no_pengirim' => '6281234567890',
            'intent_terdeteksi' => 'absensi',
            'id_siswa' => $this->siswa1->id,
            'status' => 'sukses',
        ]);
    }

    public function test_admin_can_access_simulator_and_logs(): void
    {
        // Admin test
        $responseAdmin = $this->actingAs($this->adminUser)
            ->postJson('/api/chatbot/simulate', [
                'phone' => '081234567890',
                'message' => 'Cek kehadiran',
            ]);

        $responseAdmin->assertStatus(200)
            ->assertJson(['success' => true]);

        $responseLogs = $this->actingAs($this->adminUser)
            ->getJson('/api/chatbot/logs');

        $responseLogs->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_other_roles_cannot_access_chatbot_admin_routes(): void
    {
        // Kepala TPQ should not access chatbot
        $responseKepala = $this->actingAs($this->kepalaUser)
            ->getJson('/api/chatbot/logs');
        $responseKepala->assertStatus(403);

        // Guru should not access chatbot
        $responseGuru = $this->actingAs($this->guruUser)
            ->getJson('/api/chatbot/logs');
        $responseGuru->assertStatus(403);
    }
}
