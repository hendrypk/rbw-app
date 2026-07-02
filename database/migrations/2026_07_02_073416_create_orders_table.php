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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique(); // Ditambahkan
            $table->string('customer_name')->nullable(); // Ditambahkan
            
            // Kolom Keuangan & HPP (Ditambahkan)
            $table->decimal('total_hpp', 15, 2)->default(0);
            $table->decimal('total_overhead', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('final_total', 15, 2)->default(0); // Menggantikan total_amount agar sesuai model
            
            $table->string('payment_method')->default('cash'); // cash, qris, edc
            $table->string('status')->default('unpaid'); // draft, completed, cancelled
            $table->text('notes')->nullable(); // Menampung orderNote dari frontend
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
