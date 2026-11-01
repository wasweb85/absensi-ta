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
        if (Schema::hasTable('kelas') && ! Schema::hasColumn('kelas', 'nominal_spp')) {
            Schema::table('kelas', function (Blueprint $table) {
                $table->decimal('nominal_spp', 12, 2)->default(15000)->after('nama_kelas');
            });
        }

        if (! Schema::hasTable('tagihan_spp')) {
            Schema::create('tagihan_spp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_siswa')->constrained('siswa')->cascadeOnDelete();
                $table->tinyInteger('bulan')->comment('1 = Januari s/d 12 = Desember');
                $table->year('tahun');
                $table->string('tahun_ajaran', 20)->default('2026/2027');
                $table->decimal('nominal_tagihan', 12, 2);
                $table->enum('status', ['belum_lunas', 'lunas', 'kurang_bayar'])->default('belum_lunas');
                $table->date('jatuh_tempo')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['id_siswa', 'bulan', 'tahun'], 'uniq_tagihan_siswa_periode');
            });
        }

        if (! Schema::hasTable('pembayaran_spp')) {
            Schema::create('pembayaran_spp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_tagihan')->constrained('tagihan_spp')->cascadeOnDelete();
                $table->foreignId('id_siswa')->constrained('siswa')->cascadeOnDelete();
                $table->string('nomor_kuitansi', 50)->unique();
                $table->dateTime('tanggal_bayar');
                $table->decimal('jumlah_bayar', 12, 2);
                $table->enum('metode_pembayaran', ['Tunai', 'Transfer'])->default('Tunai');
                $table->foreignId('id_petugas')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('status_transaksi', ['valid', 'dibatalkan'])->default('valid');
                $table->text('alasan_pembatalan')->nullable();
                $table->string('dibatalkan_oleh', 100)->nullable();
                $table->text('catatan')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran_spp');
        Schema::dropIfExists('tagihan_spp');

        if (Schema::hasTable('kelas') && Schema::hasColumn('kelas', 'nominal_spp')) {
            Schema::table('kelas', function (Blueprint $table) {
                $table->dropColumn('nominal_spp');
            });
        }
    }
};

