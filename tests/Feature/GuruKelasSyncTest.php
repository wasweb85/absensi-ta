<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuruKelasSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Kelas $kelas1;

    protected Kelas $kelas2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Sistem',
            'username' => 'admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->kelas1 = Kelas::create([
            'nama_kelas' => 'JILID 1',
            'urutan' => 1,
            'warna_label' => '#f44336',
        ]);

        $this->kelas2 = Kelas::create([
            'nama_kelas' => 'JILID 2',
            'urutan' => 2,
            'warna_label' => '#2196f3',
        ]);
    }

    public function test_assign_guru_ke_kelas_otomatis_update_wali_kelas(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/users', [
            'name' => 'Ustadzah Halimah',
            'username' => 'halimah',
            'email' => 'halimah@test.com',
            'password' => 'password123',
            'role' => 'guru',
            'id_kelas' => $this->kelas1->id,
        ]);

        $response->assertStatus(201);

        $this->kelas1->refresh();
        $this->assertEquals('Ustadzah Halimah', $this->kelas1->wali_kelas);
    }

    public function test_pindah_kelas_guru_otomatis_bersihkan_kelas_lama(): void
    {
        $guru = User::create([
            'name' => 'Ustadz Mansur',
            'username' => 'mansur',
            'email' => 'mansur@test.com',
            'password' => Hash::make('password'),
            'role' => 'guru',
            'id_kelas' => $this->kelas1->id,
        ]);
        $this->kelas1->update(['wali_kelas' => 'Ustadz Mansur']);

        // Pindahkan ustadz mansur ke kelas2
        $response = $this->actingAs($this->admin, 'sanctum')->putJson('/api/users/'.$guru->id, [
            'name' => 'Ustadz Mansur',
            'username' => 'mansur',
            'email' => 'mansur@test.com',
            'role' => 'guru',
            'id_kelas' => $this->kelas2->id,
        ]);

        $response->assertStatus(200);

        $this->kelas1->refresh();
        $this->kelas2->refresh();

        $this->assertNull($this->kelas1->wali_kelas);
        $this->assertEquals('Ustadz Mansur', $this->kelas2->wali_kelas);
    }

    public function test_update_kelas_dengan_id_guru_otomatis_sinkron_user_dan_wali_kelas(): void
    {
        $guru = User::create([
            'name' => 'Ustadzah Maryam',
            'username' => 'maryam',
            'email' => 'maryam@test.com',
            'password' => Hash::make('password'),
            'role' => 'guru',
            'id_kelas' => null,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->putJson('/api/kelas/'.$this->kelas1->id, [
            'nama_kelas' => 'JILID 1',
            'id_guru' => $guru->id,
            'urutan' => 1,
        ]);

        $response->assertStatus(200);

        $this->kelas1->refresh();
        $guru->refresh();

        $this->assertEquals('Ustadzah Maryam', $this->kelas1->wali_kelas);
        $this->assertEquals($this->kelas1->id, $guru->id_kelas);
    }

    public function test_hapus_guru_otomatis_kosongkan_wali_kelas(): void
    {
        $guru = User::create([
            'name' => 'Ustadz Zaid',
            'username' => 'zaid',
            'email' => 'zaid@test.com',
            'password' => Hash::make('password'),
            'role' => 'guru',
            'id_kelas' => $this->kelas1->id,
        ]);
        $this->kelas1->update(['wali_kelas' => 'Ustadz Zaid']);

        $response = $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/users/'.$guru->id);
        $response->assertStatus(200);

        $this->kelas1->refresh();
        $this->assertNull($this->kelas1->wali_kelas);
    }
}
