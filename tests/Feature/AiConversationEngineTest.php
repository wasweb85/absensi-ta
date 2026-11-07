<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\ChatConversation;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Services\ChatbotService;
use App\Services\IntentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiConversationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Kelas $kelas;

    protected Siswa $santri1;

    protected Siswa $santri2;

    protected Siswa $otherSantri;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kelas = Kelas::create([
            'nama_kelas' => 'JILID 4',
            'urutan' => 4,
        ]);

        $this->santri1 = Siswa::create([
            'nisn' => '4001',
            'nama_lengkap' => 'Ahmad Farel',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
            'nama_ortu' => 'Bapak Ahmad',
            'no_hp_ortu' => '6281311112222',
        ]);

        $this->santri2 = Siswa::create([
            'nisn' => '4002',
            'nama_lengkap' => 'Aisyah Putri',
            'jenis_kelamin' => 'P',
            'id_kelas' => $this->kelas->id,
            'nama_ortu' => 'Bapak Ahmad',
            'no_hp_ortu' => '6281311112222',
        ]);

        $this->otherSantri = Siswa::create([
            'nisn' => '4999',
            'nama_lengkap' => 'Santri Wali Lain',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
            'nama_ortu' => 'Wali Lain',
            'no_hp_ortu' => '6281900009999',
        ]);
    }

    public function test_intent_service_understands_colloquial_indonesian_and_typos(): void
    {
        $intentService = app(IntentService::class);

        $this->assertSame('absensi', $intentService->detectIntent('anak sya udh dtg blm tadi?'));
        $this->assertSame('absensi', $intentService->detectIntent('knp saya blm dpt notifikasi absn hari ini'));
        $this->assertSame('spp_notice', $intentService->detectIntent('sy udh tf spp bln ini tapi blm ada konfirmasi'));
        $this->assertSame('spp_notice', $intentService->detectIntent('anak saya masih nunggk brp bln?'));
        $this->assertSame('info_tpq', $intentService->detectIntent('jam masuk dan pulang tpq jam berapa ya?'));
        $this->assertSame('izin_sakit', $intentService->detectIntent('ustadz mohon izin hari ini ananda sedang sakit demam'));
    }

    public function test_hybrid_ai_engine_executes_function_calling_and_updates_conversation_context(): void
    {
        Config::set('services.gemini.api_key', 'test-gemini-key-hybrid');
        Config::set('services.gemini.model', 'gemini-2.5-flash');

        Absensi::create([
            'id_siswa' => $this->santri1->id,
            'tanggal' => Carbon::today('Asia/Jakarta')->toDateString(),
            'waktu_masuk' => '15:25:00',
            'status' => 'Hadir',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'role' => 'model',
                                'parts' => [
                                    [
                                        'functionCall' => [
                                            'name' => 'check_attendance',
                                            'args' => [
                                                'nama_santri' => 'Farel',
                                                'periode' => 'hari_ini',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200)
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'role' => 'model',
                                'parts' => [
                                    [
                                        'text' => 'Alhamdulillah Bapak/Ibu, ananda *Ahmad Farel* (JILID 4) hari ini sudah hadir tepat waktu pada pukul 15:25 WIB.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $chatbotService = app(ChatbotService::class);
        $result = $chatbotService->handleIncomingMessage('081311112222', 'Ustadz, Farel tadi udah masuk belum ya?');

        $this->assertTrue($result['success']);
        $this->assertSame('absensi', $result['intent']);
        $this->assertSame('gemini_hybrid', $result['engine'] ?? null);
        $this->assertSame($this->santri1->id, $result['siswa']['id'] ?? null);
        $this->assertStringContainsString('Ahmad Farel', $result['reply']);
        $this->assertStringContainsString('15:25', $result['reply']);

        // Pastikan santri aktif di chat_conversations tersimpan ke Ahmad Farel
        $conversation = ChatConversation::where('phone_number', '6281311112222')->first();
        $this->assertNotNull($conversation);
        $this->assertSame($this->santri1->id, $conversation->santri_id);
        $this->assertCount(2, $conversation->messages);
    }

    public function test_hybrid_ai_engine_blocks_unauthorized_access_to_other_student_id(): void
    {
        Config::set('services.gemini.api_key', 'test-gemini-key-security');
        Config::set('services.gemini.model', 'gemini-2.5-flash');

        $capturedToolResponse = null;

        Http::fake(function ($request) use (&$capturedToolResponse) {
            $body = json_decode($request->body(), true);
            $contents = $body['contents'] ?? [];
            $lastContent = end($contents);

            // Jika ini request ke-2 yang membawa functionResponse dari Laravel
            if (isset($lastContent['parts'][0]['functionResponse'])) {
                $capturedToolResponse = $lastContent['parts'][0]['functionResponse']['response'] ?? null;

                return Http::response([
                    'candidates' => [
                        [
                            'content' => [
                                'role' => 'model',
                                'parts' => [
                                    ['text' => 'Mohon maaf Bapak/Ibu, saya hanya dapat menampilkan informasi untuk santri yang terdaftar pada nomor Bapak/Ibu.'],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }

            // Request pertama: simulasi AI mencoba meminta santri_id milik wali lain
            return Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'role' => 'model',
                            'parts' => [
                                [
                                    'functionCall' => [
                                        'name' => 'check_attendance',
                                        'args' => [
                                            'santri_id' => $this->otherSantri->id,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200);
        });

        $chatbotService = app(ChatbotService::class);
        $result = $chatbotService->handleIncomingMessage('081311112222', 'Tolong cek absensi santri lain');

        $this->assertTrue($result['success']);
        $this->assertNotNull($capturedToolResponse);
        $this->assertSame('unauthorized', $capturedToolResponse['status'] ?? null);
        $this->assertStringNotContainsString('Santri Wali Lain', $result['reply']);
    }

    public function test_hybrid_ai_engine_automatically_falls_back_to_local_engine_when_gemini_fails(): void
    {
        Config::set('services.gemini.api_key', 'test-gemini-key-down');
        Config::set('services.gemini.model', 'gemini-2.5-flash');

        Absensi::create([
            'id_siswa' => $this->otherSantri->id,
            'tanggal' => Carbon::today('Asia/Jakarta')->toDateString(),
            'waktu_masuk' => '15:20:00',
            'status' => 'Hadir',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'Service temporarily overloaded'],
            ], 503),
        ]);

        $chatbotService = app(ChatbotService::class);
        $result = $chatbotService->handleIncomingMessage('081900009999', 'Gimana absensi anak saya hari ini?');

        $this->assertTrue($result['success']);
        $this->assertSame('absensi', $result['intent']);
        $this->assertStringContainsString('Santri Wali Lain', $result['reply']);
        $this->assertStringContainsString('HADIR TEPAT WAKTU', $result['reply']);
    }
}
