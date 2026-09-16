<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OverheadCostRequest; // Import Request yang baru dibuat
use App\Models\OverheadCost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverheadCostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $overheads = OverheadCost::query()
            ->when($request->filled('outlet_id'), fn($q) => $q->where('outlet_id', $request->outlet_id))
            ->when($request->search, fn($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json($overheads);
    }

    public function store(OverheadCostRequest $request): JsonResponse
    {
        $overhead = OverheadCost::create($request->validated());

        return response()->json($overhead, 201);
    }

    public function update(OverheadCostRequest $request, OverheadCost $overheadCost): JsonResponse
    {
        $overheadCost->update($request->validated());

        return response()->json($overheadCost);
    }

    public function destroy(OverheadCost $overheadCost): JsonResponse
    {
        $overheadCost->delete();
        return response()->json(['message' => 'Overhead cost berhasil dihapus.']);
    }

    public function getActiveOverheads(Request $request): JsonResponse
    {
        $overheads = OverheadCost::where('is_active', true)
            ->when($request->filled('outlet_id'), fn($q) => $q->where('outlet_id', $request->outlet_id))
            ->get();

        return response()->json($overheads);
    }

    public function updateStatus(Request $request, OverheadCost $overheadCost)
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $overheadCost->update([
            'is_active' => $validated['is_active']
        ]);

        return response()->json([
            'message' => 'Status biaya overhead berhasil diubah.',
            'data'    => $overheadCost
        ]);
    }

    public function syncMenus(Request $request, OverheadCost $overheadCost)
{
    $request->validate([
        'menu_ids' => 'array',
        'menu_ids.*' => 'exists:menus,id'
    ]);

    $overheadCost->menus()->sync($request->menu_ids);

    return response()->json(['message' => 'Menu berhasil dihubungkan.']);
}
}
