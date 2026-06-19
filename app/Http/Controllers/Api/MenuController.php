<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuPrice;
use App\Services\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MenuController extends Controller
{
    public function __construct(private MenuService $menuService) {}

    public function index()
    {
        return response()->json($this->menuService->getAllMenus());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'category'    => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'recipes'     => 'required|array|min:1',
            'recipes.*.raw_material_id' => 'required|uuid|exists:raw_materials,id',
            'recipes.*.qty_usage'       => 'required|numeric|min:0.0001',
            'prices'      => 'required|array|min:1',
            'prices.*.channel'         => 'required|in:offline,shopeefood,grabfood,gofood',
            'prices.*.margin_percent'  => 'required|numeric|min:0',
        ]);

        $menu = Menu::create([
            'name'        => $data['name'],
            'category'    => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        $result = $this->menuService->saveRecipesAndPrices($menu, $data['recipes'], $data['prices']);

        return response()->json($result, 201);
    }

    public function show(Menu $menu): JsonResponse
    {
        return response()->json(
            $menu->load(['recipes.rawMaterial', 'prices'])
        );
    }

    public function update(Request $request, Menu $menu): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'category'    => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
            'recipes'     => 'sometimes|array|min:1',
            'recipes.*.raw_material_id' => 'required_with:recipes|uuid|exists:raw_materials,id',
            'recipes.*.qty_usage'       => 'required_with:recipes|numeric|min:0.0001',
            'prices'      => 'sometimes|array|min:1',
            'prices.*.channel'         => 'required_with:prices|in:offline,shopeefood,grabfood,gofood',
            'prices.*.margin_percent'  => 'required_with:prices|numeric|min:0',
        ]);

        $menu->update($data);

        if (isset($data['recipes'])) {
            $result = $this->menuService->saveRecipesAndPrices(
                $menu,
                $data['recipes'],
                $data['prices'] ?? $menu->prices->map(fn($p) => [
                    'channel'        => $p->channel,
                    'margin_percent' => $p->margin_percent,
                ])->toArray()
            );
            return response()->json($result);
        }

        return response()->json($menu->fresh(['recipes.rawMaterial', 'prices']));
    }

    public function destroy(Menu $menu): JsonResponse
    {
        $menu->delete();

        return response()->json(['message' => 'Menu dihapus.']);
    }

    public function channels(): JsonResponse
    {
        return response()->json([
            'channels' => MenuPrice::CHANNELS,
            'platform_fees' => MenuPrice::PLATFORM_FEES,
        ]);
    }
}