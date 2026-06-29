<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OverheadCost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverheadCostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $overheads = OverheadCost::query()
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json($overheads);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'amount'    => 'required|numeric|min:0',
            'type'      => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $overhead = OverheadCost::create($data);

        return response()->json($overhead, 201);
    }

    public function update(Request $request, OverheadCost $overheadCost): JsonResponse
    {
        $data = $request->validate([
            'name'      => 'sometimes|required|string|max:255',
            'amount'    => 'sometimes|required|numeric|min:0',
            'type'      => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $overheadCost->update($data);

        return response()->json($overheadCost);
    }

    public function destroy(OverheadCost $overheadCost): JsonResponse
    {
        $overheadCost->delete();
        return response()->json(['message' => 'Overhead cost berhasil dihapus.']);
    }
    
    // Method tambahan untuk dipanggil di form Pembuatan Menu (Hanya yang Aktif)
    public function getActiveOverheads(): JsonResponse
    {
        return response()->json(OverheadCost::where('is_active', true)->get());
    }
}