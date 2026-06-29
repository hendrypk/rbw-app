<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
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
            'name' => 'required|string|max:100|unique:categories,name'
        ]);

        $category = Category::create([
            'id' => (string) Str::uuid(),
            'name' => $validated['name'],
            'is_visible' => true
        ]);

        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category->id)],
            'is_visible' => 'sometimes|boolean'
        ]);

        $category->update($validated);

        return response()->json($category);
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