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

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignUlid('outlet_id')->nullable()->after('id')->constrained('outlets')->nullOnDelete();
        });

        Schema::table('account_mappings', function (Blueprint $table) {
            $table->foreignUlid('outlet_id')->nullable()->after('id')->constrained('outlets')->nullOnDelete();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignUlid('outlet_id')->nullable()->after('id')->constrained('outlets')->nullOnDelete();
        });

        Schema::table('stock_ledgers', function (Blueprint $table) {
            $table->foreignUlid('outlet_id')->nullable()->after('id')->constrained('outlets')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropColumn('outlet_id');
        });

        Schema::table('account_mappings', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropColumn('outlet_id');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropColumn('outlet_id');
        });

        Schema::table('stock_ledgers', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropColumn('outlet_id');
        });
    }
};
