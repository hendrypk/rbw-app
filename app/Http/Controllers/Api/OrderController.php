<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class OrderController extends Controller
{
    public function checkout(Request $request)
    {
        // 1. Validasi Request dari POS Frontend
        $request->validate([
            'customer_name'  => 'nullable|string|max:100',
            'payment_method' => 'required|string|in:cash,qris,edc',
            'discount'       => 'nullable|numeric|min:0',
            'transaction_fee'=> 'nullable|numeric|min:0',
            'notes'          => 'nullable|string',
            'items'          => 'required|array|min:1',
            'items.*.menu_id'=> 'required|uuid|exists:menus,id',
            'items.*.quantity'=> 'required|integer|min:1',
            
            // Tambahan untuk fitur tombol "Bayar" (Lunas / Sebagian)
            'action_type'    => 'required|string|in:save,pay', // save = Simpan, pay = Bayar
            'amount_paid'    => 'nullable|numeric|min:0',      // Nominal uang yang dibayarkan kasir
        ]);

        try {
            DB::beginTransaction();

            // 2. Generate Nomor Order Unik Otomatis (Format: INV-YYYYMMDD-XXXX)
            $today = Carbon::today()->format('Ymd');
            $latestOrder = Order::whereDate('created_at', Carbon::today())
                ->orderBy('created_at', 'desc')
                ->first();

            $nextNumber = 1;
            if ($latestOrder) {
                $lastSequence = substr($latestOrder->order_number, -4);
                $nextNumber = intval($lastSequence) + 1;
            }
            $orderNumber = 'INV-' . $today . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // 3. Inisialisasi Hitungan Keuangan Seluruh Transaksi
            $totalSubtotal = 0;
            $totalHppAll = 0;
            $totalOverheadAll = 0;
            $orderItemsData = [];

            // Loop semua item yang dikirim dari Cart POS
            foreach ($request->items as $cartItem) {
                // Ambil data menu beserta resepnya untuk menghitung HPP real-time saat ini
                $menu = Menu::with(['recipes.rawMaterial', 'prices' => function($query) {
                    $query->where('channel', 'offline')->where('is_active', true);
                }])->findOrFail($cartItem['menu_id']);

                // Ambil harga offline
                $priceOffline = $menu->prices->first();
                $sellingPrice = $priceOffline ? floatval($priceOffline->selling_price) : 0;

                if ($sellingPrice <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => "Menu '{$menu->name}' belum memiliki harga offline yang aktif."
                    ], 422);
                }

                // Hitung total modal dasar (HPP bahan baku + overhead) untuk menu ini
                $menuHpp = 0;
                $menuOverhead = 0;
                
                if ($menu->recipes) {
                    foreach ($menu->recipes as $recipe) {
                        if ($recipe->rawMaterial) {
                            // Kalkulasi HPP: Pemakaian bahan baku * harga beli bahan baku terkini
                            $menuHpp += floatval($recipe->quantity) * floatval($recipe->rawMaterial->buy_price ?? 0);
                        }
                    }
                }
                
                // Jika Anda punya margin overhead tetap di model, bisa diambil dari sana atau default 0
                // Sebagai contoh, diambil dari selisih nett_price model kalkulasi Anda jika ada.

                $qty = intval($cartItem['quantity']);
                $itemSubtotal = $sellingPrice * $qty;

                // Akumulasi total keseluruhan nota
                $totalSubtotal += $itemSubtotal;
                $totalHppAll += ($menuHpp * $qty);
                $totalOverheadAll += ($menuOverhead * $qty);

                // Siapkan data array untuk insert bulk/banyak ke order_items
                $orderItemsData[] = [
                    'id'            => Str::uuid()->toString(),
                    'menu_id'       => $menu->id,
                    'quantity'      => $qty,
                    'price'         => $sellingPrice,
                    'hpp'           => $menuHpp,
                    'overhead_cost' => $menuOverhead,
                    'subtotal'      => $itemSubtotal,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }

            // 4. Kalkulasi Nilai Akhir Nota (Subtotal + Biaya - Diskon)
            $discount = floatval($request->discount ?? 0);
            $fee = floatval($request->transaction_fee ?? 0);
            $finalTotal = ($totalSubtotal + $fee) - $discount;
            if ($finalTotal < 0) $finalTotal = 0;

            // 5. Penentuan Status Order (Sesuai Tombol yang Ditekan)
            $status = 'unpaid'; // Default awal

            if ($request->action_type === 'pay') {
                $amountPaid = floatval($request->amount_paid ?? 0);
                
                // Jika uang yang dibayar kasir >= total tagihan, set Lunas (paid)
                if ($amountPaid >= $finalTotal) {
                    $status = 'paid';
                } else {
                    // Jika dibayar kurang dari total / dibayar sebagian, tetap unpaid
                    $status = 'unpaid';
                }
            }

            // 6. Simpan Data ke Tabel Orders
            $order = Order::create([
                'order_number'   => $orderNumber,
                'customer_name'  => $request->customer_name,
                'total_hpp'      => $totalHppAll,
                'total_overhead' => $totalOverheadAll,
                'subtotal'       => $totalSubtotal,
                'discount'       => $discount,
                'final_total'    => $finalTotal,
                'payment_method' => $request->payment_method,
                'status'         => $status,
                'notes'          => $request->notes, // Jika kolom notes masuk di migration orders Anda
            ]);

            // 7. Simpan Seluruh Item Belanjaan ke Tabel OrderItems dengan Relasi HasMany
            $order->items()->createMany($orderItemsData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil diproses!',
                'data'    => [
                    'order_id'     => $order->id,
                    'order_number' => $order->order_number,
                    'status'       => $order->status,
                    'final_total'  => $order->final_total
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses checkout transaksi.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function getUnpaidOrders()
    {
        $orders = Order::with('items')
            ->where('status', 'unpaid')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function getPaidInvoices()
    {
        $invoices = Order::with('items')
            ->where('status', 'paid')
            ->orderBy('created_at', 'desc')
            ->limit(50) // Batasi 50 riwayat terakhir agar load API kencang
            ->get();

        return response()->json([
            'success' => true,
            'data' => $invoices
        ]);
    }

    public function getOrdersData()
    {
        try {
            $orders = Order::with('items.menu')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $orders
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data transaksi',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}