<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_mappings', function (Blueprint $table) {
            // 1. Drop foreign key terlebih dahulu
            $table->dropForeign(['outlet_id']);
            
            // 2. Ubah tipe kolom menjadi char(36) atau uuid
            $table->char('outlet_id', 36)->nullable()->change();
            
            // 3. Pasang kembali foreign key ke tabel outlets
            $table->foreign('outlet_id')->references('id')->on('outlets')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('account_mappings', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->char('outlet_id', 26)->nullable()->change();
            $table->foreign('outlet_id')->references('id')->on('outlets')->onDelete('set null');
        });
    }
};