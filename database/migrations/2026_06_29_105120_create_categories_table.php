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
        // 1. Buat Tabel Categories Baru
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->boolean('is_visible')->default(true); // Untuk fitur hide/unhide
            $table->timestamps();
        });

        // 2. Modifikasi Tabel Menus agar menggunakan Foreign Key
        Schema::table('menus', function (Blueprint $table) {
            // Drop kolom string lama jika ada
            if (Schema::hasColumn('menus', 'category')) {
                $table->dropColumn('category');
            }
            
            // Tambahkan relasi ke tabel categories dengan proteksi RESTRICT
            $table->foreignUuid('category_id')
                  ->nullable()
                  ->after('name')
                  ->constrained('categories')
                  ->restrictOnDelete(); // Mengunci hapus jika kategori dipakai di menu
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
            $table->string('category')->nullable()->after('name');
        });
        
        Schema::dropIfExists('categories');
    }
};
