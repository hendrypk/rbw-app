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
        Schema::create('menu_recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignUuid('raw_material_id')->constrained('raw_materials');
            $table->decimal('qty_usage', 15, 4);
            $table->decimal('unit_cost_snapshot', 15, 4)->default(0);
            $table->decimal('subtotal_cost', 15, 4)->default(0);
            $table->timestamps();
 
            $table->unique(['menu_id', 'raw_material_id']);
            $table->index('menu_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_recipes');
    }
};
