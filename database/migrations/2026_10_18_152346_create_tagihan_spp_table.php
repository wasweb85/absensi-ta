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
        Schema::create('tagihan_spp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_siswa')->constrained('siswa')->cascadeOnDelete();
            $table->unsignedTinyInteger('bulan')->index();
            $table->unsignedSmallInteger('tahun')->index();
            $table->string('tahun_ajaran', 20)->index();
            $table->decimal('nominal_tagihan', 12, 2)->default(0);
            $table->enum('status', ['belum_lunas', 'lunas', 'kurang_bayar'])->default('belum_lunas')->index();
            $table->date('jatuh_tempo')->nullable();
            $table->timestamps();

            $table->unique(['id_siswa', 'bulan', 'tahun']);
            $table->index(['id_siswa', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tagihan_spp');
    }
};
