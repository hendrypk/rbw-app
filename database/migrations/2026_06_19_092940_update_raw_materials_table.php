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
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->string('base_unit')->after('name');
            $table->string('purchase_unit')->after('base_unit');
            $table->decimal('conversion_factor', 15, 4)->default(1)->after('purchase_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->dropColumn('base_unit', 'purchase_unit', 'conversion_factor');
        });
    }
};
