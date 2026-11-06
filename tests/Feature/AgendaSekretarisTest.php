<?php

namespace Tests\Feature;

use App\Models\Agenda;
use App\Models\HariLibur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgendaSekretarisTest extends TestCase
{
    use RefreshDatabase;

    protected User $sekretaris;

    protected User $admin;

    protected User $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekretaris = User::create([
            'name' => 'Sekretaris TPQ',
            'username' => 'sekretaris_test',
            'email' => 'sekretaris@tpq.test',
            'password' => Hash::make('password123'),
            'role' => 'sekretaris',
        ]);

        $this->admin = User::create([
            'name' => 'Admin TPQ',
            'username' => 'admin_test',
            'email' => 'admin@tpq.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $this->guru = User::create([
            'name' => 'Guru TPQ',
            'username' => 'guru_test',
            'email' => 'guru@tpq.test',
            'password' => Hash::make('password123'),
            'role' => 'guru',
        ]);
    }

    public function test_sekretaris_can_create_agenda(): void
    {
        $response = $this->actingAs($this->sekretaris, 'sanctum')->postJson('/api/agenda', [
            'tanggal' => '2026-12-01',
            'judul' => 'Ujian Akhir Semester Santri',
            'kategori' => 'Akademik',
            'keterangan' => 'Pelaksanaan evaluasi pembelajaran jilid',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Agenda berhasil ditambahkan',
            ]);

        $this->assertDatabaseHas('agenda', [
            'judul' => 'Ujian Akhir Semester Santri',
            'kategori' => 'Akademik',
        ]);
    }

    public function test_sekretaris_can_update_agenda(): void
    {
        $agenda = Agenda::create([
            'tanggal' => '2026-12-01',
            'judul' => 'Agenda Lama',
            'kategori' => 'Umum',
            'keterangan' => 'Keterangan awal',
        ]);

        $response = $this->actingAs($this->sekretaris, 'sanctum')->putJson("/api/agenda/{$agenda->id}", [
            'tanggal' => '2026-12-05',
            'judul' => 'Agenda Diperbarui oleh Sekretaris',
            'kategori' => 'Kegiatan',
            'keterangan' => 'Catatan telah disesuaikan',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Agenda berhasil diperbarui',
            ]);

        $this->assertDatabaseHas('agenda', [
            'id' => $agenda->id,
            'judul' => 'Agenda Diperbarui oleh Sekretaris',
            'kategori' => 'Kegiatan',
        ]);
    }

    public function test_sekretaris_can_delete_agenda(): void
    {
        $agenda = Agenda::create([
            'tanggal' => '2026-12-10',
            'judul' => 'Agenda Dihapus',
            'kategori' => 'Umum',
        ]);

        $response = $this->actingAs($this->sekretaris, 'sanctum')->deleteJson("/api/agenda/{$agenda->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Agenda berhasil dihapus',
            ]);

        $this->assertDatabaseMissing('agenda', [
            'id' => $agenda->id,
        ]);
    }

    public function test_sekretaris_can_create_and_delete_hari_libur(): void
    {
        $postResponse = $this->actingAs($this->sekretaris, 'sanctum')->postJson('/api/hari-libur', [
            'tanggal_mulai' => '2026-12-25',
            'tanggal_selesai' => '2026-12-26',
            'keterangan' => 'Libur Semester Ganjil',
        ]);

        $postResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Hari libur berhasil ditambahkan',
            ]);

        $libur = HariLibur::where('keterangan', 'Libur Semester Ganjil')->firstOrFail();

        $delResponse = $this->actingAs($this->sekretaris, 'sanctum')->deleteJson("/api/hari-libur/{$libur->id}");
        $delResponse->assertStatus(200);

        $this->assertDatabaseMissing('hari_libur', [
            'id' => $libur->id,
        ]);
    }

    public function test_unauthorized_role_cannot_manage_agenda(): void
    {
        $response = $this->actingAs($this->guru, 'sanctum')->postJson('/api/agenda', [
            'tanggal' => '2026-12-01',
            'judul' => 'Agenda Terlarang',
        ]);

        $response->assertStatus(403);
    }
}

