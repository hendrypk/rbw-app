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

public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'code'      => 'required|string|max:50|unique:outlets,code',
            'address'   => 'nullable|string',
            'phone'     => 'nullable|string|max:30',
            'is_active' => 'boolean',
        ]);

        Outlet::create($validated);

        return redirect()->back()->with('success', 'Outlet berhasil ditambahkan.');
    }

    public function update(Request $request, Outlet $outlet)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'code'      => 'required|string|max:50|unique:outlets,code,' . $outlet->id,
            'address'   => 'nullable|string',
            'phone'     => 'nullable|string|max:30',
            'is_active' => 'boolean',
        ]);

        $outlet->update($validated);

        return redirect()->back()->with('success', 'Outlet berhasil diperbarui.');
    }

    public function destroy(Outlet $outlet)
    {
        $outlet->delete();

        return redirect()->back()->with('success', 'Outlet berhasil dihapus.');
    }
}
