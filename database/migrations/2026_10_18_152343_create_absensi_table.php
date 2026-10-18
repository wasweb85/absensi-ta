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
        Schema::create('absensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_siswa')->constrained('siswa')->cascadeOnDelete();
            $table->date('tanggal')->index();
            $table->time('waktu_masuk')->nullable();
            $table->time('waktu_pulang')->nullable();
            $table->enum('status', ['Hadir', 'Terlambat', 'Sakit', 'Izin', 'Alpa'])->default('Hadir');
            $table->enum('metode_scan', ['qr_kamera', 'scanner_usb', 'manual'])->default('qr_kamera');
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['id_siswa', 'tanggal']);
            $table->index(['tanggal', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absensi');
    }
};
