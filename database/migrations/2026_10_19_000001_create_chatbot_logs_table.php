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
        Schema::create('chatbot_logs', function (Blueprint $table) {
            $table->id();
            $table->string('no_pengirim', 25)->index();
            $table->text('pesan_masuk');
            $table->string('intent_terdeteksi', 50)->default('unknown')->index();
            $table->foreignId('id_siswa')->nullable()->constrained('siswa')->nullOnDelete();
            $table->text('pesan_keluar');
            $table->enum('status', ['sukses', 'gagal'])->default('sukses');
            $table->text('keterangan_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot_logs');
    }
};
