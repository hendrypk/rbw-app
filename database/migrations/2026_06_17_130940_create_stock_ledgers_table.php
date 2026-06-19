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
        Schema::create('stock_ledgers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('raw_material_id')->constrained('raw_materials');
            $table->uuid('reference_id')->nullable();
            $table->string('reference_type', 60)->nullable(); // PurchaseOrderItem | manual
            $table->enum('movement_type', ['in', 'out', 'adjustment']);
            $table->decimal('qty', 15, 4);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('avg_cost_before', 15, 4)->default(0);
            $table->decimal('avg_cost_after', 15, 4)->default(0);
            $table->decimal('stock_before', 15, 4)->default(0);
            $table->decimal('stock_after', 15, 4)->default(0);
            $table->string('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
 
            $table->index(['raw_material_id', 'created_at']);
            $table->index('movement_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_ledgers');
    }
};
