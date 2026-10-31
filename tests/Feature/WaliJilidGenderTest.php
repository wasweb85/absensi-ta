<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaliJilidGenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_gender_binaan_attribute_default_all(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'gender_binaan' => 'all',
        ]);
    }

    public function test_can_assign_two_teachers_to_same_class_with_different_gender_binaan(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = Kelas::create(['nama_kelas' => 'JILID 3', 'warna_label' => '#2563eb']);

        // 1. Assign Ustadz (Putra)
        $res1 = $this->actingAs($admin, 'sanctum')->postJson('/api/users', [
            'name' => 'Ustadz Fairus',
            'username' => 'fairus',
            'password' => 'secret123',
            'role' => 'guru',
            'id_kelas' => $kelas->id,
            'gender_binaan' => 'L',
        ]);
        $res1->assertStatus(201);

        // 2. Assign Ustadzah (Putri) to the same class
        $res2 = $this->actingAs($admin, 'sanctum')->postJson('/api/users', [
            'name' => 'Ustadzah Siti',
            'username' => 'siti',
            'password' => 'secret123',
            'role' => 'guru',
            'id_kelas' => $kelas->id,
            'gender_binaan' => 'P',
        ]);
        $res2->assertStatus(201);

        $kelas->refresh();
        $this->assertEquals('Ustadz Fairus (Putra) & Ustadzah Siti (Putri)', $kelas->wali_kelas);

        // 3. Reject second Ustadz with same gender on the same class
        $res3 = $this->actingAs($admin, 'sanctum')->postJson('/api/users', [
            'name' => 'Ustadz Ali',
            'username' => 'ali',
            'password' => 'secret123',
            'role' => 'guru',
            'id_kelas' => $kelas->id,
            'gender_binaan' => 'L',
        ]);
        $res3->assertStatus(422);
        $res3->assertJsonValidationErrors(['id_kelas']);
    }

    public function test_absensi_monitoring_filters_by_gender(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = Kelas::create(['nama_kelas' => 'JILID 3', 'warna_label' => '#2563eb']);

        $santriPutra = Siswa::create([
            'nisn' => '1001',
            'nama_lengkap' => 'Ahmad Santri',
            'jenis_kelamin' => 'L',
            'id_kelas' => $kelas->id,
            'status' => 'aktif',
        ]);

        $santriPutri = Siswa::create([
            'nisn' => '1002',
            'nama_lengkap' => 'Fatimah Santri',
            'jenis_kelamin' => 'P',
            'id_kelas' => $kelas->id,
            'status' => 'aktif',
        ]);

        // Filter explicitly with jenis_kelamin=L
        $resL = $this->actingAs($admin, 'sanctum')->getJson("/api/absensi/monitoring?id_kelas={$kelas->id}&jenis_kelamin=L");
        $resL->assertStatus(200);
        $studentsL = collect($resL->json('data.students'));
        $this->assertTrue($studentsL->contains('id_siswa', $santriPutra->id));
        $this->assertFalse($studentsL->contains('id_siswa', $santriPutri->id));

        // Filter by teacher's default gender_binaan when logged in as guru Putri
        $guruPutri = User::factory()->create([
            'role' => 'guru',
            'id_kelas' => $kelas->id,
            'gender_binaan' => 'P',
        ]);

        $resGuru = $this->actingAs($guruPutri, 'sanctum')->getJson("/api/absensi/monitoring?id_kelas={$kelas->id}");
        $resGuru->assertStatus(200);
        $studentsGuru = collect($resGuru->json('data.students'));
        $this->assertFalse($studentsGuru->contains('id_siswa', $santriPutra->id));
        $this->assertTrue($studentsGuru->contains('id_siswa', $santriPutri->id));
    }

    public function test_absensi_matrix_filters_by_gender(): void
    {
        $admin = User::factory()->admin()->create();
        $kelas = Kelas::create(['nama_kelas' => 'JILID 3', 'warna_label' => '#2563eb']);

        User::factory()->create([
            'name' => 'Ustadz Fairus',
            'role' => 'guru',
            'id_kelas' => $kelas->id,
            'gender_binaan' => 'L',
        ]);

        User::factory()->create([
            'name' => 'Ustadzah Siti',
            'role' => 'guru',
            'id_kelas' => $kelas->id,
            'gender_binaan' => 'P',
        ]);

        $santriPutra = Siswa::create([
            'nisn' => '2001',
            'nama_lengkap' => 'Budi Santri',
            'jenis_kelamin' => 'L',
            'id_kelas' => $kelas->id,
            'status' => 'aktif',
        ]);

        $santriPutri = Siswa::create([
            'nisn' => '2002',
            'nama_lengkap' => 'Aisyah Santri',
            'jenis_kelamin' => 'P',
            'id_kelas' => $kelas->id,
            'status' => 'aktif',
        ]);

        $res = $this->actingAs($admin, 'sanctum')->getJson("/api/absensi/matrix?id_kelas={$kelas->id}&jenis_kelamin=P&bulan=10&tahun=2026");
        $res->assertStatus(200);

        // Check wali_info
        $res->assertJsonPath('data.wali_info.wali_putra', 'Ustadz Fairus');
        $res->assertJsonPath('data.wali_info.wali_putri', 'Ustadzah Siti');
        $res->assertJsonPath('data.wali_info.wali_umum', null);

        // Check students data only contains Putri
        $matrixStudents = collect($res->json('data.data'));
        $this->assertTrue($matrixStudents->contains('id_siswa', $santriPutri->id));
        $this->assertFalse($matrixStudents->contains('id_siswa', $santriPutra->id));
    }
}
