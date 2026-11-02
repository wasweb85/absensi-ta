<?php

namespace Tests\Feature;

use App\Models\Agenda;
use App\Models\HariLibur;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TelegramAgendaReminderTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin TPQ',
            'username' => 'admin_tele',
            'email' => 'admin_tele@test.local',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        Pengaturan::setValue('telegram_bot_token', '123456:mock_token_abc');
        Pengaturan::setValue('telegram_chat_id', '-100987654321');
        Pengaturan::setValue('telegram_notif_agenda_aktif', '1');
        Pengaturan::setValue('telegram_notif_agenda_jam', '07:00');
    }

    public function test_telegram_service_sends_message(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 999,
                    'text' => 'Pesan Tes',
                ],
            ], 200),
        ]);

        $service = app(TelegramService::class);
        $result = $service->sendMessage('<b>Halo Telegram</b>');

        $this->assertTrue($result['success']);
        $this->assertEquals(999, $result['data']['message_id']);
    }

    public function test_api_test_telegram_connection(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1001],
            ], 200),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/pengaturan/test-telegram', [
            'bot_token' => '123456:mock_token_abc',
            'chat_id' => '-100987654321',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_agenda_reminder_h_minus_1_dispatches_when_agenda_exists_tomorrow(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1002],
            ], 200),
        ]);

        $tomorrow = Carbon::tomorrow('Asia/Jakarta')->format('Y-m-d');

        Agenda::create([
            'tanggal' => $tomorrow,
            'tanggal_selesai' => $tomorrow,
            'judul' => 'Ujian Munaqosyah Santri Jilid 6',
            'kategori' => 'Ujian',
            'keterangan' => 'Bertempat di aula utama lantai 2',
        ]);

        HariLibur::create([
            'tanggal_mulai' => $tomorrow,
            'tanggal_selesai' => $tomorrow,
            'keterangan' => 'Libur Persiapan Acara',
        ]);

        $exitCode = Artisan::call('agenda:remind-telegram', ['--force' => true]);
        $this->assertEquals(0, $exitCode);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage') &&
                   str_contains($request['text'], 'Ujian Munaqosyah Santri Jilid 6') &&
                   str_contains($request['text'], 'Libur Persiapan Acara');
        });
    }

    public function test_agenda_reminder_returns_when_no_agenda_tomorrow(): void
    {
        Http::fake();

        $service = app(TelegramService::class);
        $result = $service->sendAgendaReminderHMinus1(true);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['sent']);
        $this->assertStringContainsString('Tidak ada agenda kegiatan', $result['message']);

        Http::assertNothingSent();
    }

    public function test_pengaturan_includes_agenda_telegram_defaults(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/pengaturan');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals('1', $data['telegram_notif_agenda_aktif']);
        $this->assertEquals('07:00', $data['telegram_notif_agenda_jam']);
    }
}
