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
            'customer_id'     => 'nullable|uuid|exists:customers,id', // Validasi customer_id jika dipilih
            'voucher_id'      => 'nullable|uuid|exists:vouchers,id', // ⬅️ Tambahkan validasi voucher_id
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
            // Generate Invoice unik (INV-YYYYMMDD-XXXX)
            $mmyy = Carbon::now()->format('my'); // 'm' = bulan (08), 'y' = tahun 2 digit (26)
            $random4Digit = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT); // Angka acak 0000 - 9999

            // Hasilnya misal: 08264912
            // Pengumpulan data item pesanan
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

            // Hitung nilai bersih
            $discount = floatval($request->discount ?? 0);
            $fee = floatval($request->transaction_fee ?? 0);
            $finalTotal = max(0, ($totalSubtotal + $fee) - $discount);

            // Sinkronisasi status
            $status = 'unpaid';
            $journalType = 'pos_pending'; 

            if ($request->action_type === 'pay') {
                $amountPaid = floatval($request->amount_paid ?? 0);
                if ($amountPaid >= $finalTotal) {
                    $status = 'paid';
                    $journalType = 'pos_revenue_' . $request->payment_method; 
                }
            }

            $orderData = [
                'customer_id' => $request->customer_id ?? null,
                'voucher_id'     => $request->voucher_id ?? null,
                'customer_name'  => $request->customer_name ?? 'Pelanggan POS',
                'subtotal'       => $totalSubtotal,
                // 'tax'            => $fee,
                'discount'       => $discount,
                'final_total'    => $finalTotal,
                'amount_paid'    => $request->amount_paid,
                'payment_method' => $request->payment_method,
                'status'         => $status,
                'notes'          => $request->notes
            ];

            // 1. Jalankan core engine POS service (pengurangan stok resep & buat record order)
            $order = $this->posService->completeOrder($orderData, $itemsData);

            if (!empty($request->voucher_id)) {
                $voucher = \App\Models\Voucher::find($request->voucher_id);
                if ($voucher) {
                    $voucher->increment('used_count');
                }
            }

            // 2. Eksekusi Akuntansi Otomatis via Account Mapping
            $replacements = ['order_number' => $order->order_number];

            if ($order->status === 'paid') {
                // Jurnal Ayat 1: Sisi Finansial Penerimaan Uang
                JournalEntry::createEntryFromMapping(
                    type: $journalType, 
                    j1Amount: (float) $order->final_total,
                    reference: $order,
                    replacements: $replacements
                );

                // Jurnal Ayat 2: Sisi Pengurangan Inventaris Dapur (HPP)
                if ($order->total_hpp > 0) {
                    JournalEntry::createEntryFromMapping(
                        type: 'pos_sales_hpp',
                        j1Amount: (float) $order->total_hpp,
                        reference: $order,
                        replacements: $replacements
                    );
                }

                // if (strtolower($request->payment_method) === 'cash' && !empty($request->customer_id)) {
                //     $this->posService->rewardCustomerPoints($order);
                // }
                
            } else {
                // Jurnal Kasus Pending / Piutang
                JournalEntry::createEntryFromMapping(
                    type: 'pos_pending',
                    j1Amount: (float) $order->final_total,
                    reference: $order,
                    replacements: $replacements
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Checkout berhasil diproses, stok berkurang, dan jurnal tercatat.',
                'data'    => ['order_id' => $order->id, 'order_number' => $order->order_number, 'final_total' => $order->final_total]
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint untuk mengubah status order QRIS yang tadinya pending/unpaid 
     * menjadi paid (lunas) setelah pembayaran sukses diterima dari gateway.
     */
    public function markOrderAsPaid(Request $request, $id): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|string|in:qris,edc,cash',
            'customer_id'    => 'nullable|uuid|exists:customers,id', // Tambahkan opsional ini jika ingin diperbarui saat pelunasan
        ]);

        try {
            $order = Order::findOrFail($id);

            // Jika sudah lunas, cegah duplikasi jurnal
            if ($order->status === 'paid') {
                return response()->json(['success' => true, 'message' => 'Order sudah berstatus lunas sebelumnya.']);
            }

            // 1. Update status order menjadi paid
            $order->update([
                'status'         => 'paid',
                'payment_method' => $request->payment_method,
                'customer_id'    => $request->customer_id ?? $order->customer_id // Pertahankan atau perbarui jika dikirim
            ]);

            $replacements = ['order_number' => $order->order_number];
            $journalType = 'pos_revenue_' . $request->payment_method; 

            // 2. Catat Jurnal Finansial Pendapatan
            JournalEntry::createEntryFromMapping(
                type: $journalType, 
                j1Amount: (float) $order->final_total,
                reference: $order,
                replacements: $replacements
            );

            $this->posService->rewardCustomerPoints($order);
            
            return response()->json([
                'success' => true,
                'message' => 'Status order berhasil diubah menjadi lunas (paid) dan jurnal tercatat.',
                'data'    => ['order_id' => $order->id, 'order_number' => $order->order_number]
            ], 200);

        } catch (\Exception $e) {
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
            $orders = Order::with('items.menu')
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
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|uuid|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'shipping_address' => 'required|string',
            'notes' => 'nullable|string',
            'voucher_id'       => 'nullable|uuid|exists:vouchers,id', // ⬅️ Tambahkan validasi voucher_id
            'discount'         => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $customer = auth('customer')->user();

            $today = Carbon::now()->format('Ymd');
            $timeHash = Carbon::now()->format('His') . '-' . strtoupper(substr(uniqid(), -4));
            
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
            $finalTotal = max(0, $totalSubtotal - $discount);

            $orderData = [
                'customer_name'    => $request->customer_name,
                'customer_phone'   => $request->customer_phone,
                'shipping_address' => $request->shipping_address,
                'customer_id'      => $customer ? $customer->id : null, 
                'voucher_id'       => $request->voucher_id ?? null, // ⬅️ Simpan voucher_id
                'subtotal'         => $totalSubtotal,
                'discount'         => $discount,                    // ⬅️ Simpan nominal diskon
                'final_total'      => $finalTotal,                  // ⬅️ Simpan total bersih setelah diskon
                'payment_method'   => 'pending', 
                'status'           => 'unpaid',
                'notes'            => $request->notes
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
            $order = Order::with(['items.menu', 'voucher', 'points'])
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
}