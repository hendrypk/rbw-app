<?php

namespace App\Services;

use App\Models\Order;
use App\Models\RawMaterial;
use App\Models\StockLedger;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Exception;

class PosService
{
    /**
     * Selesaikan Transaksi POS (Lunas / Pending)
     */
    public function completeOrder(array $orderData, array $itemsData): Order
    {
        return DB::transaction(function () use ($orderData, $itemsData) {
            
            $order = Order::create([
                'customer_id'      => $orderData['customer_id'] ?? null,     // <-- TAMBAHKAN INI
                'voucher_id'      => $orderData['voucher_id'] ?? null,     // <-- TAMBAHKAN INI
                'customer_name'    => $orderData['customer_name'],
                'total_hpp'        => 0,
                'total_overhead'   => 0,
                'subtotal'         => $orderData['subtotal'],
                // 'tax'            => $orderData['tax'],
                'discount'         => $orderData['discount'],
                'final_total'      => $orderData['final_total'],
                'amount_paid'      => $orderData['amount_paid'],
                'payment_method'   => $orderData['payment_method'],
                'status'           => $orderData['status'],
                'notes'            => $orderData['notes']
            ]);

            $accumulatedTotalHpp = 0;
            $accumulatedOverhead = 0;

            foreach ($itemsData as $item) {
                $menu = \App\Models\Menu::with('recipes.rawMaterial')->findOrFail($item['menu_id']);
                $itemQuantity = (float) $item['quantity'];
                $menuHppUnit = 0;

                foreach ($menu->recipes as $recipe) {
                    $material = $recipe->rawMaterial;
                    $totalUsageQty = (float) $recipe->qty_usage * $itemQuantity;

                    $materialUnitCost = (float) $material->avg_cost;
                    $menuHppUnit += ((float) $recipe->qty_usage * $materialUnitCost);

                    $stockBefore = (float) $material->stock_qty;
                    $stockAfter = $stockBefore - $totalUsageQty;

                    StockLedger::create([
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

                $itemOverhead = (float) ($menu->overhead_cost ?? 0) * $itemQuantity;
                $accumulatedOverhead += $itemOverhead;

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