<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Outlet::query();

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('address', 'like', "%{$search}%");
                });
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $outlets = $query->orderBy('name', 'asc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar outlet berhasil dimuat',
                'data'    => $outlets
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat daftar outlet: ' . $e->getMessage(),
                'data'    => []
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'code'      => 'required|string|max:50|unique:outlets,code',
            'address'   => 'nullable|string',
            'phone'     => 'nullable|string|max:25',
            'is_active' => 'boolean',
        ]);

        try {
            $outlet = Outlet::create([
                'name'      => $validated['name'],
                'code'      => $validated['code'],
                'address'   => $validated['address'] ?? null,
                'phone'     => $validated['phone'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Outlet berhasil ditambahkan',
                'data'    => $outlet
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan outlet: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $outlet = Outlet::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Detail outlet ditemukan',
                'data'    => $outlet
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Outlet tidak ditemukan'
            ], 404);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $outlet = Outlet::findOrFail($id);

            $validated = $request->validate([
                'name'      => 'sometimes|required|string|max:255',
                'code'      => 'sometimes|required|string|max:50|unique:outlets,code,' . $id,
                'address'   => 'nullable|string',
                'phone'     => 'nullable|string|max:25',
                'is_active' => 'boolean',
            ]);

            $outlet->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Outlet berhasil diperbarui',
                'data'    => $outlet
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui outlet: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $outlet = Outlet::findOrFail($id);
            $outlet->delete();

            return response()->json([
                'success' => true,
                'message' => 'Outlet berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus outlet: ' . $e->getMessage()
            ], 500);
        }
    }
}