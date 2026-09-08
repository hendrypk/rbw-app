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
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'outlet_id')) {
                $table->uuid('outlet_id')->nullable();
            } else {
                // Jika sudah ada, ubah menjadi nullable
                $table->uuid('outlet_id')->nullable()->change();
            }        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'outlet_id')) {
                $table->uuid('outlet_id')->nullable(false)->change();
            }
        });
    }
};
