<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ubah kolom journal_entries terlebih dahulu
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->char('outlet_id', 36)->nullable()->change();
        });

        // 2. Jika tabel accounts juga mengalami error serupa, drop foreign key-nya dulu
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->char('outlet_id', 36)->nullable()->change();
            $table->foreign('outlet_id')->references('id')->on('outlets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->char('outlet_id', 26)->nullable()->change();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->char('outlet_id', 26)->nullable()->change();
            $table->foreign('outlet_id')->references('id')->on('outlets')->nullOnDelete();
        });
    }
};