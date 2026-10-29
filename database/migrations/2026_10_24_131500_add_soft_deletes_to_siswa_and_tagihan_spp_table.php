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
        Schema::table('siswa', function (Blueprint $table) {
            $table->softDeletes()->after('foto');
            $table->index('no_hp_ortu');
        });

        Schema::table('tagihan_spp', function (Blueprint $table) {
            $table->softDeletes()->after('jatuh_tempo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropIndex(['no_hp_ortu']);
            $table->dropSoftDeletes();
        });

        Schema::table('tagihan_spp', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
