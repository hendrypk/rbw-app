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
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('category', ['1', '2', '3', '4', '5']); // 1: Kas & Bank, 2: Pendapatan, dst.
            $table->string('account_number'); // Input manual, misal: "1000", "2100"
            $table->string('code')->unique();  // Generated otomatis: "1-1000", "5-2000"
            $table->string('name');            // Nama Akun
            $table->enum('normal_balance', ['debit', 'credit']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Memastikan kombinasi kategori dan nomor akun manual tidak boleh kembar
            $table->unique(['category', 'account_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
