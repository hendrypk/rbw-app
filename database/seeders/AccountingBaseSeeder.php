<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountMapping;
use Illuminate\Database\Seeder;

class AccountingBaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Definisikan dan buat Master Rekening (COA) terlebih dahulu
        $coa = [
            ['category' => '1', 'account_number' => '1000', 'name' => 'Kas & Bank (Utama/POS)', 'normal_balance' => 'debit'],
            ['category' => '1', 'account_number' => '1100', 'name' => 'Piutang Dagang Pelanggan', 'normal_balance' => 'debit'],
            ['category' => '1', 'account_number' => '2000', 'name' => 'Persediaan Bahan Baku / Barang', 'normal_balance' => 'debit'],
            ['category' => '2', 'account_number' => '1000', 'name' => 'Pendapatan Penjualan POS', 'normal_balance' => 'credit'],
            ['category' => '3', 'account_number' => '1000', 'name' => 'Utang Dagang Supplier', 'normal_balance' => 'credit'],
            ['category' => '3', 'account_number' => '1200', 'name' => 'Pendapatan Diterima di Muka', 'normal_balance' => 'credit'],
            ['category' => '4', 'account_number' => '1000', 'name' => 'Modal Pemilik', 'normal_balance' => 'credit'],
            ['category' => '5', 'account_number' => '1000', 'name' => 'Harga Pokok Penjualan (Beban Pokok)', 'normal_balance' => 'debit'],
            ['category' => '5', 'account_number' => '3000', 'name' => 'Beban Kerugian Selisih Persediaan', 'normal_balance' => 'debit'],
        ];

        foreach ($coa as $item) {
            // Menggunakan updateOrCreate agar boot() model men-generate kolom 'code' (cth: "1-1000")
            Account::updateOrCreate(
                ['category' => $item['category'], 'account_number' => $item['account_number']],
                ['name' => $item['name'], 'normal_balance' => $item['normal_balance'], 'is_active' => true]
            );
        }

        // 2. Ambil ID akun yang baru saja dipastikan ada menggunakan format 'code' hasil boot()
        $accounts = [
            'kas_utama'        => Account::where('code', '1-1000')->first()->id,
            'piutang'          => Account::where('code', '1-1100')->first()->id,
            'persediaan'       => Account::where('code', '1-2000')->first()->id,
            'pendapatan'       => Account::where('code', '2-1000')->first()->id,
            'utang'            => Account::where('code', '3-1000')->first()->id,
            'unearned_revenue' => Account::where('code', '3-1200')->first()->id,
            'hpp'              => Account::where('code', '5-1000')->first()->id,
            'waste'            => Account::where('code', '5-3000')->first()->id,
        ];

        // 3. Masukkan data ke Account Mappings dengan format Single-Entry yang baru
        $mappings = [
            // --- KELOMPOK PENJUALAN POS ---
            [
                'transaction_type'     => 'pos_sales_revenue',
                'debit_account_id'     => null, // Pilihan akun kas/bank dinamis saat transaksi checkout
                'credit_account_id'    => $accounts['pendapatan'],
                'description_template' => 'Pendapatan penjualan POS order #{{order_number}}',
            ],
            [
                'transaction_type'     => 'pos_sales_hpp',
                'debit_account_id'     => $accounts['hpp'],        // Beban bertambah (Debet)
                'credit_account_id'    => $accounts['persediaan'], // Stok berkurang (Kredit)
                'description_template' => 'Alokasi pengeluaran bahan baku / HPP otomatis atas POS #{{order_number}}',
            ],
            [
                'transaction_type'     => 'pos_pending',
                'debit_account_id'     => $accounts['piutang'],
                'credit_account_id'    => $accounts['unearned_revenue'],
                'description_template' => 'Pencatatan piutang pesanan POS pending #{{order_number}}',
            ],

            // --- KELOMPOK PEMBELIAN / SUPPLY CHAIN ---
            [
                'transaction_type'     => 'purchase_received_cash',
                'debit_account_id'     => $accounts['persediaan'],
                'credit_account_id'    => $accounts['kas_utama'],
                'description_template' => 'Penerimaan restock persediaan tunai atas PO #{{po_number}}',
            ],
            [
                'transaction_type'     => 'purchase_received_credit',
                'debit_account_id'     => $accounts['persediaan'],
                'credit_account_id'    => $accounts['utang'],
                'description_template' => 'Penerimaan restock persediaan kredit atas PO #{{po_number}}',
            ],

            // --- KELOMPOK INVENTARIS & WASTE ---
            [
                'transaction_type'     => 'inventory_adjustment_waste',
                'debit_account_id'     => $accounts['waste'],
                'credit_account_id'    => $accounts['persediaan'],
                'description_template' => 'Penyesuaian stok bahan rusak/opname: {{material_name}}',
            ]
        ];

        foreach ($mappings as $map) {
            AccountMapping::updateOrCreate(
                ['transaction_type' => $map['transaction_type']], 
                $map
            );
        }
    }
}