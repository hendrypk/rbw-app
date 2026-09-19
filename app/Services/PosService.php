<?php

namespace App\Services;

use App\Models\Order;
use App\Models\RawMaterial;
use App\Models\StockLedger;
use App\Models\JournalEntry;
use App\Models\Menu;
use Illuminate\Support\Facades\DB;
use Exception;

class PosService
{
    /**
     * 1. Fungsi khusus untuk membuat Record Order & Order Items (Kalkulasi HPP saja, tidak potong stok)
     */
    public function createOrder(array $orderData, array $itemsData): Order
    {
        return DB::transaction(function () use ($orderData, $itemsData) {
            $order = Order::create($orderData);

            $accumulatedTotalHpp = 0;
            $accumulatedOverhead = 0;

            foreach ($itemsData as $item) {
                $menu = Menu::with('recipes.rawMaterial')->findOrFail($item['menu_id']);
                $itemQuantity = (float) $item['quantity'];

                // Kalkulasi HPP
                $menuHppUnit = 0;
                foreach ($menu->recipes as $recipe) {
                    $materialUnitCost = (float) $recipe->rawMaterial->avg_cost;
                    $menuHppUnit += ((float) $recipe->qty_usage * $materialUnitCost);
                }

                // Kalkulasi Overhead
                $itemOverhead = (float) ($menu->overhead_cost ?? 0) * $itemQuantity;
                $accumulatedOverhead += $itemOverhead;

                // Simpan Item
                $order->items()->create([
                    'menu_id'       => $menu->id,
                    'quantity'      => $itemQuantity,
                    'price'         => $item['price'],
                    'hpp'           => $menuHppUnit,
                    'overhead_cost' => $menu->overhead_cost ?? 0,
                    'subtotal'      => $item['subtotal']
                ]);

                $accumulatedTotalHpp += ($menuHppUnit * $itemQuantity);
            }

            $order->update([
                'total_hpp'      => $accumulatedTotalHpp,
                'total_overhead' => $accumulatedOverhead,
            ]);

            return $order;
        });
    }

    /**
     * 2. Fungsi khusus untuk mengeksekusi Pemotongan Stok Bahan Baku
     */
    public function deductStock(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->loadMissing('items.menu.recipes.rawMaterial');

            foreach ($order->items as $orderItem) {
                $menu = $orderItem->menu;
                $itemQuantity = (float) $orderItem->quantity;

                foreach ($menu->recipes as $recipe) {
                    $material = $recipe->rawMaterial;
                    $totalUsageQty = (float) $recipe->qty_usage * $itemQuantity;
                    $materialUnitCost = (float) $material->avg_cost;

                    $stockBefore = (float) $material->stock_qty;
                    $stockAfter = $stockBefore - $totalUsageQty;

                    StockLedger::create([
                        'outlet_id'       => $order->outlet_id,
                        'raw_material_id' => $material->id,
                        'reference_id'    => $order->id,
                        'reference_type'  => Order::class,
                        'movement_type'   => 'out',
                        'qty'             => $totalUsageQty,
                        'unit_cost'       => $materialUnitCost,
                        'avg_cost_before' => $materialUnitCost,
                        'avg_cost_after'  => $materialUnitCost,
                        'stock_before'    => $stockBefore,
                        'stock_after'     => $stockAfter,
                        'notes'           => "Pengurangan stok untuk menu '{$menu->name}' pada POS #{$order->order_number}",
                        'created_at'      => now(),
                    ]);

                    $material->update(['stock_qty' => $stockAfter]);
                }
            }
        });
    }

    /**
     * 3. Fungsi khusus untuk Jurnal saat Checkout (HPP & Piutang/Kas)
     */
    public function recordCheckoutJournals(Order $order): void
    {
        $replacements = ['order_number' => $order->order_number];

        // Ayat 1: Finansial (Lunas -> Kas, Belum Lunas -> Piutang)
        if ($order->status === 'paid') {
            JournalEntry::createEntryFromMapping(
                type: 'pos_revenue_' . $order->payment_method,
                j1Amount: (float) $order->final_total,
                reference: $order,
                replacements: $replacements
            );
            JournalEntry::createEntryFromMapping(
                type: 'pos_sales_hpp',
                j1Amount: (float) $order->total_hpp,
                reference: $order,
                replacements: $replacements
            );

        } else {
            JournalEntry::createEntryFromMapping(
                type: 'pos_pending',
                j1Amount: (float) $order->final_total,
                reference: $order,
                replacements: $replacements
            );
        }

        // Ayat 2: Pencatatan HPP (Karena stok sudah dipotong, HPP diakui)
        // if ($order->total_hpp > 0) {
        //     JournalEntry::createEntryFromMapping(
        //         type: 'pos_sales_hpp',
        //         j1Amount: (float) $order->total_hpp,
        //         reference: $order,
        //         replacements: $replacements
        //     );
        // }
    }

    /**
     * 4. Fungsi khusus untuk Jurnal Pelunasan (Settlement dari Pending -> Paid)
     */
    public function recordPaymentSettlementJournal(Order $order): void
    {
        // Jurnal penerimaan kas dari piutang (misal QRIS cair)
        JournalEntry::createEntryFromMapping(
            type: 'pos_revenue_' . $order->payment_method,
            j1Amount: (float) $order->final_total,
            reference: $order,
            replacements: ['order_number' => $order->order_number]
        );
    }

    /**
     * VOID / CANCEL / HAPUS TRANSAKSI POS (Reverse Total Proses)
     */
    public function voidOrder(string $orderId, string $reason = 'Pembatalan Transaksi oleh Kasir'): void
    {
        DB::transaction(function () use ($orderId, $reason) {
            $order = Order::with('items.menu.recipes.rawMaterial')->findOrFail($orderId);

            if ($order->status === 'voided') {
                throw new Exception("Transaksi ini sudah dibatalkan sebelumnya.");
            }

            // 1. KEMBALIKAN STOK BAHAN BAKU YANG PERNAH DIKURANGI
            foreach ($order->items as $item) {
                foreach ($item->menu->recipes as $recipe) {
                    $material = $recipe->rawMaterial;
                    $returnedQty = (float) $recipe->qty_usage * (float) $item->quantity;

                    $stockBefore = (float) $material->stock_qty;
                    $stockAfter = $stockBefore + $returnedQty;

                    // Catat mutasi pembalikan masuk ke Ledger
                    StockLedger::create([
                        'outlet_id'       => $order->outlet_id,
                        'raw_material_id' => $material->id,
                        'reference_id'    => $order->id,
                        'reference_type'  => Order::class,
                        'movement_type'   => 'in',
                        'qty'             => $returnedQty,
                        'unit_cost'       => (float) $material->avg_cost,
                        'avg_cost_before' => (float) $material->avg_cost,
                        'avg_cost_after'  => (float) $material->avg_cost,
                        'stock_before'    => $stockBefore,
                        'stock_after'     => $stockAfter,
                        'notes'           => "[VOID ORDER] Pengembalian bahan akibat batal POS #{$order->order_number}",
                        'created_at'      => now(),
                    ]);

                    // Kembalikan angka stok ke master tabel
                    $material->update([
                        'stock_qty' => $stockAfter
                    ]);
                }
            }

            // 2. BALIK POSISI JURNAL KEUANGAN (REVERSAL ENGINE)
            JournalEntry::reverseEntriesFor($order, $reason);

            // 3. Ubah status order menjadi voided
            $order->update(['status' => 'voided']);
            $order->delete();
        });
    }

    public function rewardCustomerPoints(Order $order)
    {
        if (!$order->customer_id || $order->status !== 'paid') {
            return;
        }

        $amountForPoints = $order->final_total;
        $earnedPoints = floor($amountForPoints / 1000) * 10;

        if ($earnedPoints > 0) {
            $existingPoint = \App\Models\CustomerPoint::where('order_id', $order->id)
                ->where('type', 'earned')
                ->exists();

            if (!$existingPoint) {
                \App\Models\CustomerPoint::create([
                    'customer_id' => $order->customer_id,
                    'order_id'    => $order->id,
                    'points'      => $earnedPoints,
                    'type'        => 'earned',
                    'description' => "Poin dari transaksi #{$order->order_number}"
                ]);

                $customer = \App\Models\Customer::find($order->customer_id);
                if ($customer) {
                    $customer->increment('total_points', $earnedPoints);
                }
            }
        }
    }
}
