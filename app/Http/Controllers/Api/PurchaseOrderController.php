<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function __construct(private StockService $stockService) {}

    public function index(Request $request): JsonResponse
    {
        $orders = PurchaseOrder::with('supplier', 'items.rawMaterial')
            ->when($request->status, fn($q) => $q->byStatus($request->status))
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->orderByDesc('order_date')
            ->paginate($request->per_page ?? 15);

        return response()->json($orders);
    }

public function store(Request $request, StockService $stockService): JsonResponse
{
    $data = $request->validate([
        'supplier_id'             => 'required|uuid|exists:suppliers,id',
        'order_date'              => 'required|date',
        'notes'                   => 'nullable|string',
        'items'                   => 'required|array|min:1',
        'items.*.raw_material_id' => 'required|uuid|exists:raw_materials,id',
        'items.*.qty'             => 'required|numeric|min:0.0001',
        'items.*.unit_price'      => 'required|numeric|min:0',
        'status'                  => 'required|in:draft,received',
        
        // Menangkap request dari form modal vue
        'payment_account_id'      => 'nullable|uuid|exists:accounts,id',
        'amount_paid'             => 'nullable|required_with:payment_account_id|numeric|min:0',
    ]);

    $po = DB::transaction(function () use ($data, $stockService) {
        // 1. Buat Header PO (Map dari request form ke field asli DB)
        $po = PurchaseOrder::create([
            'supplier_id'        => $data['supplier_id'],
            'order_date'         => $data['order_date'],
            'notes'              => $data['notes'] ?? null,
            'status'             => $data['status'],
            'payment_account_id' => $data['payment_account_id'] ?? null,
            'total_payment'      => $data['payment_account_id'] ? (float) $data['amount_paid'] : 0, // Set ke total_payment
        ]);

        // 2. Insert detail items
        foreach ($data['items'] as $item) {
            $subtotal = (float) $item['qty'] * (float) $item['unit_price'];
            $po->items()->create(array_merge($item, ['subtotal' => $subtotal]));
        }

        // 3. Kalkulasi Total Bruto PO
        $totalAmount = (float) $po->items()->sum('subtotal');
        $po->total_amount = $totalAmount;

        // 4. LOGIKA OTOMATIS: Bandingkan total_payment vs total_amount
        $paid = $po->total_payment;
        
        if ($paid <= 0) {
            $po->payment_status = 'unpaid';
            $po->total_payment = 0;
            $po->payment_account_id = null; 
        } elseif ($paid >= $totalAmount) {
            $po->payment_status = 'paid';
            $po->total_payment = $totalAmount; // Cegah overpayment tak sengaja
        } else {
            $po->payment_status = 'partial';
        }

        $po->save();
        
        // 5. Jika status operasional langsung 'received' (Barang Masuk Gudang)
        if ($data['status'] === 'received') {
            $po->load('items.rawMaterial');
            $stockService->receivePurchaseOrder($po);

            // 6. OTOMATISASI JURNAL AKUNTANSI (Double-Entry)
            if ($po->payment_status === 'paid') {
                // Lunas Direct: Persediaan (D) vs Kas/Bank User (K)
                JournalEntry::createEntryFromMapping(
                    type: 'purchase_received_cash',
                    j1Amount: $totalAmount,
                    reference: $po,
                    replacements: ['po_number' => $po->po_number ?? $po->id],
                    customCreditAccountId: $po->payment_account_id
                );
            } else {
                // Unpaid / Partial masuk skema tempo dulu: Persediaan (D) vs Utang Dagang (K)
                JournalEntry::createEntryFromMapping(
                    type: 'purchase_received_credit',
                    j1Amount: $totalAmount,
                    reference: $po,
                    replacements: ['po_number' => $po->po_number ?? $po->id]
                );

                // Jika STATUS PARTIAL: Tambahkan potongan jurnal Clearance tunai untuk DP-nya
                if ($po->payment_status === 'partial') {
                    JournalEntry::createEntryFromMapping(
                        type: 'purchase_payment_clearance',
                        j1Amount: $po->total_payment, // Ambil dari kolom database total_payment
                        reference: $po,
                        replacements: ['po_number' => $po->po_number ?? $po->id],
                        customCreditAccountId: $po->payment_account_id
                    );
                }
            }
        }

        return $po;
    });

    return response()->json($po->load(['supplier', 'items.rawMaterial']), 201);
}

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json(
            $purchaseOrder->load(['supplier', 'items.rawMaterial'])
        );
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'             => 'sometimes|uuid|exists:suppliers,id',
            'order_date'              => 'sometimes|date',
            'notes'                   => 'nullable|string',
            'status'                  => 'required|in:draft,received',
            'items'                   => 'sometimes|array|min:1',
            'items.*.raw_material_id' => 'required_with:items|uuid|exists:raw_materials,id',
            'items.*.qty'             => 'required_with:items|numeric|min:0.0001',
            'items.*.unit_price'      => 'required_with:items|numeric|min:0',
        ]);

        $oldStatus = $purchaseOrder->status;
        $newStatus = $data['status'];

        DB::transaction(function () use ($purchaseOrder, $data, $oldStatus, $newStatus) {
            // 1. JIKA STATUS LAMA RECEIVED: Revert dulu (buang stok lama)
            // Lakukan ini sebelum update data apapun agar stok kembali ke angka awal
            if ($oldStatus === 'received') {
                $this->stockService->reverseReceivePurchaseOrder($purchaseOrder);
            }

            // 2. Update Data PO
            $purchaseOrder->update($data);

            // 3. Update Items
            if (isset($data['items'])) {
                $purchaseOrder->items()->delete();
                foreach ($data['items'] as $item) {
                    $purchaseOrder->items()->create($item);
                }
                $purchaseOrder->recalculateTotal();
            }

            // 4. JIKA STATUS BARU RECEIVED: Tambah stok
            if ($newStatus === 'received') {
                $purchaseOrder->load('items.rawMaterial'); // WAJIB load ulang setelah items diupdate
                $this->stockService->receivePurchaseOrder($purchaseOrder);
            }
        });

        return response()->json($purchaseOrder->fresh(['supplier', 'items.rawMaterial']));
    }

    public function payOrder(Request $request, $id): JsonResponse
    {
        $request->validate([
            'payment_account_id' => 'required|uuid|exists:accounts,id',
            'amount'             => 'required|numeric|min:0',
        ]);

        $po = PurchaseOrder::findOrFail($id);
        
        // Hitung sisa utang maksimal yang bisa dibayar
        $currentDebt = (float)$po->total_amount - (float)$po->total_payment;

        if ((float)$request->amount > $currentDebt) {
            return response()->json([
                'errors' => ['amount' => ['Nominal pembayaran melebihi sisa utang (Sisa: Rp ' . number_format($currentDebt) . ')']]
            ], 422);
        }

        DB::transaction(function () use ($po, $request, $currentDebt) {
            // 1. Update akumulasi pembayaran pada PO
            $newTotalPayment = (float)$po->total_payment + (float)$request->amount;
            
            // 2. Tentukan status pembayaran baru otomatis
            $newPaymentStatus = ($newTotalPayment >= (float)$po->total_amount) ? 'paid' : 'partial';

            $po->update([
                'total_payment'  => $newTotalPayment,
                'payment_status' => $newPaymentStatus,
            ]);

            // 3. CETAK JURNAL: Utang Dagang (D) vs Kas/Bank Pilihan (K)
            JournalEntry::createEntryFromMapping(
                type: 'purchase_payment_clearance',
                j1Amount: (float)$request->amount,
                reference: $po,
                replacements: ['po_number' => $po->po_number ?? $po->id],
                customCreditAccountId: $request->payment_account_id
            );
        });

        return response()->json([
            'success' => true, 
            'message' => 'Pembayaran cicilan utang berhasil dicatat.',
            'data'    => $po->load(['supplier', 'items.rawMaterial'])
        ]);
    }

    /**
     * Tandai PO sebagai received → stok masuk + avg cost update.
     */
    public function receive(PurchaseOrder $purchaseOrder): JsonResponse
    {
        // Pastikan status yang di-abort konsisten
        abort_if($purchaseOrder->status !== 'draft', 422, 'Hanya PO berstatus "draft" yang bisa diterima.');

        $purchaseOrder->load('items.rawMaterial'); // Load sebelum dikirim ke service
        $this->stockService->receivePurchaseOrder($purchaseOrder);

        return response()->json([
            'message' => 'PO berhasil diterima.',
            'data'    => $purchaseOrder->fresh(['items.rawMaterial']),
        ]);
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        abort_if($purchaseOrder->status === 'received', 422, 'PO yang sudah diterima tidak bisa dihapus.');
        $purchaseOrder->delete();

        return response()->json(['message' => 'PO dihapus.']);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        // 1. Validasi input
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'uuid|exists:purchase_orders,id',
        ]);

        // 2. Cek apakah ada PO yang statusnya bukan 'draft'
        $hasInvalidOrders = PurchaseOrder::whereIn('id', $data['ids'])
            ->where('status', '!=', 'draft')
            ->exists();

        if ($hasInvalidOrders) {
            return response()->json([
                'message' => 'Gagal menghapus: Hanya PO berstatus "draft" yang diperbolehkan untuk dihapus. PO yang sudah "received" tidak dapat dihapus.'
            ], 422); // Unprocessable Entity
        }

        // 3. Jika lolos validasi, lakukan penghapusan
        PurchaseOrder::whereIn('id', $data['ids'])->delete();

        return response()->json([
            'message' => 'Data PO terpilih berhasil dihapus.'
        ]);
    }
}