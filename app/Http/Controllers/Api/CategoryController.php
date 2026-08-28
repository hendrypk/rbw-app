<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        // Mengembalikan semua kategori untuk kebutuhan managemen modal
        return response()->json(Category::orderBy('name')->get());
    }
public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name',
            'sort' => 'nullable|integer|min:0'
        ]);

        $category = Category::create([
            'id'         => (string) Str::uuid(),
            'name'       => $validated['name'],
            'is_visible' => true,
            'sort'       => $validated['sort'] ?? 0
        ]);

        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category->id)],
            'is_visible' => 'sometimes|boolean',
            'sort'       => 'sometimes|integer|min:0'
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    /**
     * Endpoint untuk mengatur urutan kategori secara massal (Drag & Drop sorting)
     * Payload contoh: { "items": [{ "id": "uuid-1", "sort": 0 }, { "id": "uuid-2", "sort": 1 }] }
     */
    public function updateCategoriesSort(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'          => 'required|array',
            'items.*.id'     => 'required|string|exists:categories,id',
            'items.*.sort'   => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            Category::where('id', $item['id'])->update(['sort' => $item['sort']]);
        }

        return response()->json(['message' => 'Category order updated successfully.']);
    }

    /**
     * Endpoint untuk mengatur urutan menu di dalam kategori tertentu
     * Payload contoh: { "items": [{ "menu_id": "uuid-menu-1", "sort": 0 }, { "menu_id": "uuid-menu-2", "sort": 1 }] }
     */
    public function updateMenusSort(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'items'          => 'required|array',
            'items.*.menu_id'=> 'required|string|exists:menus,id',
            'items.*.sort'   => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            // Mengupdate tabel pivot category_menu
            $category->menus()->updateExistingPivot($item['menu_id'], ['sort' => $item['sort']]);
        }

        return response()->json(['message' => 'Menu order within category updated successfully.']);
    }

    public function destroy(Category $category)
    {
        try {
            $category->delete();
            return response()->json(['message' => 'Kategori berhasil dihapus.']);
        } catch (\Illuminate\Database\QueryException $e) {
            // Integrity constraint violation (SQLSTATE 23000)
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'Kategori tidak dapat dihapus karena masih digunakan oleh beberapa menu aktif.'
                ], 422);
            }
            return response()->json(['message' => 'Terjadi kesalahan database.'], 500);
        }
    }
}