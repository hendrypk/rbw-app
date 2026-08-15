<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountMapping;
use Illuminate\Database\Seeder;

class AccountMappingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Definisikan blueprint event transaksi sesuai kolom 'transaction_type' di migrasi terbaru
        $defaultMappings = [
            // --- KELOMPOK PENJUALAN POS ---
            [
                'transaction_type'     => 'pos_sales_revenue',
                'debit_code'           => '1-1000', // Kas Utama / POS
                'credit_code'          => '2-1000', // Pendapatan Penjualan
                'description_template' => 'Pendapatan penjualan POS order #{{order_number}}'
            ],
            [
                'transaction_type'     => 'pos_sales_hpp',
                'debit_code'           => '5-1000', // Harga Pokok Penjualan (HPP)
                'credit_code'          => '1-2000', // Persediaan Barang Jadi / Bahan Baku
                'description_template' => 'Pencatatan HPP otomatis atas penjualan POS #{{order_number}}'
            ],

            // --- KELOMPOK PEMBELIAN / SUPPLY CHAIN ---
            [
                'transaction_type'     => 'purchase_received_cash',
                'debit_code'           => '1-2000', // Persediaan Bahan Baku (Kain/Aksesoris)
                'credit_code'          => '1-1000', // Kas Utama (Tunai)
                'description_template' => 'Penerimaan inventory tunai via Gudang atas PO #{{po_number}}'
            ],
            [
                'transaction_type'     => 'purchase_received_credit',
                'debit_code'           => '1-2000', // Persediaan Bahan Baku
                'credit_code'          => '3-1000', // Utang Dagang Supplier
                'description_template' => 'Penerimaan inventory kredit atas PO #{{po_number}}'
            ],

            // --- KELOMPOK INVENTARIS & WASTE ---
            [
                'transaction_type'     => 'inventory_adjustment_waste',
                'debit_code'           => '5-3000', // Beban Kerugian Selisih Persediaan
                'credit_code'          => '1-2000', // Persediaan Bahan Baku
                'description_template' => 'Penyesuaian stok bahan rusak/opname: {{material_name}}'
            ]
        ];

        // 2. Loop dan simpan ke database
        foreach ($defaultMappings as $mapping) {
            // Ambil UUID dari master chart of accounts (COA) berdasarkan kode uniknya
            $debitAccount = Account::where('code', $mapping['debit_code'])->first();
            $creditAccount = Account::where('code', $mapping['credit_code'])->first();

            // Gunakan updateOrCreate agar idempotent (aman di-run berkali-kali tanpa duplikat)
            AccountMapping::updateOrCreate(
                ['transaction_type' => $mapping['transaction_type']], // Kunci pencarian unik
                [
                    'debit_account_id'     => $debitAccount?->id,  // Mengisi foreignUuid
                    'credit_account_id'    => $creditAccount?->id, // Mengisi foreignUuid
                    'description_template' => $mapping['description_template']
                ]
            );
        }
    }
}