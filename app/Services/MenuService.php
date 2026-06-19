<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\MenuPrice;
use App\Models\MenuRecipe;
use App\Models\RawMaterial;
use Illuminate\Support\Facades\DB;

class MenuService
{
    /**
     * Simpan/update resep menu dan recalculate HPP + semua harga jual.
     *
     * $recipes = [
     *   ['raw_material_id' => uuid, 'qty_usage' => 50],
     *   ...
     * ]
     *
     * $prices = [
     *   ['channel' => 'offline',    'margin_percent' => 40],
     *   ['channel' => 'shopeefood', 'margin_percent' => 55],
     *   ...
     * ]
     */
    public function saveRecipesAndPrices(Menu $menu, array $recipes, array $prices): Menu
    {
        DB::transaction(function () use ($menu, $recipes, $prices) {
            // 1. Hapus resep lama lalu insert baru
            $menu->recipes()->delete();

            foreach ($recipes as $r) {
                $material = RawMaterial::findOrFail($r['raw_material_id']);
                $snapshot = (float) $material->avg_cost;
                $qty      = (float) $r['qty_usage'];

                MenuRecipe::create([
                    'menu_id'             => $menu->id,
                    'raw_material_id'     => $r['raw_material_id'],
                    'qty_usage'           => $qty,
                    'unit_cost_snapshot'  => $snapshot,
                    'subtotal_cost'       => round($qty * $snapshot, 4),
                ]);
            }

            // 2. Recalculate HPP
            $menu->recalculateHpp();
            $hpp = (float) $menu->hpp;

            // 3. Upsert harga jual per channel
            foreach ($prices as $p) {
                $channel = $p['channel'];
                $margin  = (float) $p['margin_percent'];
                $calc    = MenuPrice::calculate($hpp, $margin, $channel);

                MenuPrice::updateOrCreate(
                    ['menu_id' => $menu->id, 'channel' => $channel],
                    array_merge(['margin_percent' => $margin], $calc, ['is_active' => true])
                );
            }
        });

        return $menu->fresh(['recipes.rawMaterial', 'prices']);
    }

    /**
     * Recalculate semua harga jual ketika HPP berubah (misal avg_cost bahan naik).
     */
    public function recalculatePrices(Menu $menu): void
    {
        $hpp = (float) $menu->hpp;

        foreach ($menu->prices as $price) {
            $calc = MenuPrice::calculate($hpp, (float) $price->margin_percent, $price->channel);
            $price->update($calc);
        }
    }

    
    public function getAllMenus()
    {
        // Semua logika query di sini
        return Menu::with(['recipes.rawMaterial', 'prices'])->get();
    }
}