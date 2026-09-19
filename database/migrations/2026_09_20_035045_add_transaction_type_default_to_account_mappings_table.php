<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('account_mappings', function (Blueprint $table) {
            if (!Schema::hasColumn('account_mappings', 'transaction_type')) {
                $table->string('transaction_type')->index();
            }
            if (!Schema::hasColumn('account_mappings', 'template')) {
                $table->text('template')->nullable();
            }
        });

        $outlets = DB::table('outlets')->get();

        $newMappings = [
            [
                'transaction_type' => 'pos_sales_discount',
                'template' => 'Potongan diskon penjualan POS #{{order_number}}',
            ],
            [
                'transaction_type' => 'pos_mdr_fee',
                'template' => 'Biaya MDR QRIS/EDC order POS #{{order_number}}',
            ],
            [
                'transaction_type' => 'pos_sales_return',
                'template' => 'Retur penjualan barang order POS #{{order_number}}',
            ],
            [
                'transaction_type' => 'pos_tax_vat',
                'template' => 'Pemungutan pajak/PB1 order POS #{{order_number}}',
            ],
            [
                'transaction_type' => 'purchase_return',
                'template' => 'Retur pembelian bahan baku atas PO #{{po_number}}',
            ],
            [
                'transaction_type' => 'loyalty_point_redemption',
                'template' => 'Biaya penukaran poin loyalitas pelanggan #{{order_number}}',
            ],
        ];

        foreach ($outlets as $outlet) {
            foreach ($newMappings as $map) {
                $exists = DB::table('account_mappings')
                    ->where('outlet_id', $outlet->id)
                    ->where('transaction_type', $map['transaction_type'])
                    ->exists();

                if (!$exists) {
                    DB::table('account_mappings')->insert([
                        'id'               => (string) Illuminate\Support\Str::uuid(),
                        'outlet_id'        => $outlet->id,
                        'transaction_type' => $map['transaction_type'],
                        'template'         => $map['template'],
                        'debit_account_id' => null,
                        'credit_account_id'=> null,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $types = [
            'pos_sales_discount',
            'pos_mdr_fee',
            'pos_sales_return',
            'pos_tax_vat',
            'purchase_return',
            'inventory_stock_opname_shrinkage',
            'loyalty_point_redemption'
        ];

        DB::table('account_mappings')->whereIn('transaction_type', $types)->delete();
    }
};
