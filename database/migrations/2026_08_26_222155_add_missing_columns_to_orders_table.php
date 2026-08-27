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
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('transaction_fee', 15, 2)->default(0.00)->after('final_total');
            $table->decimal('amount_paid', 15, 2)->default(0.00)->after('transaction_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
           $table->dropColumn(['transaction_fee', 'amount_paid']);
        });
    }
};
