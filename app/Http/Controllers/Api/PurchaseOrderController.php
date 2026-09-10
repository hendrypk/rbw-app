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
        $orders = PurchaseOrder::with(['supplier', 'items.rawMaterial', 'journalEntries.items.account'])
            // ->when($request->status, fn($q) => $q->byStatus($request->status))
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->start_date && $request->end_date, fn($q) => 
                $q->whereBetween('order_date', [$request->start_date, $request->end_date])
            )
            ->when($request->search, fn($q, $search) => 
                $q->whereHas('supplier', fn($sq) => $sq->where('name', 'like', "%{$search}%"))
                ->orWhere('po_number', 'like', "%{$search}%")
            )
            ->orderByDesc('order_date')
            ->paginate($request->per_page ?? 15);

        return response()->json($orders);
    }

    public function store(Request $request, StockService $stockService): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'             => 'required|uuid|exists:suppliers,id',
            'outlet_id'               => 'required|uuid|exists:outlets,id',
            'order_date'              => 'required|date',
            'notes'                   => 'nullable|string',
            'items'                   => 'required|array|min:1',
            'items.*.raw_material_id' => 'required|uuid|exists:raw_materials,id',
            'items.*.qty'             => 'required|numeric|min:0.0001',
            'items.*.unit_price'      => 'required|numeric|min:0',
            'status'                  => 'required|in:draft,received',
            'payment_account_id'      => 'nullable|uuid|exists:accounts,id',
            'amount_paid'             => 'nullable|numeric|min:0', // Hapus required_with agar lebih fleksibel
        ]);

        $po = DB::transaction(function () use ($data, $stockService) {
            $paidAmount = !empty($data['payment_account_id']) ? (float) ($data['amount_paid'] ?? 0) : 0;

            $po = PurchaseOrder::create([
                'supplier_id'        => $data['supplier_id'],
                'outlet_id'          => $data['outlet_id'],
                'order_date'         => $data['order_date'],
                'notes'              => $data['notes'] ?? null,
                'status'             => $data['status'],
                'payment_account_id' => $paidAmount > 0 ? $data['payment_account_id'] : null,
                'total_payment'      => $paidAmount,
            ]);

            foreach ($data['items'] as $item) {
                $subtotal = (float) $item['qty'] * (float) $item['unit_price'];
                $po->items()->create(array_merge($item, ['subtotal' => $subtotal]));
            }

            $totalAmount = (float) $po->items()->sum('subtotal');
            $po->total_amount = $totalAmount;

            // Logika Status Pembayaran
            if ($paidAmount <= 0) {
                $po->payment_status = 'unpaid';
                $po->total_payment = 0;
                $po->payment_account_id = null; 
            } elseif ($paidAmount >= $totalAmount) {
                $po->payment_status = 'paid';
                $po->total_payment = $totalAmount;
            } else {
                $po->payment_status = 'partial';
            }

            $po->save();
            
            if ($data['status'] === 'received') {
                $po->load('items.rawMaterial');
                $stockService->receivePurchaseOrder($po);

                if ($po->payment_status === 'paid') {
                    JournalEntry::createEntryFromMapping(
                        type: 'purchase_received_cash',
                        j1Amount: $totalAmount,
                        reference: $po,
                        replacements: ['po_number' => $po->po_number ?? $po->id],
                        customCreditAccountId: $po->payment_account_id
                    );
                } else {
                    JournalEntry::createEntryFromMapping(
                        type: 'purchase_received_credit',
                        j1Amount: $totalAmount,
                        reference: $po,
                        replacements: ['po_number' => $po->po_number ?? $po->id]
                    );

                    if ($po->payment_status === 'partial') {
                        JournalEntry::createEntryFromMapping(
                            type: 'purchase_payment_clearance',
                            j1Amount: $po->total_payment,
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
            $purchaseOrder->load(['supplier', 'items.rawMaterial', 'journalEntries.items.account'])
        );
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'             => 'sometimes|uuid|exists:suppliers,id',
            'outlet_id'               => 'sometimes|uuid|exists:outlets,id',
            'order_date'              => 'sometimes|date',
            'notes'                   => 'nullable|string',
            'status'                  => 'required|in:draft,received',
            'items'                   => 'sometimes|array|min:1',
            'items.*.raw_material_id' => 'required_with:items|uuid|exists:raw_materials,id',
            'items.*.qty'             => 'required_with:items|numeric|min:0.0001',
            'items.*.unit_price'      => 'required_with:items|numeric|min:0',
            
            // PERBAIKAN: Validasi ini wajib ada agar data dari frontend tidak dibuang
            'payment_account_id'      => 'nullable|uuid|exists:accounts,id',
            'amount_paid'             => 'nullable|numeric|min:0', 
        ]);

        $oldStatus = $purchaseOrder->status;
        $newStatus = $data['status'];

        DB::transaction(function () use ($purchaseOrder, $data, $oldStatus, $newStatus) {
            // 1. Revert stok lama jika status sebelumnya received
            if ($oldStatus === 'received') {
                $this->stockService->reverseReceivePurchaseOrder($purchaseOrder);
            }

            // 2. Ambil nominal pembayaran baru (jika tidak ada di request, gunakan yang lama)
            $paidAmount = array_key_exists('amount_paid', $data) 
                ? (float) $data['amount_paid'] 
                : (float) $purchaseOrder->total_payment;
            
            $accountId = array_key_exists('payment_account_id', $data) 
                ? $data['payment_account_id'] 
                : $purchaseOrder->payment_account_id;

            // Jika account diubah jadi kosong (Beli Tempo), pastikan paid = 0
            if (empty($accountId)) {
                $paidAmount = 0;
            }

            // 3. Update data dasar PO
            $purchaseOrder->update([
                'supplier_id'        => $data['supplier_id'] ?? $purchaseOrder->supplier_id,
                'outlet_id'          => $data['outlet_id'] ?? $purchaseOrder->outlet_id,
                'order_date'         => $data['order_date'] ?? $purchaseOrder->order_date,
                'notes'              => $data['notes'] ?? $purchaseOrder->notes,
                'status'             => $newStatus,
            ]);

            // 4. Update detail Items
            if (isset($data['items'])) {
                $purchaseOrder->items()->delete();
                foreach ($data['items'] as $item) {
                    $subtotal = (float) $item['qty'] * (float) $item['unit_price'];
                    $purchaseOrder->items()->create(array_merge($item, ['subtotal' => $subtotal]));
                }
            }

            // 5. PERBAIKAN: Hitung ulang total PO dan logika status pembayarannya (seperti di Create)
            $totalAmount = (float) $purchaseOrder->items()->sum('subtotal');
            $purchaseOrder->total_amount = $totalAmount;

            if ($paidAmount <= 0) {
                $purchaseOrder->payment_status = 'unpaid';
                $purchaseOrder->total_payment = 0;
                $purchaseOrder->payment_account_id = null;
            } elseif ($paidAmount >= $totalAmount) {
                $purchaseOrder->payment_status = 'paid';
                $purchaseOrder->total_payment = $totalAmount;
                $purchaseOrder->payment_account_id = $accountId;
            } else {
                $purchaseOrder->payment_status = 'partial';
                $purchaseOrder->total_payment = $paidAmount;
                $purchaseOrder->payment_account_id = $accountId;
            }

            $purchaseOrder->save();

            // 6. Masukkan stok baru jika statusnya received
            if ($newStatus === 'received') {
                $purchaseOrder->load('items.rawMaterial');
                $this->stockService->receivePurchaseOrder($purchaseOrder);
                // Note: Jika butuh update jurnal saat edit, logika jurnal disisipkan di sini.
            }
        });

        return response()->json($purchaseOrder->fresh(['supplier', 'items.rawMaterial']));
    }

    public function payOrder(Request $request, $id): JsonResponse
    {
        $request->validate([
            'payment_account_id' => 'required|uuid|exists:accounts,id',
            'amount'             => 'required|numeric|min:0',
            'payment_date'       => 'nullable|date', // Tambahkan validasi ini
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