<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TpqKnowledgeBase;
use App\Models\User;
use App\Services\ChatbotService;
use App\Services\GetTpqInformation;
use App\Services\KnowledgeBaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KnowledgeBaseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Kelas $kelas;

    protected Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kelas = Kelas::create([
            'nama_kelas' => 'JILID 1',
            'urutan' => 1,
        ]);

        $this->siswa = Siswa::create([
            'nisn' => '1088',
            'nama_lengkap' => 'Hafidz Ramadhan',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
            'nama_ortu' => 'Ibu Hafidz',
            'no_hp_ortu' => '6281811112222',
        ]);
    }

    public function test_knowledge_base_seeds_defaults_and_answers_general_tpq_questions(): void
    {
        $kbService = app(KnowledgeBaseService::class);
        $all = $kbService->getAll(true);

        $this->assertGreaterThanOrEqual(5, $all->count());

        $chatbot = app(ChatbotService::class);
        $res = $chatbot->handleIncomingMessage('081811112222', 'Jam masuk dan pulang TPQ jam berapa ya?');

        $this->assertTrue($res['success']);
        $this->assertSame('info_tpq', $res['intent']);
        $this->assertStringContainsString('Jam Masuk', $res['reply']);
        $this->assertStringContainsString('Jam Pulang', $res['reply']);
    }

    public function test_dynamic_knowledge_base_entry_answers_without_code_changes(): void
    {
        $kbService = app(KnowledgeBaseService::class);
        $kbService->seedDefaultEntriesIfEmpty();

        // Tambahkan entri baru secara dinamis di database tanpa mengubah kode program
        $kbService->store([
            'kategori' => 'kegiatan',
            'judul' => 'Jadwal Munaqosyah & Khataman',
            'kata_kunci' => 'munaqosyah, khataman, imtihan, ujian kenaikan jilid',
            'konten' => 'Kegiatan Munaqosyah Kenaikan Jilid diadakan setiap akhir semester pada minggu ke-3 bulan Juni dan Desember.',
            'is_active' => true,
            'urutan' => 6,
        ]);

        $chatbot = app(ChatbotService::class);
        $res = $chatbot->handleIncomingMessage('081811112222', 'Ustadz, kapan jadwal munaqosyah kenaikan jilid?');

        $this->assertTrue($res['success']);
        $this->assertSame('info_tpq', $res['intent']);
        $this->assertStringContainsString('Jadwal Munaqosyah & Khataman', $res['reply']);
        $this->assertStringContainsString('minggu ke-3 bulan Juni dan Desember', $res['reply']);

        // Pastikan tool payload untuk Gemini juga memuat entri dinamis tersebut
        $infoTool = app(GetTpqInformation::class);
        $payload = $infoTool->buildToolPayload('kapan jadwal munaqosyah', 'kegiatan');
        $this->assertSame('ok', $payload['status']);
        $this->assertNotEmpty($payload['knowledge_base']);
        $this->assertSame('Jadwal Munaqosyah & Khataman', $payload['knowledge_base'][0]['judul']);
    }

    public function test_admin_can_manage_knowledge_base_via_api(): void
    {
        $admin = User::create([
            'name' => 'Admin TPQ',
            'username' => 'admin_kb',
            'email' => 'admin_kb@tpq.test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        // 1. GET daftar knowledge base (otomatis seed default)
        $indexRes = $this->getJson('/api/chatbot/knowledge-base');
        $indexRes->assertOk()->assertJsonPath('success', true);

        // 2. POST tambah entri baru
        $storeRes = $this->postJson('/api/chatbot/knowledge-base', [
            'kategori' => 'peraturan',
            'judul' => 'Ketentuan Seragam Hari Jumat',
            'kata_kunci' => 'seragam jumat, baju putih, busana jumat',
            'konten' => 'Setiap hari Jumat santri dianjurkan mengenakan busana muslim warna putih.',
            'is_active' => true,
            'urutan' => 10,
        ]);
        $storeRes->assertStatus(201)->assertJsonPath('success', true);
        $id = $storeRes->json('data.id');

        // 3. PUT update entri
        $updateRes = $this->putJson("/api/chatbot/knowledge-base/{$id}", [
            'judul' => 'Ketentuan Seragam Putih Hari Jumat',
        ]);
        $updateRes->assertOk()
            ->assertJsonPath('data.judul', 'Ketentuan Seragam Putih Hari Jumat');

        // 4. DELETE hapus entri
        $deleteRes = $this->deleteJson("/api/chatbot/knowledge-base/{$id}");
        $deleteRes->assertOk()->assertJsonPath('success', true);
        $this->assertNull(TpqKnowledgeBase::find($id));
    }

    public function test_lost_qr_card_query_matches_knowledge_base_not_attendance(): void
    {
        $kbService = app(KnowledgeBaseService::class);
        $kbService->store([
            'kategori' => 'peraturan',
            'judul' => 'Ketentuan Jika Kartu QR Code Hilang atau Tertinggal',
            'kata_kunci' => 'kartu hilang, qr hilang, kartu tertinggal, lupa membawa kartu, kartu presensi hilang',
            'konten' => 'Pembuatan kartu QR Code pengganti kepada administrator TPQ dengan membayar denda sebesar 3.000 Rupiah.',
            'is_active' => true,
            'urutan' => 3,
        ]);

        $chatbot = app(ChatbotService::class);

        // Uji kalimat 1: 'kalau kartu anak saya hilang gimana?'
        $res1 = $chatbot->handleIncomingMessage('081811112222', 'kalau kartu anak saya hilang gimana?');
        $this->assertTrue($res1['success']);
        $this->assertSame('info_tpq', $res1['intent']);
        $this->assertStringContainsString('3.000', $res1['reply']);

        // Uji kalimat 2: 'kartu presensi anak saya hilang gimana?'
        $res2 = $chatbot->handleIncomingMessage('081811112222', 'kartu presensi anak saya hilang gimana?');
        $this->assertTrue($res2['success']);
        $this->assertSame('info_tpq', $res2['intent']);
        $this->assertStringContainsString('3.000', $res2['reply']);
        $this->assertStringNotContainsString('Belum ada catatan presensi', $res2['reply']);
    }
}
