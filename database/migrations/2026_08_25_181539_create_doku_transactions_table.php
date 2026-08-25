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
        Schema::create('doku_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->index(); 
            $table->string('original_reference_no')->nullable(); 
            $table->decimal('amount', 12, 2);
            $table->text('qr_content')->nullable();
            $table->string('status')->default('pending'); 
            $table->timestamp('expired_at')->nullable(); 
            $table->json('raw_response')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doku_transactions');
    }
};