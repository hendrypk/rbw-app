<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ambil ID Akun Kas Utama (1-1000) dan Pendapatan (2-1000) secara dinamis
        $kasUtamaId = DB::table('accounts')->where('code', '1-1000')->value('id');
        $pendapatanId = DB::table('accounts')->where('code', '2-1000')->value('id');

        if (!$kasUtamaId || !$pendapatanId) {
            return;
        }

        // 2. Definisikan metode pembayaran POS yang baru
        $newMappings = [
            [
                'transaction_type'     => 'pos_revenue_cash',
                'debit_account_id'     => $kasUtamaId,
                'credit_account_id'    => $pendapatanId,
                'description_template' => 'Pendapatan penjualan POS Tunai order #{{order_number}}',
            ],
            [
                'transaction_type'     => 'pos_revenue_qris',
                'debit_account_id'     => $kasUtamaId, 
                'credit_account_id'    => $pendapatanId,
                'description_template' => 'Pendapatan penjualan POS QRIS order #{{order_number}}',
            ],
        ];

        foreach ($newMappings as $map) {
            // Cek apakah tipe transaksi sudah terdaftar sebelumnya
            $existing = DB::table('account_mappings')
                ->where('transaction_type', $map['transaction_type'])
                ->first();

            if ($existing) {
                // Jika sudah ada, cukup update datanya saja (tanpa menyentuh ID dan created_at)
                DB::table('account_mappings')
                    ->where('transaction_type', $map['transaction_type'])
                    ->update([
                        'debit_account_id'     => $map['debit_account_id'],
                        'credit_account_id'    => $map['credit_account_id'],
                        'description_template' => $map['description_template'],
                        'updated_at'           => now(),
                    ]);
            } else {
                // Jika benar-benar baru, buat UUID manual dan lakukan INSERT bersih
                DB::table('account_mappings')->insert([
                    'id'                   => Str::uuid()->toString(),
                    'transaction_type'     => $map['transaction_type'],
                    'debit_account_id'     => $map['debit_account_id'],
                    'credit_account_id'    => $map['credit_account_id'],
                    'description_template' => $map['description_template'],
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }
        }

        // 3. Hapus pos_sales_revenue lama (jika ada) agar bersih
        DB::table('account_mappings')->where('transaction_type', 'pos_sales_revenue')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('account_mappings')
            ->whereIn('transaction_type', ['pos_cash', 'pos_qris', 'pos_edc'])
            ->delete();
    }
};