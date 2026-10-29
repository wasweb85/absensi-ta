<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\User;
use App\Services\GoogleSheetSyncService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleSheetSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kelas = Kelas::create([
            'nama_kelas' => 'JILID 1',
            'urutan' => 1,
            'warna_label' => '#059669',
        ]);

        $this->admin = User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@sekolah.id',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
    }

    public function test_extract_export_csv_url_converts_google_sheet_links(): void
    {
        $service = app(GoogleSheetSyncService::class);

        $url1 = 'https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit?usp=sharing';
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/export?format=csv&gid=0',
            $service->extractExportCsvUrl($url1)
        );

        $url2 = 'https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit#gid=123456';
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/export?format=csv&gid=123456',
            $service->extractExportCsvUrl($url2)
        );
    }

    public function test_extract_export_csv_url_rejects_unauthorized_hosts(): void
    {
        $service = app(GoogleSheetSyncService::class);

        $this->expectException(Exception::class);
        $service->extractExportCsvUrl('https://evil.com/spreadsheets/d/123/edit');
    }

    public function test_sync_spreadsheet_creates_and_updates_students_via_api(): void
    {
        // Siapkan data santri lama yang akan diperbarui
        Siswa::create([
            'nisn' => '1001',
            'nama_lengkap' => 'Nama Lama Fauzi',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
            'status' => 'aktif',
        ]);

        $csvContent = implode("\n", [
            'NISN,Nama Lengkap,Kelas,Jenis Kelamin,No HP Ortu,Nama Ortu,Alamat',
            '1001,Ahmad Fauzi Baru,JILID 1,L,081234567890,Bpk Fauzi,Jl. Mawar No 1',
            '1002,Fatimah Azzahra,JILID 2,P,081298765432,Ibu Fatimah,Jl. Melati No 2',
        ]);

        Http::fake([
            'docs.google.com/*' => Http::response($csvContent, 200, ['Content-Type' => 'text/csv']),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/siswa/sync-spreadsheet', [
            'spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit?usp=sharing',
            'auto_create_kelas' => true,
            'save_as_default' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'total_rows' => 2,
                'created' => 1,
                'updated' => 1,
                'failed' => 0,
            ],
        ]);

        // Cek bahwa data lama terupdate
        $this->assertDatabaseHas('siswa', [
            'nisn' => '1001',
            'nama_lengkap' => 'Ahmad Fauzi Baru',
            'no_hp_ortu' => '6281234567890',
        ]);

        // Cek bahwa data baru dibuat + kelas JILID 2 terbuat otomatis
        $this->assertDatabaseHas('siswa', [
            'nisn' => '1002',
            'nama_lengkap' => 'Fatimah Azzahra',
            'jenis_kelamin' => 'P',
        ]);

        $this->assertDatabaseHas('kelas', [
            'nama_kelas' => 'JILID 2',
        ]);

        // Cek akun user login santri otomatis terbuat
        $this->assertDatabaseHas('users', [
            'username' => '1002',
            'role' => 'siswa',
        ]);

        // Cek URL tersimpan di pengaturan
        $this->assertSame(
            'https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit?usp=sharing',
            Pengaturan::getValue('spreadsheet_santri_url')
        );
    }

    public function test_sync_detects_private_spreadsheet_error(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response('<html><title>Google Accounts: Sign in</title></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/siswa/sync-spreadsheet', [
            'spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit?usp=sharing',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('tidak dapat diakses publik', $response->json('message'));
    }
}
