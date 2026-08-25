<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\MenuPrice;
use App\Models\MenuRecipe;
use App\Models\RawMaterial;
use Illuminate\Support\Facades\DB;

class MenuService
{
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
            $baseCost = (float) $menu->hpp + (float) $menu->overhead_cost;

            // 3. Upsert harga jual per channel berbasis Harga Jual Manual Bulat
            foreach ($prices as $p) {
                $channel = $p['channel'];
                
                // Pastikan selling_price diambil secara bulat
                if (isset($p['selling_price'])) {
                    $sellingPrice = round((float) $p['selling_price']);
                    
                    // Panggil fungsi pembantu di model MenuPrice untuk hitung margin & nett price
                    $calc = MenuPrice::calculateFromSellingPrice($baseCost, $sellingPrice, $channel);
                } else {
                    $margin = (float) ($p['margin_percent'] ?? 30);
                    $calc   = MenuPrice::calculate($baseCost, $margin, $channel);
                }

                MenuPrice::updateOrCreate(
                    ['menu_id' => $menu->id, 'channel' => $channel],
                    array_merge($calc, ['is_active' => true])
                );
            }
        });

        return $menu->fresh(['recipes.rawMaterial', 'prices']);
    }

    public function recalculatePrices(Menu $menu): void
    {
        $hpp = (float) $menu->hpp;
        $baseCost = $hpp + (float) $menu->overhead_cost;

        foreach ($menu->prices as $price) {
            // Jika ingin mempertahankan harga jual manual saat recalculate HPP massal:
            if ($price->selling_price > 0) {
                $calc = MenuPrice::calculateFromSellingPrice($baseCost, (float) $price->selling_price, $price->channel);
                $price->update($calc);
            } else {
                $calc = MenuPrice::calculate($baseCost, (float) $price->margin_percent, $price->channel);
                $price->update($calc);
            }
        }
    }
    
    public function getAllMenus()
    {
        return Menu::with(['recipes.rawMaterial', 'prices', 'category'])->get();
    }
}