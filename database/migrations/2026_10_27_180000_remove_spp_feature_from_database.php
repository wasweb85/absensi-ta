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
        Schema::dropIfExists('pembayaran_spp');
        Schema::dropIfExists('tagihan_spp');

        if (Schema::hasTable('kelas') && Schema::hasColumn('kelas', 'nominal_spp')) {
            Schema::table('kelas', function (Blueprint $table) {
                $table->dropColumn('nominal_spp');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Fitur SPP telah dihapus secara permanen dari arsitektur absensi
    }
};
