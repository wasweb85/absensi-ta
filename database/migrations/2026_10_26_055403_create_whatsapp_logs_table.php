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
        Schema::create('whatsapp_logs', function (Blueprint $table) {
            $table->id();
            $table->string('fonnte_id', 100)->nullable()->index();
            $table->string('nomor_tujuan', 25)->index();
            $table->string('nama_penerima', 100)->nullable()->index();
            $table->text('pesan');
            $table->string('tipe_pesan', 50)->default('manual')->index();
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending')->index();
            $table->text('keterangan_error')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('last_retried_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Composite indexes for fast filtering and ordering in monitoring dashboard
            $table->index(['status', 'created_at']);
            $table->index(['tipe_pesan', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_logs');
    }
};
