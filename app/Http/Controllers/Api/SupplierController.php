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
            'name'    => 'required|string|max:255',
            'phone'   => 'nullable|string|max:20',
            'email'   => 'nullable|email',
            'address' => 'nullable|string',
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
        $supplier->delete();

        return response()->json(['message' => 'Supplier dihapus.']);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        // Ubah validasi agar menerima numeric (string angka tetap dianggap valid)
    $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|uuid|exists:suppliers,id', 
        ]);

        // Laravel secara otomatis akan mengubah string "1" menjadi integer 1 saat query
        \App\Models\Supplier::whereIn('id', $request->ids)->delete();

        return response()->json(['message' => 'Supplier terpilih berhasil dihapus.']);
    }
}