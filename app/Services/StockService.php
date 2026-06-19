<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RawMaterial;
use App\Models\StockLedger;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Proses penerimaan PO: update stok + avg cost tiap item, catat ke ledger.
     */
    public function receivePurchaseOrder(PurchaseOrder $po): void
    {
        DB::transaction(function () use ($po) {
            foreach ($po->items as $item) {
                $material = $item->rawMaterial;
                $factor = (float) $material->conversion_factor;

                // 1. Konversi Qty: Qty PO (e.g., Liter) * Factor (e.g., 1000) = ml
                $qtyInBaseUnit = $item->qty * $factor;

                // 2. Konversi Harga: Harga per unit beli / Factor = Harga per unit dasar
                // Contoh: 23.000 / 1000 = 23 (Harga per ml)
                $unitCostPerBaseUnit = $item->unit_price / $factor;

                $this->recordIn(
                    rawMaterial: $material,
                    qty: $qtyInBaseUnit,
                    unitCost: $unitCostPerBaseUnit,
                    referenceId: $item->id,
                    referenceType: PurchaseOrderItem::class,
                    notes: "Penerimaan PO {$po->po_number}"
                );
            }

            $po->update([
                'status'        => 'received',
                'received_date' => now()->toDateString(),
            ]);
        });
    }

    /**
     * MEMBATALKAN Penerimaan PO: Mengurangi stok kembali dengan konversi satuan.
     */
    public function reverseReceivePurchaseOrder(PurchaseOrder $po): void
    {
        DB::transaction(function () use ($po) {
            // Pastikan item dan relasi rawMaterial sudah ter-load
            $po->load('items.rawMaterial');

            foreach ($po->items as $item) {
                $rawMaterial = $item->rawMaterial;
                $factor = (float) $rawMaterial->conversion_factor;
                
                // PENTING: Konversi qty ke base unit agar sesuai dengan saat receive
                $qtyToSubtract = (float) $item->qty * $factor;
                
                $stockBefore = (float) $rawMaterial->stock_qty;
                
                // Kurangi stok (mencegah stok minus)
                $newStock = max(0, $stockBefore - $qtyToSubtract);

                // Update Raw Material
                $rawMaterial->update(['stock_qty' => $newStock]);

                // Hitung harga per unit dasar untuk ledger (jika diperlukan untuk audit)
                $unitCostPerBaseUnit = $item->unit_price / $factor;

                // Catat ke Ledger
                StockLedger::create([
                    'raw_material_id'  => $rawMaterial->id,
                    'reference_id'     => $item->id,
                    'reference_type'   => PurchaseOrderItem::class,
                    'movement_type'    => 'out',
                    'qty'              => $qtyToSubtract, // Simpan dalam Base Unit
                    'unit_cost'        => $unitCostPerBaseUnit, 
                    'avg_cost_before'  => $rawMaterial->avg_cost,
                    'avg_cost_after'   => $rawMaterial->avg_cost, 
                    'stock_before'     => $stockBefore,
                    'stock_after'      => $newStock,
                    'notes'            => "Pembatalan/Reversal Penerimaan PO {$po->po_number}",
                    'created_at'       => now(),
                ]);
            }

            // Update status PO kembali ke draft
            $po->update(['status' => 'draft', 'received_date' => null]);
        });
    }

    /**
     * Catat stok masuk dan hitung moving average baru.
     *
     * Rumus moving average:
     *   avg_baru = (stok_lama × avg_lama + qty_beli × harga_beli) / (stok_lama + qty_beli)
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
        $avgCostBefore = (float) $rawMaterial->avg_cost;

        $newStock = $stockBefore + $qty;
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
     * Catat stok keluar (pemakaian di produksi / adjustment).
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
            'avg_cost_after'   => $avgCostBefore,
            'stock_before'     => $stockBefore,
            'stock_after'      => $newStock,
            'notes'            => $notes,
            'created_at'       => now(),
        ]);
    }
}