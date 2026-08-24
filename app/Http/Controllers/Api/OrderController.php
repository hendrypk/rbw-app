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
            $today = Carbon::today()->format('Ymd');
            $latestOrder = Order::whereDate('created_at', Carbon::today())->orderBy('created_at', 'desc')->first();
            $nextNumber = $latestOrder ? intval(substr($latestOrder->order_number, -4)) + 1 : 1;
            $orderNumber = 'INV-' . $today . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

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
                    $status = 'completed';
                    $journalType = 'pos_revenue_' . $request->payment_method; 
                }
            }

            $orderData = [
                'order_number'   => $orderNumber,
                'customer_name'  => $request->customer_name ?? 'Pelanggan POS',
                'subtotal'       => $totalSubtotal,
                // 'tax'            => $fee,
                'discount'       => $discount,
                'final_total'    => $finalTotal,
                'payment_method' => $request->payment_method,
                'status'         => $status,
                'notes'          => $request->notes
            ];

            // 1. Jalankan core engine POS service (pengurangan stok resep & buat record order)
            $order = $this->posService->completeOrder($orderData, $itemsData);

            // 2. Eksekusi Akuntansi Otomatis via Account Mapping
            $replacements = ['order_number' => $order->order_number];

            if ($order->status === 'completed') {
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
            ->where('status', 'completed') // Sesuaikan ke 'completed'
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

//For User
public function userCheckout(Request $request): JsonResponse
    {
        // 1. Validasi Input (Pastikan menu_id berupa UUID sesuai relasi sistem)
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|uuid|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'shipping_address' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $user = $request->user(); 

            // 2. Generate Nomor Invoice Unik
            $today = Carbon::now()->format('Ymd');
            $timeHash = Carbon::now()->format('His') . '-' . strtoupper(substr(uniqid(), -4));
            $orderNumber = 'INV-OL-' . $today . '-' . $timeHash;
            
            $totalSubtotal = 0;
            $itemsData = [];

            // 3. Looping Item: Validasi Menu Aktif & Ambil Harga Asli
            foreach ($request->items as $itemData) {
                // Hanya izinkan menu yang is_active = true dan punya harga channel offline/gofood
                $menu = Menu::active()->with(['prices' => function($query) {
                    $query->where('channel', 'offline')->where('is_active', true); 
                }])->findOrFail($itemData['menu_id']);

                // Tarik harga dari MenuPrice, BUKAN dari tabel Menu yang tidak punya kolom selling_price
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

            // 4. Susun Format Data Sesuai Kebutuhan PosService
            $orderData = [
                'order_number'     => $orderNumber,
                'customer_name'    => $request->customer_name,
                'customer_phone'   => $request->customer_phone,
                'shipping_address' => $request->shipping_address,
                'user_id'          => $user->id ?? null,
                'subtotal'         => $totalSubtotal,
                'discount'         => 0, 
                'final_total'      => $totalSubtotal,
                'payment_method'   => 'pending', // Menunggu user bayar via QRIS/Gateway
                'status'           => 'unpaid',
                'notes'            => $request->notes
            ];

            // 5. Eksekusi Core Engine! 
            // PosService akan otomatis menghitung HPP, memotong stok bahan baku, 
            // membuat OrderItem, dan mencatat Jurnal Akuntansi Pending.
            $order = $this->posService->completeOrder($orderData, $itemsData);

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
            $customer = $request->user();
            $orders = Order::with(['items.menu'])
                ->where('customer_id', $customer->id)
                ->orderBy('created_at', 'desc')
                ->get();

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
}