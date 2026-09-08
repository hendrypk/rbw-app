<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'raw_materials',
            'categories',
            'overhead_costs',
            'vouchers',
            'menus',
            'purchase_orders',
            'purchase_order_items',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'outlet_id')) {
                Schema::table($table, function (Blueprint $columnTable) {
                    $columnTable->foreignUuid('outlet_id')->nullable()->constrained('outlets')->cascadeOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'raw_materials',
            'categories',
            'overhead_costs',
            'vouchers',
            'menus',
            'purchase_orders',
            'purchase_order_items',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'outlet_id')) {
                Schema::table($table, function (Blueprint $columnTable) {
                    $columnTable->dropForeign(['outlet_id']);
                    $columnTable->dropColumn('outlet_id');
                });
            }
        }
    }
};