<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ConversationService
{
    /**
     * Periksa ketersediaan tabel percakapan di database.
     */
    public function hasConversationTables(): bool
    {
        try {
            return Schema::hasTable('chat_conversations') && Schema::hasTable('chat_messages');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Pastikan tabel chat_conversations dan chat_messages tersedia (self-healing migration untuk serverless / TiDB Cloud).
     */
    public function ensureTablesExist(): bool
    {
        if ($this->hasConversationTables()) {
            return true;
        }

        try {
            if (! Schema::hasTable('chat_conversations')) {
                Schema::create('chat_conversations', function (Blueprint $table) {
                    $table->id();
                    $table->string('phone_number', 25)->index();
                    $table->foreignId('santri_id')->nullable()->constrained('siswa')->nullOnDelete();
                    $table->string('status', 30)->default('active')->index();
                    $table->timestamp('last_message_at')->nullable()->index();
                    $table->timestamps();

                    $table->index(['phone_number', 'status']);
                });
            }

            if (! Schema::hasTable('chat_messages')) {
                Schema::create('chat_messages', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
                    $table->string('sender_type', 20)->index();
                    $table->text('message');
                    $table->string('intent', 50)->nullable()->index();
                    $table->timestamps();

                    $table->index(['conversation_id', 'created_at']);
                });
            }

            return $this->hasConversationTables();
        } catch (\Throwable $e) {
            Log::warning('Direct create conversation tables failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Ambil atau buat sesi percakapan aktif untuk nomor WhatsApp wali santri.
     */
    public function getOrCreateConversation(string $phoneNumber, ?int $santriId = null): ChatConversation
    {
        $this->ensureTablesExist();
        $normalized = WhatsAppService::normalizePhone($phoneNumber);
        $phone = $normalized !== '' ? $normalized : $phoneNumber;

        $conversation = ChatConversation::query()
            ->where('phone_number', $phone)
            ->latest('id')
            ->first();

        if (! $conversation) {
            return ChatConversation::create([
                'phone_number' => $phone,
                'santri_id' => $santriId,
                'status' => 'active',
                'last_message_at' => Carbon::now('Asia/Jakarta'),
            ]);
        }

        // Jika sesi terakhir sudah tidak aktif lebih dari 30 menit, reset status ke active
        if ($conversation->last_message_at && $conversation->last_message_at->lt(Carbon::now('Asia/Jakarta')->subMinutes(30))) {
            $conversation->status = 'active';
            if ($santriId !== null) {
                $conversation->santri_id = $santriId;
            }
            $conversation->last_message_at = Carbon::now('Asia/Jakarta');
            $conversation->save();

            return $conversation;
        }

        if ($santriId !== null && (int) $conversation->santri_id !== $santriId) {
            $conversation->santri_id = $santriId;
        }

        $conversation->last_message_at = Carbon::now('Asia/Jakarta');
        $conversation->save();

        return $conversation;
    }

    /**
     * Perbarui santri aktif dan status percakapan.
     */
    public function updateActiveSantri(ChatConversation $conversation, ?int $santriId, string $status = 'active'): ChatConversation
    {
        $conversation->update([
            'santri_id' => $santriId,
            'status' => $status,
            'last_message_at' => Carbon::now('Asia/Jakarta'),
        ]);

        return $conversation;
    }

    /**
     * Catat pesan masuk dari wali santri ke tabel chat_messages.
     */
    public function recordIncomingMessage(ChatConversation $conversation, string $message, ?string $intent = null): ChatMessage
    {
        $conversation->update([
            'last_message_at' => Carbon::now('Asia/Jakarta'),
        ]);

        return ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'wali',
            'message' => $message,
            'intent' => $intent,
        ]);
    }

    /**
     * Catat pesan balasan dari bot ke tabel chat_messages.
     */
    public function recordBotReply(ChatConversation $conversation, string $reply, ?string $intent = null): ChatMessage
    {
        $conversation->update([
            'last_message_at' => Carbon::now('Asia/Jakarta'),
        ]);

        return ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'bot',
            'message' => $reply,
            'intent' => $intent,
        ]);
    }

    /**
     * Catat pasangan interaksi (pesan wali + balasan bot) sekaligus dalam satu percakapan.
     *
     * @return array{conversation: ChatConversation, incoming: ChatMessage, outgoing: ChatMessage}
     */
    public function recordInteractionPair(
        string $phoneNumber,
        string $incomingMessage,
        string $botReply,
        ?string $intent = null,
        ?int $santriId = null,
        string $status = 'active'
    ): array {
        $conversation = $this->getOrCreateConversation($phoneNumber, $santriId);
        if ($conversation->status !== $status || ($santriId !== null && (int) $conversation->santri_id !== $santriId)) {
            $conversation->update([
                'santri_id' => $santriId ?? $conversation->santri_id,
                'status' => $status,
                'last_message_at' => Carbon::now('Asia/Jakarta'),
            ]);
        }

        $incoming = $this->recordIncomingMessage($conversation, $incomingMessage, $intent);
        $outgoing = $this->recordBotReply($conversation, $botReply, $intent);

        return [
            'conversation' => $conversation,
            'incoming' => $incoming,
            'outgoing' => $outgoing,
        ];
    }

    /**
     * Ambil riwayat pesan terbaru dalam format contents Google Gemini API.
     *
     * @return array<int, array{role: string, parts: array<int, array{text: string}>}>
     */
    public function getGeminiHistory(ChatConversation $conversation, int $limit = 8): array
    {
        $messages = $conversation->messages()
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $contents = [];
        foreach ($messages as $msg) {
            $role = $msg->sender_type === 'bot' ? 'model' : 'user';
            $text = trim((string) $msg->message);
            if ($text === '') {
                continue;
            }

            $contents[] = [
                'role' => $role,
                'parts' => [
                    ['text' => $text],
                ],
            ];
        }

        while (! empty($contents) && $contents[0]['role'] !== 'user') {
            array_shift($contents);
        }

        return array_values($contents);
    }

    /**
     * Ambil ID santri aktif dari percakapan dalam rentang waktu tertentu (default 30 menit).
     */
    public function getActiveSantriId(string $phoneNumber, int $withinMinutes = 30): ?int
    {
        if (! $this->hasConversationTables()) {
            return null;
        }

        $normalized = WhatsAppService::normalizePhone($phoneNumber);
        $phone = $normalized !== '' ? $normalized : $phoneNumber;

        $conversation = ChatConversation::query()
            ->where('phone_number', $phone)
            ->whereNotNull('santri_id')
            ->where('last_message_at', '>=', Carbon::now('Asia/Jakarta')->subMinutes($withinMinutes))
            ->latest('id')
            ->first();

        return $conversation?->santri_id ? (int) $conversation->santri_id : null;
    }

    /**
     * Ambil intent terakhir dari percakapan wali santri dalam rentang waktu tertentu.
     */
    public function getLastConversationIntent(string $phoneNumber, int $withinMinutes = 30): ?string
    {
        if (! $this->hasConversationTables()) {
            return null;
        }

        $normalized = WhatsAppService::normalizePhone($phoneNumber);
        $phone = $normalized !== '' ? $normalized : $phoneNumber;

        $conversation = ChatConversation::query()
            ->where('phone_number', $phone)
            ->where('last_message_at', '>=', Carbon::now('Asia/Jakarta')->subMinutes($withinMinutes))
            ->latest('id')
            ->first();

        if (! $conversation) {
            return null;
        }

        $lastMsg = $conversation->messages()
            ->whereNotNull('intent')
            ->whereIn('intent', ['absensi', 'spp_notice', 'agenda', 'izin_sakit', 'info_tpq'])
            ->latest('id')
            ->first();

        return $lastMsg?->intent;
    }
}
