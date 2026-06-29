<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RawMaterialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $materials = RawMaterial::query()
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->has('active'), fn($q) => $q->active())
            ->orderBy('name');
            // ->paginate($request->per_page ?? 15);

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

    public function ledger(RawMaterial $rawMaterial): JsonResponse
    {
        $ledger = $rawMaterial->stockLedger()
            ->latest('created_at')
            ->paginate(20);

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
}