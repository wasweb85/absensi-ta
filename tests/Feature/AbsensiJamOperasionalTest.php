<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AbsensiJamOperasionalTest extends TestCase
{
    use RefreshDatabase;

    protected User $guru;

    protected Siswa $siswa;

    protected Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kelas = Kelas::create([
            'nama_kelas' => 'JILID 1',
            'urutan' => 1,
            'warna_label' => '#4CAF50',
        ]);

        $this->siswa = Siswa::create([
            'nisn' => '9901',
            'nama_lengkap' => 'Muhammad Zaki',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
            'status' => 'aktif',
            'no_hp_ortu' => '081234567890',
        ]);

        $this->guru = User::create([
            'name' => 'Ustadz Ahmad',
            'username' => 'ustadz_ahmad',
            'email' => 'ahmad@tpq.test',
            'password' => Hash::make('password123'),
            'role' => 'guru',
            'id_kelas' => $this->kelas->id,
        ]);

        Pengaturan::setValue('jam_masuk', '07:00');
        Pengaturan::setValue('jam_terlambat', '07:15');
        Pengaturan::setValue('jam_pulang', '12:00');
        Pengaturan::setValue('wa_notif_presensi', '1');
        Pengaturan::setValue('fonnte_token', 'mock_fonnte_token_123');
    }

    public function test_config_endpoint_returns_jam_tutup_scan_and_jam_pulang(): void
    {
        $response = $this->getJson('/api/absensi/config');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'jam_masuk' => '07:00',
                'jam_terlambat' => '07:15',
                'jam_pulang' => '12:00',
                'jam_tutup_scan' => '12:00',
            ]);
    }

    public function test_manual_status_update_within_scan_window_sends_wa_notification(): void
    {
        // Freeze time at 08:00 WIB (within 07:00 - 12:00)
        Carbon::setTestNow(Carbon::parse('2026-11-05 08:00:00', 'Asia/Jakarta'));

        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'id' => ['msg_test_001'],
                'target' => ['6281234567890'],
            ], 200),
        ]);

        $response = $this->actingAs($this->guru, 'sanctum')->postJson('/api/absensi/status', [
            'id_siswa' => $this->siswa->id,
            'tanggal' => '2026-11-05',
            'status' => 'Hadir',
            'keterangan' => 'Hadir manual di jam operasional',
        ]);

        $this->app->terminate();

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Status absensi berhasil diperbarui',
            ]);

        $this->assertDatabaseHas('absensi', [
            'id_siswa' => $this->siswa->id,
            'tanggal' => '2026-11-05',
            'status' => 'Hadir',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.fonnte.com/send');
        });

        Carbon::setTestNow();
    }

    public function test_manual_status_update_after_jam_tutup_scan_updates_status_without_sending_wa(): void
    {
        // Freeze time at 14:30 WIB (after closing time 12:00)
        Carbon::setTestNow(Carbon::parse('2026-11-05 14:30:00', 'Asia/Jakarta'));

        Http::fake();

        $response = $this->actingAs($this->guru, 'sanctum')->postJson('/api/absensi/status', [
            'id_siswa' => $this->siswa->id,
            'tanggal' => '2026-11-05',
            'status' => 'Hadir',
            'keterangan' => 'Ustadz input setelah jam tutup scan',
        ]);

        $this->app->terminate();

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertStringContainsString('notifikasi WA tidak dikirim', $response->json('message'));

        $this->assertDatabaseHas('absensi', [
            'id_siswa' => $this->siswa->id,
            'tanggal' => '2026-11-05',
            'status' => 'Hadir',
        ]);

        // Assert no WhatsApp was sent
        Http::assertNothingSent();

        // Check ActivityLog note
        $log = ActivityLog::where('modul', 'presensi')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Di luar rentang buka-tutup scan: notif WA tidak dikirim', $log->deskripsi);

        Carbon::setTestNow();
    }

    public function test_manual_status_update_before_jam_masuk_updates_status_without_sending_wa(): void
    {
        // Freeze time at 05:30 WIB (before buka scan 07:00)
        Carbon::setTestNow(Carbon::parse('2026-11-05 05:30:00', 'Asia/Jakarta'));

        Http::fake();

        $response = $this->actingAs($this->guru, 'sanctum')->postJson('/api/absensi/status', [
            'id_siswa' => $this->siswa->id,
            'tanggal' => '2026-11-05',
            'status' => 'Hadir',
            'keterangan' => 'Input subuh',
        ]);

        $this->app->terminate();

        $response->assertStatus(200);
        $this->assertStringContainsString('notifikasi WA tidak dikirim', $response->json('message'));

        $this->assertDatabaseHas('absensi', [
            'id_siswa' => $this->siswa->id,
            'tanggal' => '2026-11-05',
            'status' => 'Hadir',
        ]);

        Http::assertNothingSent();

        Carbon::setTestNow();
    }

    public function test_scanner_rejects_scan_after_jam_tutup_scan(): void
    {
        config(['app.enforce_scan_window' => true]);
        Carbon::setTestNow(Carbon::parse('2026-11-05 13:00:00', 'Asia/Jakarta'));

        $response = $this->postJson('/api/absensi/scan', [
            'nisn' => '9901',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString('Waktu scan presensi hari ini sudah ditutup', $response->json('message'));
        $this->assertStringContainsString('Presensi hanya dapat diinput manual oleh ustadz/ustadzah', $response->json('message'));

        Carbon::setTestNow();
    }

    public function test_scanner_accepts_scan_during_operational_hours(): void
    {
        config(['app.enforce_scan_window' => true]);
        Carbon::setTestNow(Carbon::parse('2026-11-05 07:10:00', 'Asia/Jakarta'));

        $response = $this->postJson('/api/absensi/scan', [
            'nisn' => '9901',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'Hadir',
            ]);

        $this->assertDatabaseHas('absensi', [
            'id_siswa' => $this->siswa->id,
            'tanggal' => '2026-11-05',
            'status' => 'Hadir',
        ]);

        Carbon::setTestNow();
    }
}
