<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Services\ConversationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatConversationDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected Kelas $kelas;

    protected Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kelas = Kelas::create([
            'nama_kelas' => 'JILID 2',
            'warna_label' => '#10b981',
        ]);

        $this->siswa = Siswa::create([
            'nisn' => '2001',
            'nama_lengkap' => 'Zaid bin Tsabit',
            'jenis_kelamin' => 'L',
            'id_kelas' => $this->kelas->id,
            'no_hp_ortu' => '081234567890',
            'nama_ortu' => 'Bapak Tsabit',
            'status' => 'aktif',
        ]);
    }

    public function test_can_create_conversation_and_record_messages_with_relations(): void
    {
        $service = app(ConversationService::class);

        $this->assertTrue($service->hasConversationTables());

        $pair = $service->recordInteractionPair(
            phoneNumber: '0812-3456-7890',
            incomingMessage: 'Apakah Zaid sudah masuk hari ini?',
            botReply: 'Alhamdulillah ananda Zaid bin Tsabit sudah hadir pukul 15:25 WIB.',
            intent: 'absensi',
            santriId: $this->siswa->id
        );

        /** @var ChatConversation $conversation */
        $conversation = $pair['conversation'];

        $this->assertSame('6281234567890', $conversation->phone_number);
        $this->assertSame($this->siswa->id, $conversation->santri_id);
        $this->assertSame('active', $conversation->status);
        $this->assertNotNull($conversation->last_message_at);

        $this->assertSame('Zaid bin Tsabit', $conversation->santri->nama_lengkap);
        $this->assertCount(2, $conversation->messages);
        $this->assertCount(1, $this->siswa->chatConversations);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_type' => 'wali',
            'message' => 'Apakah Zaid sudah masuk hari ini?',
            'intent' => 'absensi',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_type' => 'bot',
            'intent' => 'absensi',
        ]);
    }

    public function test_builds_gemini_formatted_history_from_conversation_messages(): void
    {
        $service = app(ConversationService::class);

        $service->recordInteractionPair(
            phoneNumber: '6281234567890',
            incomingMessage: 'Assalamualaikum ustadz, anak saya hadir hari ini?',
            botReply: 'Waalaikumsalam Bapak/Ibu, ananda Zaid hadir tepat waktu pukul 15:20 WIB.',
            intent: 'absensi',
            santriId: $this->siswa->id
        );

        $pair2 = $service->recordInteractionPair(
            phoneNumber: '6281234567890',
            incomingMessage: 'Kalau SPP bulan ini sudah lunas belum?',
            botReply: 'SPP bulan ini untuk ananda Zaid sudah tercatat LUNAS.',
            intent: 'spp_notice',
            santriId: $this->siswa->id
        );

        $history = $service->getGeminiHistory($pair2['conversation'], 8);

        $this->assertCount(4, $history);
        $this->assertSame('user', $history[0]['role']);
        $this->assertSame('Assalamualaikum ustadz, anak saya hadir hari ini?', $history[0]['parts'][0]['text']);
        $this->assertSame('model', $history[1]['role']);
        $this->assertSame('user', $history[2]['role']);
        $this->assertSame('model', $history[3]['role']);
    }

    public function test_resolves_active_santri_and_last_intent_within_time_window(): void
    {
        $service = app(ConversationService::class);

        $pair = $service->recordInteractionPair(
            phoneNumber: '081234567890',
            incomingMessage: 'Cek SPP Zaid',
            botReply: 'SPP ananda Zaid lunas.',
            intent: 'spp_notice',
            santriId: $this->siswa->id
        );

        $this->assertSame($this->siswa->id, $service->getActiveSantriId('6281234567890', 30));
        $this->assertSame('spp_notice', $service->getLastConversationIntent('6281234567890', 30));

        // Simulasikan waktu percakapan sudah lewat 45 menit yang lalu
        $pair['conversation']->update([
            'last_message_at' => Carbon::now('Asia/Jakarta')->subMinutes(45),
        ]);

        $this->assertNull($service->getActiveSantriId('6281234567890', 30));
        $this->assertNull($service->getLastConversationIntent('6281234567890', 30));
    }

    public function test_cascade_delete_messages_when_conversation_deleted(): void
    {
        $service = app(ConversationService::class);

        $pair = $service->recordInteractionPair(
            phoneNumber: '6281234567890',
            incomingMessage: 'Halo',
            botReply: 'Halo Bapak/Ibu',
            intent: 'bantuan',
            santriId: $this->siswa->id
        );

        $convId = $pair['conversation']->id;
        $this->assertSame(2, ChatMessage::where('conversation_id', $convId)->count());

        $pair['conversation']->delete();

        $this->assertSame(0, ChatMessage::where('conversation_id', $convId)->count());
    }
}
