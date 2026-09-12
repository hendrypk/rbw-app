<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->has('active'), fn($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return response()->json($suppliers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'phone'     => 'nullable|string|max:20',
            'email'     => 'nullable|email',
            'address'   => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $supplier = Supplier::create($data);

        return response()->json($supplier, 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json($supplier->load('purchaseOrders'));
    }


    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $data = $request->validate([
            'name'      => 'sometimes|required|string|max:255',
            'phone'     => 'nullable|string|max:20',
            'email'     => 'nullable|email',
            'address'   => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $supplier->update($data);

        return response()->json($supplier);
    }

public function destroy(Supplier $supplier): JsonResponse
    {
        if ($supplier->purchaseOrders()->exists()) {
            return response()->json([
                'message' => 'Supplier tidak dapat dihapus karena sudah memiliki riwayat transaksi pembelian.'
            ], 422);
        }

        $supplier->delete();

        return response()->json(['message' => 'Supplier berhasil dihapus.']);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|uuid|exists:suppliers,id', 
        ]);

        // Cek apakah ada supplier dari daftar ID yang memiliki relasi purchase orders
        $suppliersWithPurchases = \App\Models\Supplier::whereIn('id', $request->ids)
            ->has('purchaseOrders')
            ->pluck('name');

        if ($suppliersWithPurchases->isNotEmpty()) {
            return response()->json([
                'message' => 'Beberapa supplier terpilih tidak dapat dihapus karena sudah memiliki riwayat transaksi pembelian: ' . $suppliersWithPurchases->implode(', ')
            ], 422);
        }

        \App\Models\Supplier::whereIn('id', $request->ids)->delete();

        return response()->json(['message' => 'Supplier terpilih berhasil dihapus.']);
    }
}