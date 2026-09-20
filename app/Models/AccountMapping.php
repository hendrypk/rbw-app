<?php

namespace App\Models;

use App\Concerns\BelongsToOutlet;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountMapping extends Model
{
    use HasUuids, BelongsToOutlet;

    protected $fillable = [
        'transaction_type',
        'outlet_id',
        'debit_account_id',
        'credit_account_id',
        'template'
    ];

    // public const TYPE_POS_SALES_REVENUE = 'pos_sales_revenue';
    public const TYPE_POS_REVENUE_CASH = 'pos_revenue_cash';
    public const TYPE_POS_REVENUE_QRIS = 'pos_revenue_qris';
    public const TYPE_POS_SALES_HPP = 'pos_sales_hpp';
    public const TYPE_POS_PENDING = 'pos_pending';
    public const TYPE_POS_SALES_DISCOUNT = 'pos_sales_discount';
    public const TYPE_POS_MDR_FEE = 'pos_mdr_fee';
    public const TYPE_POS_SALES_RETURN = 'pos_sales_return';
    public const TYPE_POS_TAX_VAT = 'pos_tax_vat';

    public const TYPE_PURCHASE_CASH = 'purchase_received_cash';
    public const TYPE_PURCHASE_CREDIT = 'purchase_received_credit';
    public const TYPE_PURCHASE_RETURN = 'purchase_return';

    public const TYPE_INVENTORY_WASTE = 'inventory_adjustment_waste';
    public const TYPE_LOYALTY_REDEMPTION = 'loyalty_point_redemption';

    /**
     * Daftar master mapping transaksi keuangan.
     */
    public const TRANSACTION_MAPPINGS = [
        // [
        //     'transaction_type'     => self::TYPE_POS_SALES_REVENUE,
        //     'debit_account_id'     => null,
        //     'credit_account_id'    => 'pendapatan',
        //     'description_template' => 'Pendapatan penjualan POS order #{{order_number}}',
        // ],
        // [
        //     'transaction_type'     => self::TYPE_POS_REVENUE_CASH,
        //     'debit_account_id'     => 'kas_utama',
        //     'credit_account_id'    => 'pendapatan',
        //     'description_template' => 'Penerimaan pembayaran kas tunai order POS #{{order_number}}',
        // ],
        // [
        //     'transaction_type'     => self::TYPE_POS_REVENUE_QRIS,
        //     'debit_account_id'     => 'kas_utama',
        //     'credit_account_id'    => 'pendapatan',
        //     'description_template' => 'Penerimaan pembayaran QRIS order POS #{{order_number}}',
        // ],
        // [
        //     'transaction_type'     => self::TYPE_POS_SALES_HPP,
        //     'debit_account_id'     => 'hpp',
        //     'credit_account_id'    => 'persediaan',
        //     'description_template' => 'Alokasi pengeluaran bahan baku / HPP otomatis atas POS #{{order_number}}',
        // ],
        // [
        //     'transaction_type'     => self::TYPE_POS_PENDING,
        //     'debit_account_id'     => 'piutang',
        //     'credit_account_id'    => 'unearned_revenue',
        //     'description_template' => 'Pencatatan piutang pesanan POS pending #{{order_number}}',
        // ],
        // [
        //     'transaction_type'     => self::TYPE_POS_SALES_DISCOUNT,
        //     'debit_account_id'     => 'diskon_penjualan',
        //     'credit_account_id'    => null,
        //     'description_template' => 'Potongan diskon penjualan POS #{{order_number}}',
        // ],
        // [
        //     'transaction_type'     => self::TYPE_POS_MDR_FEE,
        //     'debit_account_id'     => 'biaya_mdr',
        //     'credit_account_id'    => 'kas_utama',
        //     'description_template' => 'Biaya MDR QRIS/EDC order POS #{{order_number}}',
        // ],
        // [
        //     'transaction_type'     => self::TYPE_POS_SALES_RETURN,
        //     'debit_account_id'     => 'retur_penjualan',
        //     'credit_account_id'    => 'piutang',
        //     'description_template' => 'Retur penjualan barang order POS #{{order_number}}',
        // ],
        // [
        //     'transaction_type'     => self::TYPE_POS_TAX_VAT,
        //     'debit_account_id'     => null,
        //     'credit_account_id'    => 'hutang_pajak',
        //     'description_template' => 'Pemungutan pajak/PB1 order POS #{{order_number}}',
        // ],
        // [
        //     'transaction_type'     => self::TYPE_PURCHASE_CASH,
        //     'debit_account_id'     => 'persediaan',
        //     'credit_account_id'    => 'kas_utama',
        //     'description_template' => 'Penerimaan restock persediaan tunai atas PO #{{po_number}}',
        // ],
        // [
        //     'transaction_type'     => self::TYPE_PURCHASE_CREDIT,
        //     'debit_account_id'     => 'persediaan',
        //     'credit_account_id'    => 'utang',
        //     'description_template' => 'Penerimaan restock persediaan kredit atas PO #{{po_number}}',
        // ],
        // [
        //     'transaction_type'     => self::TYPE_PURCHASE_RETURN,
        //     'debit_account_id'     => 'utang',
        //     'credit_account_id'    => 'persediaan',
        //     'description_template' => 'Retur pembelian bahan baku atas PO #{{po_number}}',
        // ],
        // [
        //     'transaction_type'     => self::TYPE_INVENTORY_WASTE,
        //     'debit_account_id'     => 'waste',
        //     'credit_account_id'    => 'persediaan',
        //     'description_template' => 'Penyesuaian stok bahan rusak/opname: {{material_name}}',
        // ],
        // [
        //     'transaction_type'     => self::TYPE_LOYALTY_REDEMPTION,
        //     'debit_account_id'     => 'biaya_loyalty',
        //     'credit_account_id'    => 'kas_utama',
        //     'description_template' => 'Biaya penukaran poin loyalitas pelanggan #{{order_number}}',
        // ],
    ];

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
    }

    /**
     * Helper untuk membuat deskripsi teks dinamis dari template
     */
    public function parseDescription(array $replacements): string
    {
        // Gunakan $this->template sesuai nama kolom fisik MySQL
        $template = $this->template ?? 'Transaksi Otomatis: ' . $this->transaction_event;

        foreach ($replacements as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }

        return $template;
    }
}
