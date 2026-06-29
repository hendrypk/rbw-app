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
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('entry_date');
            $table->string('reference_type')->nullable(); // Polymorphic: App\Models\Order atau App\Models\PurchaseOrder
            $table->uuid('reference_id')->nullable();     // ID dari baris transaksi asal
            $table->string('description');                // Keterangan final jurnal
            $table->decimal('total_amount', 15, 2);       // Total nilai yang dijurnal (harus balance)
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
