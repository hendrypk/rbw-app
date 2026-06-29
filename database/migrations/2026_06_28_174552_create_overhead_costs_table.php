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
        Schema::create('overhead_costs', function (Blueprint $table) {$table->uuid('id')->primary();
            $table->string('name'); // Contoh: "Paperbox & Plastik", "Gas Elpiji"
            $table->decimal('amount', 15, 2)->default(0); // Nominal biaya
            $table->string('type')->default('per_porsi'); // Opsional: untuk tracking tipe biaya
            $table->boolean('is_active')->default(true); // Status Aktif/Tidak
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overhead_costs');
    }
};
