<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transaction_type')->unique(); // 'pos_paid', 'pos_pending', 'purchase_paid', 'purchase_pending'

            // Jurnal Utama (J1)
            // j1_debit dibuat nullable karena khusus 'pos_paid', akun debet dipilih langsung oleh user saat transaksi
            $table->foreignUuid('j1_debit_account_id')->nullable()->constrained('accounts')->onDelete('restrict');
            $table->foreignUuid('j1_credit_account_id')->constrained('accounts')->onDelete('restrict');

            // Jurnal Pendukung / Stok HPP (J2)
            $table->foreignUuid('j2_debit_account_id')->nullable()->constrained('accounts')->onDelete('restrict');
            $table->foreignUuid('j2_credit_account_id')->nullable()->constrained('accounts')->onDelete('restrict');

            $table->string('j1_description_template')->nullable();
            $table->string('j2_description_template')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_mappings');
    }
};