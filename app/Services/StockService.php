<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RawMaterial;
use App\Models\StockLedger;
use App\Models\JournalEntry; // SUNTIKKAN MODEL JURNAL
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Proses penerimaan PO: update stok + avg cost tiap item, catat ke ledger, + JURNAL AKUNTANSI.
     */
    public function receivePurchaseOrder(PurchaseOrder $po): void
    {
        DB::transaction(function () use ($po) {
            $totalPurchaseValue = 0;

            foreach ($po->items as $item) {
                $material = $item->rawMaterial;
                $factor = (float) $material->conversion_factor;

                // 1. Konversi Qty & Harga ke Base Unit
                $qtyInBaseUnit = $item->qty * $factor;
                $unitCostPerBaseUnit = $item->unit_price / $factor;

                $totalPurchaseValue += ((float) $item->qty * (float) $item->unit_price);

                $this->recordIn(
                    rawMaterial: $material,
                    qty: $qtyInBaseUnit,
                    unitCost: $unitCostPerBaseUnit,
                    referenceId: $item->id,
                    referenceType: PurchaseOrderItem::class,
                    notes: "Penerimaan PO {$po->po_number}"
                );
            }

            // =================================================================
            // 3. AUTOMATION ACCOUNT MAPPING: MULTI-JOURNAL LOGIC (Dinamis)
            // =================================================================
            
            $totalPaid = (float) $po->total_payment; // Nominal DP / Cash yang dibayar
            $remainingDebt = $totalPurchaseValue - $totalPaid; // Sisa yang masuk Utang

            // KONDISI A: LUNAS TOTAL (Paid)
            if ($po->payment_status === 'paid' || $totalPaid >= $totalPurchaseValue) {
                JournalEntry::createEntryFromMapping(
                    type: 'purchase_received_cash',
                    j1Amount: $totalPurchaseValue,
                    reference: $po,
                    replacements: ['po_number' => $po->po_number],
                    customCreditAccountId: $po->payment_account_id // Pakai Kas/Bank pilihan user
                );
            }
            
            // KONDISI B: HUTANG TOTAL (Unpaid)
            elseif ($po->payment_status === 'unpaid' || $totalPaid <= 0) {
                JournalEntry::createEntryFromMapping(
                    type: 'purchase_received_credit',
                    j1Amount: $totalPurchaseValue,
                    reference: $po,
                    replacements: ['po_number' => $po->po_number]
                    // Otomatis Debet: Persediaan, Kredit: Utang Dagang (sesuai mapping)
                );
            }
            
            // KONDISI C: BAYAR SEBAGIAN (Partial) -> Pecah Jadi 2 Jurnal Bersih
            else {
                // Jurnal Bagian Tunai (DP)
                // Persediaan (D) Rp 600rb vs Kas/Bank (K) Rp 600rb
                JournalEntry::createEntryFromMapping(
                    type: 'purchase_received_cash',
                    j1Amount: $totalPaid,
                    reference: $po,
                    replacements: ['po_number' => $po->po_number . ' (Uang Muka)'],
                    customCreditAccountId: $po->payment_account_id
                );

                // Jurnal Bagian Tempo (Sisa)
                // Persediaan (D) Rp 400rb vs Utang Dagang (K) Rp 400rb
                JournalEntry::createEntryFromMapping(
                    type: 'purchase_received_credit',
                    j1Amount: $remainingDebt,
                    reference: $po,
                    replacements: ['po_number' => $po->po_number . ' (Sisa Tempo)']
                );
            }

            // Update status operasional PO
            $po->update([
                'status'        => 'received',
                'received_date' => now()->toDateString(),
            ]);
        });
    }

    /**
     * MEMBATALKAN Penerimaan PO: Mengurangi stok kembali + OTOMATIS REVERSAL JURNAL.
     */
    public function reverseReceivePurchaseOrder(PurchaseOrder $po): void
    {
        DB::transaction(function () use ($po) {
            $po->load('items.rawMaterial');

            foreach ($po->items as $item) {
                $rawMaterial = $item->rawMaterial;
                $factor = (float) $rawMaterial->conversion_factor;
                
                $qtyToSubtract = (float) $item->qty * $factor;
                $stockBefore = (float) $rawMaterial->stock_qty;
                $newStock = max(0, $stockBefore - $qtyToSubtract);

                $rawMaterial->update(['stock_qty' => $newStock]);

                $unitCostPerBaseUnit = $item->unit_price / $factor;

                StockLedger::create([
                    'raw_material_id'  => $rawMaterial->id,
                    'reference_id'     => $item->id,
                    'reference_type'   => PurchaseOrderItem::class,
                    'movement_type'    => 'out',
                    'qty'              => $qtyToSubtract, 
                    'unit_cost'        => $unitCostPerBaseUnit, 
                    'avg_cost_before'  => $rawMaterial->avg_cost,
                    'avg_cost_after'   => $rawMaterial->avg_cost, 
                    'stock_before'     => $stockBefore,
                    'stock_after'      => $newStock,
                    'notes'            => "Pembatalan/Reversal Penerimaan PO {$po->po_number}",
                    'created_at'       => now(),
                ]);
            }

            // 3. AUTOMATION REVERSAL ENGINE: Balik posisi Debet-Kredit jurnal PO ini & soft delete jurnal lama
            JournalEntry::reverseEntriesFor($po, "Pembatalan/Void Penerimaan PO");

            // Update status PO kembali ke draft
            $po->update(['status' => 'draft', 'received_date' => null]);
        });
    }

    /**
     * Catat stok masuk dan hitung moving average baru (Aman di-override manual oleh user kapan saja).
     */
    public function recordIn(
        RawMaterial $rawMaterial,
        float $qty,
        float $unitCost,
        ?string $referenceId = null,
        ?string $referenceType = null,
        ?string $notes = null
    ): StockLedger {
        $stockBefore   = (float) $rawMaterial->stock_qty;
        $avgCostBefore = (float) $rawMaterial->avg_cost; // Jika habis di-edit manual oleh user, baseline baru mengunci di sini

        $newStock = $stockBefore + $qty;
        
        // Rumus Moving Average tetap berjalan aman pasca-edit manual
        $newAvgCost = $newStock > 0
            ? (($stockBefore * $avgCostBefore) + ($qty * $unitCost)) / $newStock
            : $unitCost;

        $rawMaterial->update([
            'stock_qty' => $newStock,
            'avg_cost'  => round($newAvgCost, 4),
            'last_cost' => $unitCost,
        ]);

        return StockLedger::create([
            'raw_material_id'  => $rawMaterial->id,
            'reference_id'     => $referenceId,
            'reference_type'   => $referenceType,
            'movement_type'    => 'in',
            'qty'              => $qty,
            'unit_cost'        => $unitCost,
            'avg_cost_before'  => $avgCostBefore,
            'avg_cost_after'   => round($newAvgCost, 4),
            'stock_before'     => $stockBefore,
            'stock_after'      => $newStock,
            'notes'            => $notes,
            'created_at'       => now(),
        ]);
    }

    /**
     * Catat stok keluar (pemakaian di produksi / adjustment / POS hpp).
     */
    public function recordOut(
        RawMaterial $rawMaterial,
        float $qty,
        ?string $referenceId = null,
        ?string $referenceType = null,
        ?string $notes = null
    ): StockLedger {
        $stockBefore   = (float) $rawMaterial->stock_qty;
        $avgCostBefore = (float) $rawMaterial->avg_cost;
        $newStock      = max(0, $stockBefore - $qty);

        $rawMaterial->update(['stock_qty' => $newStock]);

        return StockLedger::create([
            'raw_material_id'  => $rawMaterial->id,
            'reference_id'     => $referenceId,
            'reference_type'   => $referenceType,
            'movement_type'    => 'out',
            'qty'              => $qty,
            'unit_cost'        => $avgCostBefore,
            'avg_cost_before'  => $avgCostBefore,
            'avg_cost_after'   => $avgCostBefore, // Barang keluar tidak mengubah struktur avg_cost
            'stock_before'     => $stockBefore,
            'stock_after'      => $newStock,
            'notes'            => $notes,
            'created_at'       => now(),
        ]);
    }
}