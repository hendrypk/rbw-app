<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuPrice;
use App\Models\OverheadCost;
use App\Services\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MenuController extends Controller
{
    public function __construct(private MenuService $menuService) {}

    public function index(Request $request)
    {
        $outletId = $request->input('outlet_id', 'all');

        $menus = $this->menuService->getAllMenus($outletId);

        return response()->json($menus);
    }
    
    public function store(Request $request): JsonResponse
    {
        $outletId = $request->input('outlet_id');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_ids' => 'required|array|min:1', 
            'category_ids.*' => [
                'string',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($outletId) {
                    if ($outletId) {
                        $category = \App\Models\Category::find($value);
                        if ($category && $category->outlet_id && $category->outlet_id !== $outletId) {
                            $fail('Kategori yang dipilih tidak sesuai dengan outlet aktif.');
                        }
                    }
                }
            ],
            'description' => 'nullable|string',
            'recipes' => 'required|array|min:1',
            'overhead_cost' => 'sometimes|required|numeric|min:0',
            'recipes.*.raw_material_id' => [
                'required',
                'uuid',
                'exists:raw_materials,id',
                function ($attribute, $value, $fail) use ($outletId) {
                    if ($outletId) {
                        $material = \App\Models\RawMaterial::find($value);
                        if ($material && $material->outlet_id && $material->outlet_id !== $outletId) {
                            $fail('Bahan baku yang dipilih tidak sesuai dengan outlet aktif.');
                        }
                    }
                }
            ],
            'recipes.*.qty_usage' => 'required|numeric|min:0.0001',
            'prices' => 'required|array|min:1',
            'prices.*.channel' => 'required|in:offline,shopeefood,grabfood,gofood',
            'prices.*.selling_price' => 'required|numeric|min:0',
            'prices.*.margin_percent' => 'nullable|numeric', 
            'outlet_id' => 'nullable|uuid|exists:outlets,id',
        ]);

        $result = DB::transaction(function () use ($data, $outletId) {
            $menu = Menu::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'overhead_cost' => $data['overhead_cost'] ?? 0,
                'outlet_id' => $outletId ?? null, 
            ]);

            $categorySyncData = [];
            foreach ($data['category_ids'] as $index => $categoryId) {
                $categorySyncData[$categoryId] = ['sort' => $index]; 
            }
            $menu->categories()->sync($categorySyncData);

            return $this->menuService->saveRecipesAndPrices($menu, $data['recipes'], $data['prices']);
        });

        return response()->json($result, 201);
    }

    public function update(Request $request, Menu $menu): JsonResponse
    {
        $outletId = $request->input('outlet_id') ?? $menu->outlet_id;

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'category_ids' => 'sometimes|array|min:1',
            'category_ids.*' => [
                'string',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($outletId) {
                    if ($outletId) {
                        $category = \App\Models\Category::find($value);
                        if ($category && $category->outlet_id && $category->outlet_id !== $outletId) {
                            $fail('Kategori yang dipilih tidak sesuai dengan outlet aktif.');
                        }
                    }
                }
            ],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'recipes' => 'sometimes|array|min:1',
            'recipes.*.raw_material_id' => [
                'required_with:recipes',
                'uuid',
                'exists:raw_materials,id',
                function ($attribute, $value, $fail) use ($outletId) {
                    if ($outletId) {
                        $material = \App\Models\RawMaterial::find($value);
                        if ($material && $material->outlet_id && $material->outlet_id !== $outletId) {
                            $fail('Bahan baku yang dipilih tidak sesuai dengan outlet aktif.');
                        }
                    }
                }
            ],
            'recipes.*.qty_usage' => 'required_with:recipes|numeric|min:0.0001',
            'prices' => 'sometimes|array|min:1',
            'prices.*.channel' => 'required_with:prices|in:offline,shopeefood,grabfood,gofood',
            'prices.*.selling_price' => 'sometimes|required_with:prices|numeric|min:0',
            'prices.*.margin_percent' => 'nullable|numeric', 
            'outlet_id' => 'nullable|uuid|exists:outlets,id',
        ]);

        $result = DB::transaction(function () use ($request, $data, $menu, $outletId) {
            $menu->update(array_merge($data, [
                'outlet_id' => $outletId ?? $menu->outlet_id
            ]));

            if (isset($data['category_ids'])) {
                $categorySyncData = [];
                foreach ($data['category_ids'] as $index => $categoryId) {
                    $categorySyncData[$categoryId] = ['sort' => $index];
                }
                $menu->categories()->sync($categorySyncData);
            }

            if (isset($data['recipes']) || isset($data['prices'])) {
                return $this->menuService->saveRecipesAndPrices(
                    $menu,
                    $data['recipes'] ?? $menu->recipes->map(fn($r) => [
                        'raw_material_id' => $r->raw_material_id,
                        'qty_usage' => $r->qty_usage
                    ])->toArray(),
                    $data['prices'] ?? $menu->prices->map(fn($p) => [
                        'channel' => $p->channel,
                        'selling_price' => $p->selling_price,
                        'margin_percent'=> $p->margin_percent,
                    ])->toArray()
                );
            }

            return $menu->load(['recipes.rawMaterial', 'prices', 'categories']);
        });

        return response()->json($result);
    }

    public function show(Menu $menu): JsonResponse
    {
        return response()->json(
            $menu->load(['recipes.rawMaterial', 'prices'])
        );
    }
    
    public function destroy(Menu $menu): JsonResponse
    {
        // Cek apakah menu sudah pernah digunakan dalam transaksi (order items)
        if ($menu->orderItems()->exists() || method_exists($menu, 'orderItems') && $menu->orderItems()->count() > 0) {
            return response()->json([
                'message' => 'Menu tidak dapat dihapus karena sudah memiliki riwayat transaksi/penjualan. Anda dapat menonaktifkannya.'
            ], 422);
        }

        DB::transaction(function () use ($menu) {
            $menu->recipes()->delete();
            $menu->prices()->delete();
            $menu->delete();
        });

        return response()->json(['message' => 'Menu berhasil dihapus.']);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'string|exists:menus,id',
        ]);

        // Cek apakah ada menu dari daftar yang dipilih sudah memiliki transaksi
        $menusWithOrders = Menu::whereIn('id', $data['ids'])
            ->has('orderItems')
            ->exists();

        if ($menusWithOrders) {
            return response()->json([
                'message' => 'Beberapa menu yang dipilih tidak dapat dihapus karena sudah memiliki riwayat transaksi.'
            ], 422);
        }

        DB::transaction(function () use ($data) {
            $menus = Menu::whereIn('id', $data['ids'])->get();
            foreach ($menus as $menu) {
                $menu->recipes()->delete();
                $menu->prices()->delete();
                $menu->delete();
            }
        });

        return response()->json([
            'message' => 'Menu terpilih berhasil dihapus.'
        ]);
    }

    public function channels(): JsonResponse
    {
        return response()->json([
            'channels' => MenuPrice::CHANNELS,
            'platform_fees' => MenuPrice::PLATFORM_FEES,
        ]);
    }

    // 1. Method untuk cek apakah nominal overhead di menu sama dengan master yang aktif
    public function checkOverheadSync(): JsonResponse
    {
        // Hitung total nominal semua master overhead yang sedang aktif saat ini
        $currentMasterTotal = (float) OverheadCost::where('is_active', true)->sum('amount');

        // Cek apakah ada menu aktif yang nilai overhead_cost-nya tidak sama dengan total master aktif
        $isOutofSync = Menu::where('is_active', true)
            ->where('overhead_cost', '!=', $currentMasterTotal)
            ->exists();

        return response()->json([
            'is_out_of_sync' => $isOutofSync,
            'master_total' => $currentMasterTotal
        ]);
    }

    // 2. Method untuk eksekusi sync massal dan kalkulasi ulang HPP & Harga Jual tiap menu
    public function syncOverhead(): JsonResponse
    {
        $currentMasterTotal = (float) OverheadCost::where('is_active', true)->sum('amount');
        
        // Ambil semua menu yang perlu diupdate
        $menusToUpdate = Menu::where('is_active', true)->get();

        foreach ($menusToUpdate as $menu) {
            // Update nominal overhead di menu
            $menu->update(['overhead_cost' => $currentMasterTotal]);

            // Panggil kembali MenuService Anda untuk kalkulasi ulang resep + harga jual per channel
            // Asumsi struktur service Anda: $this->menuService->recalculateMenuPrices($menu);
            $this->menuService->saveRecipesAndPrices(
                $menu, 
                $menu->recipes->toArray(), 
                $menu->prices->map(fn($p) => [
                    'channel' => $p->channel,
                    'margin_percent' => $p->margin_percent
                ])->toArray()
            );
        }

        return response()->json([
            'message' => 'Berhasil menyinkronkan overhead cost ke seluruh menu produksi.'
        ]);
    }

    public function checkRecipeSync(): JsonResponse
    {
        $isOutofSync = Menu::where('menus.is_active', true)
            ->join('menu_recipes', 'menus.id', '=', 'menu_recipes.menu_id')
            ->join('raw_materials', 'menu_recipes.raw_material_id', '=', 'raw_materials.id')
            ->whereColumn('menu_recipes.unit_cost_snapshot', '!=', 'raw_materials.avg_cost')
            ->exists();

        return response()->json([
            'is_out_of_sync' => $isOutofSync // Pastikan key-nya konsisten snake_case
        ]);
    }

    public function syncRecipes(): JsonResponse
    {
        $menusToUpdate = Menu::where('is_active', true)->with(['recipes.rawMaterial', 'prices'])->get();

        foreach ($menusToUpdate as $menu) {
            $recipesData = $menu->recipes->map(function ($recipe) {
                return [
                    'raw_material_id' => $recipe->raw_material_id,
                    'qty_usage' => $recipe->qty_usage, 
                ];
            })->toArray();

            $pricesData = $menu->prices->map(function ($price) {
                return [
                    'channel' => $price->channel,
                    'margin_percent' => $price->margin_percent,
                ];
            })->toArray();

            // Gunakan service yang sudah ada untuk kalkulasi ulang total HPP & harga jual
            $this->menuService->saveRecipesAndPrices($menu, $recipesData, $pricesData);
        }

        return response()->json([
            'message' => 'Berhasil menyinkronkan HPP bahan baku ke seluruh menu produksi.'
        ]);
    }

public function userIndex(Request $request)
{
    // Ambil kategori yang visible, memiliki menu aktif, dan urutkan berdasarkan 'sort'
    $categories = Category::where('is_visible', true)
        ->whereHas('menus', function ($query) {
            $query->where('is_active', true);
        })
        ->orderBy('sort', 'asc')
        ->get();

    $menus = Menu::active()
        ->with([
            'categories' => function ($query) {
                $query->orderBy('category_menu.sort', 'asc');
            }, 
            'prices' => function ($query) {
                $query->where('channel', 'offline')->where('is_active', true);
            }
        ])
        ->when($request->filled('category_id'), function ($query) use ($request) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->category_id);
            });
        })
        ->get()
        ->map(function ($menu) {
            $priceOffline = $menu->prices->first();
            
            $mappedCategories = $menu->categories->map(function ($cat) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'sort' => $cat->pivot?->sort ?? 0,
                ];
            });

            $primaryCategory = $menu->categories->first();
            $pivotSort = $primaryCategory?->pivot?->sort ?? 0;

            return [
                'id' => $menu->id,
                'name' => $menu->name,
                'description' => $menu->description,
                'image' => $menu->image_path,
                'categories'  => $mappedCategories,
                'category_id' => $primaryCategory?->id,
                'category' => $primaryCategory ? [
                    'id' => $primaryCategory->id,
                    'name' => $primaryCategory->name,
                ] : null,
                'sort' => $pivotSort,
                'price' => $priceOffline ? (float) $priceOffline->selling_price : 0,
            ];
        });

    return response()->json([
        'status' => 'success',
        'data' => [
            'categories' => $categories,
            'menus' => $menus,
        ]
    ]);
}
}