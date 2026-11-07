<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('chat_conversations')) {
            return;
        }

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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
