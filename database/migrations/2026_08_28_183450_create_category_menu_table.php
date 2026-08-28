<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Menu;
use App\Models\Category;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('category_menu', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignUuid('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'menu_id']);
        });

            $categoryNames = [
        'nanas',
        'stroberi',
        'bluberi',
        'keju',
        'tiramisu',
        'matcha',
        'vanila',
        'srikaya',
        'cokelat',
        'Choco Crunchy',
        'Keju Crunchy',
        'Susu Crunchy',
        'Tiramisu Crunchy'
    ];

    $categories = [];
    foreach ($categoryNames as $catName) {
        $categories[strtolower($catName)] = Category::firstOrCreate(
            ['name' => $catName],
            ['id' => (string) Str::uuid(), 'is_visible' => true, 'sort' => 0]
        );
    }

    foreach (Menu::all() as $menu) {
        $matchedCategoryIds = [];
        $menuNameLower = strtolower($menu->name);

        foreach ($categories as $catNameLower => $category) {
            if (str_contains($menuNameLower, $catNameLower)) {
                $matchedCategoryIds[$category->id] = ['sort' => 0];
            }
        }

        if (!empty($matchedCategoryIds)) {
            $menu->categories()->syncWithoutDetaching($matchedCategoryIds);
        }
    }
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_menu');
    }
};
