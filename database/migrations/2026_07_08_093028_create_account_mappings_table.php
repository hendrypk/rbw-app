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
        Schema::create('account_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transaction_type')->unique(); // Contoh: 'pos_sales', 'purchase_invoice'
            
            // Hanya 1 pasang akun default
            $table->foreignUuid('debit_account_id')->nullable()->constrained('accounts');
            $table->foreignUuid('credit_account_id')->nullable()->constrained('accounts');
            
            $table->string('description_template')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_mappings');
    }
};
