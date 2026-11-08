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
        Schema::table('pembayaran_spp', function (Blueprint $table) {
            $table->string('cloudinary_public_id', 191)->nullable()->after('catatan');
            $table->text('cloudinary_url')->nullable()->after('cloudinary_public_id');
            $table->string('invoice_token', 64)->nullable()->unique()->after('cloudinary_url');
            $table->string('upload_status', 20)->default('pending')->after('invoice_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pembayaran_spp', function (Blueprint $table) {
            $table->dropUnique(['invoice_token']);
            $table->dropColumn([
                'cloudinary_public_id',
                'cloudinary_url',
                'invoice_token',
                'upload_status',
            ]);
        });
    }
};
