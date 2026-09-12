<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RawMaterialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $outletId = $request->input('outlet_id');

        $query = RawMaterial::query()
            ->when($outletId && $outletId !== 'all', fn($q) => $q->where('outlet_id', $outletId))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->has('active'), fn($q) => $q->active())
            ->orderBy('name');

        return response()->json($query->paginate($request->per_page ?? 15));
    }
    
    public function options(Request $request): JsonResponse
    {
        $outletId = $request->input('outlet_id');

        $materials = RawMaterial::query()
            ->when($outletId && $outletId !== 'all', fn($q) => $q->where('outlet_id', $outletId))
            ->active()
            ->orderBy('name')
            ->get();

        return response()->json($materials);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'base_unit'         => 'required|string|max:20',
            'purchase_unit'     => 'required|string|max:20',
            'conversion_factor' => 'required|numeric|min:0.0001',
            'min_stock'         => 'nullable|numeric|min:0',
            'is_active'         => 'boolean',
            'outlet_id' => 'nullable|uuid|exists:outlets,id',
        ]);

        $material = RawMaterial::create($data);

        return response()->json($material, 201);
    }

    public function show(RawMaterial $rawMaterial): JsonResponse
    {
        return response()->json(
            $rawMaterial->load(['stockLedger' => fn($q) => $q->latest('created_at')->limit(20)])
        );
    }

    public function update(Request $request, RawMaterial $rawMaterial): JsonResponse
    {
        $data = $request->validate([
            'name'              => 'sometimes|required|string|max:255',
            'base_unit'         => 'sometimes|required|string|max:20',
            'purchase_unit'     => 'sometimes|required|string|max:20',
            'conversion_factor' => 'sometimes|required|numeric|min:0.0001',
            'min_stock'         => 'nullable|numeric|min:0',
            'is_active'         => 'boolean',
            'outlet_id' => 'nullable|uuid|exists:outlets,id',
        ]);

        $rawMaterial->update($data);

        return response()->json($rawMaterial);
    }

    public function destroy(RawMaterial $rawMaterial): JsonResponse
    {
        // Cek apakah bahan baku sudah digunakan di resep menu
        if ($rawMaterial->menuRecipes()->exists()) {
            return response()->json([
                'message' => 'Bahan baku tidak bisa dihapus karena masih digunakan dalam resep menu.'
            ], 422); // Status code 422 Unprocessable Entity
        }

        $rawMaterial->delete();

        return response()->json(['message' => 'Bahan baku dihapus.']);
    }

    public function ledger(Request $request, RawMaterial $rawMaterial): JsonResponse
    {
        $query = $rawMaterial->stockLedger()
            ->with('reference') // <-- SANGAT PENTING: Eager load relasi polymorph
            ->latest('created_at');

        if (!empty($request->start_date) && !empty($request->end_date)) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

        $ledger = $query->paginate(20);

        return response()->json($ledger);
    }
    

    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|uuid|exists:raw_materials,id',
        ]);

        // Cari ID yang sedang digunakan dalam resep
        $usedMaterials = \App\Models\MenuRecipe::whereIn('raw_material_id', $request->ids)
            ->pluck('raw_material_id')
            ->toArray();

        // Hapus hanya ID yang tidak ada di daftar $usedMaterials
        $idsToDelete = array_diff($request->ids, $usedMaterials);

        if (empty($idsToDelete)) {
            return response()->json([
                'message' => 'Tidak ada bahan baku yang bisa dihapus karena semuanya masih digunakan dalam resep.'
            ], 422);
        }

        RawMaterial::whereIn('id', $idsToDelete)->delete();

        $message = 'Berhasil menghapus ' . count($idsToDelete) . ' bahan baku.';
        if (count($usedMaterials) > 0) {
            $message .= ' Beberapa bahan tidak bisa dihapus karena masih digunakan dalam resep.';
        }

        return response()->json(['message' => $message]);
    }

    public function adjustStock(Request $request, RawMaterial $rawMaterial, \App\Services\StockService $stockService): JsonResponse
    {
        $request->validate([
            'adjustment_type' => 'required|in:in,out,actual',
            'qty' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            DB::transaction(function () use ($request, $rawMaterial, $stockService) {
                $inputQty = (float) $request->qty;
                $currentStock = (float) $rawMaterial->stock_qty;
                $type = $request->adjustment_type;
                $baseNotes = $request->notes ? ': ' . $request->notes : '';

                if ($type === 'in') {
                    if ($inputQty <= 0) {
                        throw new \InvalidArgumentException("Jumlah stok masuk harus lebih dari 0.");
                    }

                    $stockService->recordIn(
                        rawMaterial: $rawMaterial,
                        qty: $inputQty,
                        unitCost: (float) $rawMaterial->avg_cost,
                        referenceId: $rawMaterial->id,
                        referenceType: RawMaterial::class,
                        notes: 'Penyesuaian Stok Masuk (IN)' . $baseNotes
                    );
                } 
                elseif ($type === 'out') {
                    if ($inputQty <= 0) {
                        throw new \InvalidArgumentException("Jumlah stok keluar harus lebih dari 0.");
                    }
                    if ($inputQty > $currentStock) {
                        throw new \InvalidArgumentException("Jumlah pengeluaran melebihi stok saat ini (" . $currentStock . ").");
                    }

                    $stockService->recordOut(
                        rawMaterial: $rawMaterial,
                        qty: $inputQty,
                        referenceId: $rawMaterial->id,
                        referenceType: RawMaterial::class,
                        notes: 'Penyesuaian Stok Keluar (OUT)' . $baseNotes
                    );
                } 
                elseif ($type === 'actual') {
                    // Logika Stock Opname / Aktual Stok
                    $diff = $inputQty - $currentStock;

                    if ($diff == 0) {
                        throw new \InvalidArgumentException("Qty aktual sama dengan stok sistem saat ini. Tidak ada penyesuaian yang dilakukan.");
                    }

                    if ($diff > 0) {
                        // Aktual lebih banyak dari sistem -> Catat sebagai Masuk (IN) selisihnya
                        $stockService->recordIn(
                            rawMaterial: $rawMaterial,
                            qty: abs($diff),
                            unitCost: (float) $rawMaterial->avg_cost,
                            referenceId: $rawMaterial->id,
                            referenceType: RawMaterial::class,
                            notes: 'Stock Opname / Aktual Stok (Penambahan Selisih)' . $baseNotes
                        );
                    } else {
                        // Aktual lebih sedikit dari sistem -> Catat sebagai Keluar (OUT) selisihnya
                        $stockService->recordOut(
                            rawMaterial: $rawMaterial,
                            qty: abs($diff),
                            referenceId: $rawMaterial->id,
                            referenceType: RawMaterial::class,
                            notes: 'Stock Opname / Aktual Stok (Pengurangan Selisih)' . $baseNotes
                        );
                    }
                }
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Gagal Penyesuaian Stok: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }

        $rawMaterial->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Penyesuaian persediaan berhasil disimpan.',
            'data' => $rawMaterial
        ]);
    }
}