<?php

namespace Tests\Feature;

use App\Models\HariLibur;
use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RekapMatrixLiburTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Kelas $kelas;

    protected Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin TPQ',
            'username' => 'admin_matrix_test',
            'email' => 'admin_matrix@tpq.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $this->kelas = Kelas::create([
            'nama_kelas' => 'JILID 1',
            'urutan' => 1,
            'warna_label' => '#4CAF50',
        ]);

        $this->siswa = Siswa::create([
            'nisn' => '7701',
            'nama_lengkap' => 'Ahmad Santri',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
            'status' => 'aktif',
        ]);

        // Tetapkan hari aktif belajar: Senin sampai Jumat (Sabtu & Minggu libur rutinan)
        Pengaturan::setValue('hari_aktif', 'Senin,Selasa,Rabu,Kamis,Jumat');
    }

    public function test_rekap_matrix_displays_l_for_libur_rutinan_and_hari_libur_kalender(): void
    {
        // November 2026:
        // 1 Nov 2026 = Minggu (Libur Rutinan)
        // 7 Nov 2026 = Sabtu (Libur Rutinan)
        // 8 Nov 2026 = Minggu (Libur Rutinan)
        // 2 Nov 2026 = Senin (Hari aktif)
        // Buat Hari Libur kalender pada tanggal 4 s.d. 5 November 2026 (Rabu - Kamis)
        HariLibur::create([
            'tanggal_mulai' => '2026-11-04',
            'tanggal_selesai' => '2026-11-05',
            'keterangan' => 'Libur Evaluasi Pengajar',
        ]);

        // Rekam presensi Hadir pada hari aktif 2 November 2026 (Senin)
        $this->siswa->absensi()->create([
            'tanggal' => '2026-11-02',
            'status' => 'Hadir',
            'waktu_masuk' => '07:05:00',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/absensi/matrix?bulan=11&tahun=2026');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $daily = $response->json('data.data.0.daily');
        $holidayDays = $response->json('data.holiday_days');

        // 1 Nov (Minggu - libur rutinan) harus berstatus 'L'
        $this->assertEquals('L', $daily[1]);
        $this->assertContains(1, $holidayDays);

        // 2 Nov (Senin - hadir) harus berstatus 'H'
        $this->assertEquals('H', $daily[2]);

        // 3 Nov (Selasa - aktif tapi tidak ada absen) harus berstatus '-'
        $this->assertEquals('-', $daily[3]);

        // 4 Nov & 5 Nov (Rabu & Kamis - Libur Kalender) harus berstatus 'L'
        $this->assertEquals('L', $daily[4]);
        $this->assertEquals('L', $daily[5]);
        $this->assertContains(4, $holidayDays);
        $this->assertContains(5, $holidayDays);

        // 7 & 8 Nov (Sabtu & Minggu - libur rutinan) harus berstatus 'L'
        $this->assertEquals('L', $daily[7]);
        $this->assertEquals('L', $daily[8]);
        $this->assertContains(7, $holidayDays);
        $this->assertContains(8, $holidayDays);
    }
}
