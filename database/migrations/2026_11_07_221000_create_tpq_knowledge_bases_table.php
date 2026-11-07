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
        if (! Schema::hasTable('tpq_knowledge_bases')) {
            Schema::create('tpq_knowledge_bases', function (Blueprint $table) {
                $table->id();
                $table->string('kategori', 50)->default('umum')->index();
                $table->string('judul', 150);
                $table->string('kata_kunci', 255)->nullable();
                $table->text('konten');
                $table->boolean('is_active')->default(true)->index();
                $table->integer('urutan')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tpq_knowledge_bases');
    }
};
