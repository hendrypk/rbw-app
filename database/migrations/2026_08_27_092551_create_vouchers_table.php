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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique(); 
            $table->string('name'); 
            $table->enum('type', ['fixed', 'percentage']); 
            $table->decimal('value', 15, 2); 
            $table->decimal('min_spend', 15, 2)->default(0); 
            $table->decimal('max_discount', 15, 2)->nullable(); 
            $table->integer('usage_limit')->nullable(); // null = tanpa batas kuota total
            $table->integer('used_count')->default(0); 
            $table->timestamp('started_at')->nullable(); // null = langsung berlaku / permanent dari awal
            $table->timestamp('expired_at')->nullable(); // null = tidak ada batas kedaluwarsa / permanent
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
