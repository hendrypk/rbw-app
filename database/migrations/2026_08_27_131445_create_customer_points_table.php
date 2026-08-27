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
        Schema::create('customer_points', function (Blueprint $table) {
                $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->integer('points'); // Bisa positif (dapat/redeem) atau negatif (pakai)
            $table->enum('type', ['earned', 'redeemed', 'expired', 'adjusted']);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->integer('total_points')->default(0)->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_points');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('total_points');
        });
    }
};
