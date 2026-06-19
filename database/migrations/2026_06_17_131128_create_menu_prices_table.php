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
        Schema::create('menu_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->enum('channel', ['offline', 'shopeefood', 'grabfood', 'gofood']);
            $table->decimal('margin_percent', 8, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('platform_fee_percent', 8, 2)->default(0);
            $table->decimal('nett_price', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
 
            $table->unique(['menu_id', 'channel']);
            $table->index('menu_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_prices');
    }
};
