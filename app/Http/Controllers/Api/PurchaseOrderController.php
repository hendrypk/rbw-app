<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        ]);

        $po = DB::transaction(function () use ($data, $stockService) {
            $po = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'order_date'  => $data['order_date'],
                'notes'       => $data['notes'] ?? null,
                'status'      => $data['status'],
            ]);

            foreach ($data['items'] as $item) {
                $po->items()->create($item);
            }

            $po->recalculateTotal();
            
            // Jika status awal langsung received
            if ($data['status'] === 'received') {
                $po->load('items.rawMaterial'); // WAJIB: memuat relasi agar conversion_factor terbaca
                $stockService->receivePurchaseOrder($po);
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