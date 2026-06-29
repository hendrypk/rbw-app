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
            'piutang'          => Account::where('code', '1-1100')->first()->id,
            'persediaan'        => Account::where('code', '1-2000')->first()->id,
            'pendapatan'        => Account::where('code', '2-1000')->first()->id,
            'utang'             => Account::where('code', '3-1000')->first()->id,
            'unearned_revenue'  => Account::where('code', '3-1200')->first()->id,
            'hpp'               => Account::where('code', '5-1000')->first()->id,
        ];

        // 3. Masukkan data ke Account Mappings dengan aman
        $mappings = [
            [
                'transaction_type' => 'pos_paid',
                'j1_debit_account_id'  => null, // Pilihan akun kas dinamis saat transaksi
                'j1_credit_account_id' => $accounts['pendapatan'],
                'j2_debit_account_id'  => $accounts['persediaan'],
                'j2_credit_account_id' => $accounts['hpp'],
                'j1_description_template' => 'Pendapatan penjualan POS lunas #{{order_number}}',
                'j2_description_template' => 'Alokasi pengeluaran bahan/HPP atas POS lunas #{{order_number}}',
            ],
            [
                'transaction_type' => 'pos_pending',
                'j1_debit_account_id'  => $accounts['piutang'],
                'j1_credit_account_id' => $accounts['unearned_revenue'],
                'j2_debit_account_id'  => null,
                'j2_credit_account_id' => null,
                'j1_description_template' => 'Pencatatan piutang pesanan POS pending #{{order_number}}',
                'j2_description_template' => null,
            ],
            [
                'transaction_type' => 'purchase_paid',
                'j1_debit_account_id'  => $accounts['persediaan'],
                'j1_credit_account_id' => Account::where('code', '1-1000')->first()->id, // Default ke Kas Utama
                'j2_debit_account_id'  => null,
                'j2_credit_account_id' => null,
                'j1_description_template' => 'Penerimaan restock persediaan tunai atas PO #{{po_number}}',
                'j2_description_template' => null,
            ],
            [
                'transaction_type' => 'purchase_pending',
                'j1_debit_account_id'  => $accounts['persediaan'],
                'j1_credit_account_id' => $accounts['utang'],
                'j2_debit_account_id'  => null,
                'j2_credit_account_id' => null,
                'j1_description_template' => 'Penerimaan restock persediaan kredit atas PO #{{po_number}}',
                'j2_description_template' => null,
            ],
        ];

        foreach ($mappings as $map) {
            AccountMapping::updateOrCreate(['transaction_type' => $map['transaction_type']], $map);
        }
    }
}