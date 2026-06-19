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
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

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

        RawMaterial::whereIn('id', $request->ids)->delete();

        return response()->json(['message' => 'Material berhasil dihapus.']);
    }
}