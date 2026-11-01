<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::table('pengaturan')
                ->where('key', 'nama_instansi')
                ->update(['value' => "TPQ Nurul Mun'im"]);

            Cache::forget('pengaturan_nama_instansi');
        } catch (Throwable $e) {
            // Abaikan jika tabel belum ada
        }
    }

    public function down(): void {}
};
