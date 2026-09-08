<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Outlet;
use App\Models\User;

class MigrateToMultiOutletSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Create Outlet 1 and Outlet 2
            $outlet1Id = (string) Str::uuid();
            $outlet2Id = (string) Str::uuid();

            DB::table('outlets')->insert([
                [
                    'id' => $outlet1Id,
                    'name' => 'Roti Bakar Wisuda Jl Kaliurang km 12.5',
                    'code' => 'RBW-UII',
                    'phone' => '085814973157',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => $outlet2Id,
                    'name' => 'Roti Bakar Wisuda Borobudur',
                    'code' => 'RBW-BDR',
                    'phone' => '085814973157',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            // 2. Link ALL existing users to BOTH Outlets via the outlet_user pivot table
            $users = DB::table('users')->get();
            $pivotData = [];
            foreach ($users as $user) {
                $pivotData[] = [
                    'id' => (string) Str::uuid(),
                    'outlet_id' => $outlet1Id, 
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $pivotData[] = [
                    'id' => (string) Str::uuid(),
                    'outlet_id' => $outlet2Id, 
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (!empty($pivotData)) {
                DB::table('outlet_users')->insert($pivotData);
            }

            // 3. Set existing data for the following tables to OUTLET 1
            $tablesToOutlet1 = [
                'raw_materials',
                'categories',
                'overhead_costs',
                'vouchers',
                'purchase_orders',
                'purchase_order_items',
            ];

            foreach ($tablesToOutlet1 as $table) {
                DB::table($table)->update(['outlet_id' => $outlet1Id]);
            }

            // For the menus table, set it to outlet 1 as well
            DB::table('menus')->update(['outlet_id' => $outlet1Id]);


            // 4. DUPLICATE ALL DATA TO OUTLET 2
            
            // A. Raw Materials for Outlet 2 (Keep tracking old ID mapping to new ID for recipes)
            $rawMaterials = DB::table('raw_materials')->where('outlet_id', $outlet1Id)->get();
            $rawMaterialMap = [];
            foreach ($rawMaterials as $rm) {
                $newId = (string) Str::uuid();
                $rawMaterialMap[$rm->id] = $newId;
                
                $arr = (array) $rm;
                $arr['id'] = $newId;
                $arr['outlet_id'] = $outlet2Id;
                unset($arr['created_at'], $arr['updated_at']);
                DB::table('raw_materials')->insert(array_merge($arr, ['created_at' => now(), 'updated_at' => now()]));
            }

            // B. Categories for Outlet 2
            $categories = DB::table('categories')->where('outlet_id', $outlet1Id)->get();
            $categoryMap = [];
            foreach ($categories as $cat) {
                $newId = (string) Str::uuid();
                $categoryMap[$cat->id] = $newId;

                $arr = (array) $cat;
                $arr['id'] = $newId;
                $arr['outlet_id'] = $outlet2Id;
                unset($arr['created_at'], $arr['updated_at']);
                DB::table('categories')->insert(array_merge($arr, ['created_at' => now(), 'updated_at' => now()]));
            }

            // C. Overhead Costs for Outlet 2
            $overheads = DB::table('overhead_costs')->where('outlet_id', $outlet1Id)->get();
            foreach ($overheads as $oh) {
                $arr = (array) $oh;
                $arr['id'] = (string) Str::uuid();
                $arr['outlet_id'] = $outlet2Id;
                unset($arr['created_at'], $arr['updated_at']);
                DB::table('overhead_costs')->insert(array_merge($arr, ['created_at' => now(), 'updated_at' => now()]));
            }

            // D. Vouchers for Outlet 2
            $vouchers = DB::table('vouchers')->where('outlet_id', $outlet1Id)->get();
            $voucherMap = [];
            foreach ($vouchers as $v) {
                $newId = (string) Str::uuid();
                $voucherMap[$v->id] = $newId;

                $arr = (array) $v;
                $arr['id'] = $newId;
                $arr['outlet_id'] = $outlet2Id;
                unset($arr['created_at'], $arr['updated_at']);
                DB::table('vouchers')->insert(array_merge($arr, ['created_at' => now(), 'updated_at' => now()]));
            }

            // E. Menus, Menu Prices, Menu Recipes, Category Menu, Voucher Menu for Outlet 2
            $menus = DB::table('menus')->where('outlet_id', $outlet1Id)->get();
            foreach ($menus as $menu) {
                $newMenuId = (string) Str::uuid();
                
                $arrMenu = (array) $menu;
                $arrMenu['id'] = $newMenuId;
                $arrMenu['outlet_id'] = $outlet2Id;
                unset($arrMenu['created_at'], $arrMenu['updated_at']);
                DB::table('menus')->insert(array_merge($arrMenu, ['created_at' => now(), 'updated_at' => now()]));

                // Duplicate Menu Prices
                $prices = DB::table('menu_prices')->where('menu_id', $menu->id)->get();
                foreach ($prices as $p) {
                    $arrP = (array) $p;
                    $arrP['id'] = (string) Str::uuid();
                    $arrP['menu_id'] = $newMenuId;
                    unset($arrP['created_at'], $arrP['updated_at']);
                    DB::table('menu_prices')->insert(array_merge($arrP, ['created_at' => now(), 'updated_at' => now()]));
                }

                // Duplicate Menu Recipes (Map raw_material_id to Outlet 2 raw materials)
                $recipes = DB::table('menu_recipes')->where('menu_id', $menu->id)->get();
                foreach ($recipes as $r) {
                    if (isset($rawMaterialMap[$r->raw_material_id])) {
                        $arrR = (array) $r;
                        $arrR['id'] = (string) Str::uuid();
                        $arrR['menu_id'] = $newMenuId;
                        $arrR['raw_material_id'] = $rawMaterialMap[$r->raw_material_id];
                        unset($arrR['created_at'], $arrR['updated_at']);
                        DB::table('menu_recipes')->insert(array_merge($arrR, ['created_at' => now(), 'updated_at' => now()]));
                    }
                }

                // Duplicate Category Menu (Pivot)
                $catMenus = DB::table('category_menu')->where('menu_id', $menu->id)->get();
                foreach ($catMenus as $cm) {
                    if (isset($categoryMap[$cm->category_id])) {
                        DB::table('category_menu')->insert([
                            'id' => (string) Str::uuid(),
                            'category_id' => $categoryMap[$cm->category_id],
                            'menu_id' => $newMenuId,
                            'sort' => $cm->sort ?? 0,
                        ]);
                    }
                }
            }

            // F. Voucher Menus (Pivot) for Outlet 2
            foreach ($voucherMap as $oldVoucherId => $newVoucherId) {
                $vMenus = DB::table('voucher_menus')->where('voucher_id', $oldVoucherId)->get();
                foreach ($vMenus as $vm) {
                    // Handle or map voucher menus if needed
                }
            }
        });

        $this->command->info('Multi-outlet data migration completed successfully! Outlet 1 (Kaliurang) & Outlet 2 (Borobudur) are ready.');
    }
}