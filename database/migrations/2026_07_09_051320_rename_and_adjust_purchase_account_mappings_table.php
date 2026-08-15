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
        // 1. Ambil ID Akun secara dinamis berdasarkan kode COA standard Anda
        $persediaanId = DB::table('accounts')->where('code', '1-2000')->value('id');
        $kasUtamaId   = DB::table('accounts')->where('code', '1-1000')->value('id');
        $utangId      = DB::table('accounts')->where('code', '3-1000')->value('id');

        // Safety check, jika COA belum di-seed, batalkan proses agar tidak error
        if (!$persediaanId || !$utangId) {
            return;
        }

        // 2. Bersihkan nama-nama tipe purchase lama (jika ada) untuk menghindari duplikasi key
        DB::table('account_mappings')
            ->whereIn('transaction_type', [
                'purchase_received_cash', 
                'purchase_received_credit', 
                'purchase_payment_clearance',
                'purchase_paid', // antisipasi nama lama
                'purchase_pending'
            ])->delete();

        // 3. Susun data pemetaan bersih yang baru
        $cleanMappings = [
            [
                'transaction_type'     => 'purchase_received_cash',
                'debit_account_id'     => $persediaanId,  // Barang masuk (Debet)
                'credit_account_id'    => $kasUtamaId,    // Kas berkurang (Kredit)
                'description_template' => 'Penerimaan restock persediaan tunai atas PO #{{po_number}}',
            ],
            [
                'transaction_type'     => 'purchase_received_credit',
                'debit_account_id'     => $persediaanId,  // Barang masuk (Debet)
                'credit_account_id'    => $utangId,       // Utang bertambah (Kredit)
                'description_template' => 'Penerimaan restock persediaan kredit/tempo atas PO #{{po_number}}',
            ],
            [
                'transaction_type'     => 'purchase_payment_clearance',
                'debit_account_id'     => $utangId,       // Utang berkurang (Debet)
                'credit_account_id'    => null,           // NULL -> Di-override akun Kas/Bank pilihan user dari Frontend
                'description_template' => 'Pelunasan utang dagang supplier atas PO #{{po_number}}',
            ],
        ];

        // 4. Insert data ke database dengan UUID manual karena field ID tidak auto-increment
        foreach ($cleanMappings as $map) {
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan ke kondisi semula jika sewaktu-waktu di-rollback
        DB::table('account_mappings')
            ->whereIn('transaction_type', ['purchase_received_cash', 'purchase_received_credit', 'purchase_payment_clearance'])
            ->delete();
    }
};