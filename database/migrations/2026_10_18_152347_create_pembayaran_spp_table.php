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
        Schema::create('pembayaran_spp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_tagihan')->constrained('tagihan_spp')->cascadeOnDelete();
            $table->foreignId('id_siswa')->constrained('siswa')->cascadeOnDelete();
            $table->string('nomor_kuitansi', 50)->unique()->index();
            $table->dateTime('tanggal_bayar')->index();
            $table->decimal('jumlah_bayar', 12, 2);
            $table->enum('metode_pembayaran', ['Tunai', 'Transfer'])->default('Tunai');
            $table->foreignId('id_petugas')->constrained('users')->cascadeOnUpdate();
            $table->enum('status_transaksi', ['valid', 'dibatalkan'])->default('valid')->index();
            $table->text('alasan_pembatalan')->nullable();
            $table->string('dibatalkan_oleh')->nullable();
            $table->string('catatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran_spp');
    }
};
