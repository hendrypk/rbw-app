<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Menu;
use App\Models\OrderItem;
use App\Services\PosService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrderController extends Controller
{
    // Suntikkan PosService ke dalam Controller
    public function __construct(private PosService $posService) {}

    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id'     => 'nullable|uuid|exists:customers,id',
            'voucher_id'      => 'nullable|uuid|exists:vouchers,id',
            'customer_name'   => 'nullable|string|max:100',
            'payment_method'  => 'required|string|in:cash,qris,edc,pending',
            'discount'        => 'nullable|numeric|min:0',
            'transaction_fee' => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string',
            'items'           => 'required|array|min:1',
            'items.*.menu_id' => 'required|uuid|exists:menus,id',
            'items.*.quantity'=> 'required|integer|min:1',
            'action_type'     => 'required|string|in:save,pay',
            'amount_paid'     => 'nullable|numeric|min:0',
        ]);

        try {
            $totalSubtotal = 0;
            $itemsData = [];

            foreach ($request->items as $cartItem) {
                $menu = Menu::with(['prices' => function($query) {
                    $query->where('channel', 'offline')->where('is_active', true);
                }])->findOrFail($cartItem['menu_id']);

                $priceOffline = $menu->prices->first();
                $sellingPrice = $priceOffline ? floatval($priceOffline->selling_price) : 0;

                if ($sellingPrice <= 0) {
                    return response()->json(['success' => false, 'message' => "Menu '{$menu->name}' belum memiliki harga offline aktif."], 422);
                }

                $qty = intval($cartItem['quantity']);
                $itemSubtotal = $sellingPrice * $qty;
                $totalSubtotal += $itemSubtotal;

                $itemsData[] = [
                    'menu_id'  => $menu->id,
                    'quantity' => $qty,
                    'price'    => $sellingPrice,
                    'subtotal' => $itemSubtotal
                ];
            }

            $discount = floatval($request->discount ?? 0);
            $fee = floatval($request->transaction_fee ?? 0);
            $finalTotal = max(0, ($totalSubtotal + $fee) - $discount);

            $status = 'unpaid';
            $amountPaid = floatval($request->amount_paid ?? 0);

            if ($request->action_type === 'pay' && $amountPaid >= $finalTotal) {
                $status = 'paid';
            }

            $outletId = session('active_outlet_id') ?? $request->header('X-Outlet-ID');

            $orderData = [
                'is_self_order'  => false,
                'transaction_at' => now(),
                'outlet_id'      => $outletId,
                'customer_id'    => $request->customer_id ?? null,
                'voucher_id'     => $request->voucher_id ?? null,
                'customer_name'  => $request->customer_name ?? 'Pelanggan POS',
                'subtotal'       => $totalSubtotal,
                'discount'       => $discount,
                'final_total'    => $finalTotal,
                'amount_paid'    => $request->amount_paid,
                'payment_method' => $request->payment_method,
                'status'         => $status,
                'notes'          => $request->notes
            ];

            DB::beginTransaction();

            $order = $this->posService->createOrder($orderData, $itemsData);

            if ($status === 'paid') {
                $this->posService->deductStock($order);

                $this->posService->recordCheckoutJournals($order);

                if (!empty($request->customer_id)) {
                    $this->posService->rewardCustomerPoints($order);
                }
            }

            if (!empty($request->voucher_id)) {
                \App\Models\Voucher::where('id', $request->voucher_id)->increment('used_count');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $status === 'paid' ? 'Transaksi berhasil diproses.' : 'Invoice QRIS tersimpan (Belum Bayar).',
                'data'    => $order
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function payOrder(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|string|in:cash,qris,edc',
            'amount_paid'    => 'required|numeric|min:' . $order->final_total,
        ]);

        try {
            if ($order->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Order ini sudah berstatus lunas sebelumnya.'
                ], 400);
            }

            DB::beginTransaction();

            $order->update([
                'payment_method' => $request->payment_method,
                'amount_paid'    => $request->amount_paid,
                'status'         => 'paid',
            ]);

            $journalType = 'pos_revenue_' . $request->payment_method;
            $replacements = ['order_number' => $order->order_number];

            JournalEntry::createEntryFromMapping(
                type: $journalType,
                j1Amount: (float) $order->final_total,
                reference: $order,
                replacements: $replacements
            );

            if ($order->total_hpp > 0) {
                JournalEntry::createEntryFromMapping(
                    type: 'pos_sales_hpp',
                    j1Amount: (float) $order->total_hpp,
                    reference: $order,
                    replacements: $replacements
                );
            }

            $this->posService->deductStock($order);

            if (!empty($order->customer_id)) {
                $this->posService->rewardCustomerPoints($order);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran order berhasil diproses, stok dikurangi, dan jurnal tercatat.',
                'data'    => $order
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pembayaran',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint untuk mengubah status order QRIS yang tadinya pending/unpaid
     * menjadi paid (lunas) setelah pembayaran sukses diterima dari gateway.
     */
    public function markOrderAsPaid(Request $request, $id): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|string|in:cash,qris,edc',
            'amount_paid'    => 'required|numeric|min:0',
            'customer_id'    => 'nullable|uuid|exists:customers,id',
        ]);

        try {
            $order = Order::findOrFail($id);

            // if ($order->status === 'paid') {
            //     return response()->json(['success' => true, 'message' => 'Order sudah berstatus lunas.'], 400);
            // }

            DB::beginTransaction();

            $order->update([
                'status'         => 'paid',
                'payment_method' => $request->payment_method,
                'amount_paid'    => $request->amount_paid,
                'customer_id'    => $request->customer_id ?? $order->customer_id
            ]);

            $this->posService->deductStock($order);

            $this->posService->recordCheckoutJournals($order);

            if (!empty($order->customer_id)) {
                $this->posService->rewardCustomerPoints($order);
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Pembayaran berhasil dilunasi dan stok diperbarui.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Membatalkan / Void Transaksi POS (Mengembalikan Stok & Membalik Jurnal)
     */
    public function void(string $id, Request $request): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:255']);

        try {
            $reason = $request->input('reason', 'Pembatalan/Void oleh Kasir');
            $this->posService->voidOrder($id, $reason);

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil di-void. Stok dikembalikan dan jurnal keuangan telah dibalik.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function getUnpaidOrders(): JsonResponse
    {
        $orders = Order::with('items')
            ->where('status', 'unpaid')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $orders
        ]);
    }

    public function getPaidInvoices(): JsonResponse
    {
        $invoices = Order::with('items')
            ->where('status', 'paid')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $invoices
        ]);
    }

    public function getOrdersData(): JsonResponse
    {
        try {
            $orders = Order::with('items.menu', 'outlet')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => $orders
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data transaksi',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function userCheckout(Request $request): JsonResponse
    {
        $request->validate([
            'outlet_id' => 'required|uuid|exists:outlets,id',
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|uuid|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'shipping_address' => 'required|string',
            'notes' => 'nullable|string',
            'voucher_id'       => 'nullable|uuid|exists:vouchers,id', // ⬅️ Tambahkan validasi voucher_id
            'discount'         => 'nullable|numeric|min:0',
            'points_to_use'    => 'nullable|integer|min:0', // ⬅️ Tambahkan validasi poin yang digunakan
        ]);

        try {
            DB::beginTransaction();

            $customer = auth('customer')->user();
            $pointsToUse = intval($request->points_to_use ?? 0);

            if ($pointsToUse > 0) {
                if (!$customer) {
                    return response()->json(['status' => 'error', 'message' => 'Anda harus login untuk menggunakan poin.'], 401);
                }
                if ($customer->total_points < $pointsToUse) {
                    return response()->json(['status' => 'error', 'message' => 'Saldo poin Anda tidak mencukupi.'], 422);
                }
            }

            $totalSubtotal = 0;
            $itemsData = [];

            foreach ($request->items as $itemData) {
                $menu = Menu::active()->with(['prices' => function($query) {
                    $query->where('channel', 'offline')->where('is_active', true);
                }])->findOrFail($itemData['menu_id']);

                $priceOffline = $menu->prices->first();
                $sellingPrice = $priceOffline ? floatval($priceOffline->selling_price) : 0;

                if ($sellingPrice <= 0) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Menu '{$menu->name}' belum memiliki harga aktif."
                    ], 422);
                }

                $qty = intval($itemData['quantity']);
                $itemSubtotal = $sellingPrice * $qty;
                $totalSubtotal += $itemSubtotal;

                $itemsData[] = [
                    'menu_id'  => $menu->id,
                    'quantity' => $qty,
                    'price'    => $sellingPrice,
                    'subtotal' => $itemSubtotal
                ];
            }

            $discount = floatval($request->discount ?? 0);
            $finalTotal = max(0, ($totalSubtotal - $discount) - $pointsToUse);

            $orderData = [
                'transaction_at' => now(),
                'outlet_id' => $request->outlet_id,
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                'shipping_address' => $request->shipping_address,
                'customer_id'      => $customer ? $customer->id : null,
                'voucher_id'       => $request->voucher_id ?? null, // ⬅️ Simpan voucher_id
                'subtotal'         => $totalSubtotal,
                'discount'         => $discount,                    // ⬅️ Simpan nominal diskon
                'final_total'      => $finalTotal,                  // ⬅️ Simpan total bersih setelah diskon
                'payment_method'   => 'pending',
                'status'           => 'unpaid',
                'notes'            => $request->notes,
                'is_self_order' => true,
                'amount_paid'      => floatval($request->amount_paid ?? 0), // ⬅️ Tambahkan baris pengaman ini
            ];

            $order = $this->posService->completeOrder($orderData, $itemsData);
            // $this->posService->rewardCustomerPoints($order);

            if (!empty($request->voucher_id)) {
                $voucher = \App\Models\Voucher::find($request->voucher_id);
                if ($voucher) {
                    $voucher->increment('used_count');
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Pesanan berhasil dibuat!',
                'data' => $order->load('items.menu')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses pesanan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getUserOrders(Request $request): JsonResponse
    {
        try {
            $customer = auth('customer')->user();

            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $orders = Order::with(['items.menu'])
                ->where('customer_id', $customer->id)
                ->orderBy('created_at', 'desc')
                ->get();

            // PASTIKAN ADA KATA 'return' DI SINI
            return response()->json([
                'status' => 'success',
                'data' => $orders
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil riwayat pesanan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getOrderDetail(Request $request, string $orderNumber): JsonResponse
    {
        try {
            $customer = auth('customer')->user();

            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Cari order berdasarkan order_number dan pastikan milik customer yang sedang login
            $order = Order::with(['items.menu', 'voucher', 'points', 'outlet'])
                ->where('order_number', $orderNumber)
                ->where('customer_id', $customer->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pesanan tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $order
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat detail pesanan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getSummary(Request $request): JsonResponse
    {
        $outletId = $request->header('X-Outlet-ID') ?? session('active_outlet_id');
        $shiftId  = $request->header('X-Shift-ID') ?? session('active_cashier_shift_id');

        $query = Order::where('outlet_id', $outletId)
            ->where('status', 'paid');

        if ($shiftId) {
            $query->where('cashier_shift_id', $shiftId);
        } else {
            $query->whereDate('created_at', today());
        }

        $orders = $query->with('items')->get();

        $cashOrders = $orders->where('payment_method', 'cash');
        $omzetCash  = $cashOrders->sum('final_total');
        $notaCash   = $cashOrders->count();
        $itemsCash  = $cashOrders->sum(function ($order) {
            return $order->items->sum('quantity');
        });

        $qrisOrders = $orders->where('payment_method', 'qris');
        $omzetQris  = $qrisOrders->sum('final_total');
        $notaQris   = $qrisOrders->count();
        $itemsQris  = $qrisOrders->sum(function ($order) {
            return $order->items->sum('quantity');
        });

        $totalOmzet  = $omzetCash + $omzetQris;
        $totalNota   = $orders->count();
        $totalItems  = $totalImageItems ?? $orders->sum(function ($order) {
            return $order->items->sum('quantity');
        });

        $totalHpp = $orders->sum('total_hpp');
        $totalOverhead = $orders->sum('total_overhead');
        $netProfit = $totalOmzet - ($totalHpp + $totalOverhead);
        $profitMargin = $totalOmzet > 0 ? ($netProfit / $totalOmzet) * 100 : 0;

        $orderIds = $orders->pluck('id');
        $soldProducts = collect();

        if ($orderIds->isNotEmpty()) {
            $soldProducts = OrderItem::whereIn('order_id', $orderIds)
                ->with('menu')
                ->get()
                ->groupBy('menu_id')
                ->map(function ($items) {
                    $firstItem = $items->first();
                    return [
                        'item_name'     => $firstItem->menu->name ?? 'Menu Tidak Ditemukan',
                        'total_qty'     => $items->sum('quantity'),
                        'total_revenue' => $items->sum('subtotal'),
                    ];
                })
                ->sort(function ($a, $b) {
                    if ($b['total_qty'] !== $a['total_qty']) {
                        return $b['total_qty'] <=> $a['total_qty'];
                    }
                    return $b['total_revenue'] <=> $a['total_revenue'];
                })
                ->values();
        }

        $peakHours = $orders->groupBy(function ($order) {
            return Carbon::parse($order->created_at)->format('H:00');
        })->map(function ($group) {
            return [
                'total_transactions' => $group->count(),
                'total_omzet'        => $group->sum('final_total')
            ];
        })->sortKeys();

        $groupedOrders = $orders->groupBy(function ($order) {
            return Carbon::parse($order->created_at)->format('H:00');
        });

        foreach ($groupedOrders as $hour => $group) {
            if ($peakHours->has($hour)) {
                $peakHours[$hour] = [
                    'total_transactions' => $group->count(),
                    'total_omzet'        => $group->sum('final_total')
                ];
            }
        }

        $shiftStartedAt = null;
        if ($shiftId) {
            $shift = \App\Models\CashierShift::find($shiftId);
            $shiftStartedAt = $shift?->opened_at;
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'shift_started_at' => $shiftStartedAt,
                'total_omzet'   => $totalOmzet,
                'omzet_cash'    => $omzetCash,
                'nota_cash'     => $notaCash,
                'items_cash'    => $itemsCash,
                'omzet_qris'    => $omzetQris,
                'nota_qris'     => $notaQris,
                'items_qris'    => $itemsQris,
                'total_nota'    => $totalNota,
                'total_items'   => $totalItems,
                'net_profit'    => $netProfit,
                'profit_margin' => round($profitMargin, 1),
                'sold_products' => $soldProducts,
                'peak_hours'    => $peakHours
            ]
        ]);
    }
}
