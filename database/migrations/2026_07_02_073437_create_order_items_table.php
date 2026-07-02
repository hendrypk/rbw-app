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
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Pastikan ada ->primary() karena model pakai HasUuids
            $table->foreignUuid('order_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('menu_id')->constrained()->onDelete('restrict');
            
            // Kolom Nilai Histori Transaksi (Ditambahkan & Diselaraskan)
            $table->decimal('price', 15, 2); 
            $table->integer('quantity'); // Diubah dari 'qty' menjadi 'quantity' sesuai $fillable model
            $table->decimal('hpp', 15, 2)->default(0); // Ditambahkan
            $table->decimal('overhead_cost', 15, 2)->default(0); // Ditambahkan
            $table->decimal('subtotal', 15, 2)->default(0); // Ditambahkan
            
            $table->timestamps();
            // Di Model OrderItem tidak ada 'use SoftDeletes', 
            // jika di database mau ada softDeletes, tambahkan 'use SoftDeletes' di dalam class OrderItem PHP-nya.
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
