<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Menu;
use App\Models\Outlet;

// Pastikan nama class SAMA dengan nama file (MenuSync.php)
class MenuSync extends Command
{
    /**
     * Signature bersih tanpa parameter (akan jalan secara interaktif)
     */
    protected $signature = 'menu:check-diff';

    /**
     * Deskripsi command
     */
    protected $description = 'Mengecek perbedaan menu antar outlet secara interaktif';

    /**
     * Helper: Menormalisasi nama menu
     */
    private function normalizeMenuName($name)
    {
        $cleanStr = strtolower(trim(preg_replace('/\s+/', ' ', $name)));
        $words = explode(' ', $cleanStr);
        sort($words);
        return implode(' ', $words);
    }

    public function handle()
    {
        $this->info("Mengambil data outlet dari database...");

        $outlets = Outlet::all(['id', 'name']);

        if ($outlets->count() < 2) {
            $this->error("Minimal harus ada 2 outlet di database untuk melakukan perbandingan!");
            return Command::FAILURE;
        }

        $outletNames = $outlets->pluck('name')->toArray();

        $outletAName = $this->choice(
            'Pilih Outlet Referensi (Outlet A / Acuan)',
            $outletNames
        );
        $outletA = $outlets->where('name', $outletAName)->first();

        $outletBName = $this->choice(
            'Pilih Outlet Target (Outlet B / Yang akan dicek)',
            $outletNames
        );
        $outletB = $outlets->where('name', $outletBName)->first();

        if ($outletA->id === $outletB->id) {
            $this->error("Anda memilih outlet yang sama! Perbandingan dibatalkan.");
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info("Membandingkan Menu: [{$outletA->name}] vs [{$outletB->name}]...");
        $this->newLine();

        $menusA = Menu::where('outlet_id', $outletA->id)->get(['code', 'name']);
        $menusB = Menu::where('outlet_id', $outletB->id)->get(['code', 'name']);

        $this->info("Total Menu {$outletA->name} : " . $menusA->count());
        $this->info("Total Menu {$outletB->name} : " . $menusB->count());
        $this->newLine();

        $normalizedNamesB = [];
        foreach ($menusB as $mb) {
            $normalizedNamesB[] = $this->normalizeMenuName($mb->name);
        }

        $missingInB = [];
        foreach ($menusA as $ma) {
            $normalizedNameA = $this->normalizeMenuName($ma->name);

            if (!in_array($normalizedNameA, $normalizedNamesB)) {
                $missingInB[] = [
                    'Code' => $ma->code,
                    'Menu di Outlet A' => $ma->name
                ];
            }
        }

        if (empty($missingInB)) {
            $this->info("✅ Semua menu di {$outletA->name} sudah ada di {$outletB->name}.");
        } else {
            $this->warn("⚠️ Ditemukan " . count($missingInB) . " menu di {$outletA->name} yang TIDAK ADA di {$outletB->name}:");

            $this->table(
                ['Kode Menu', 'Nama Menu (Belum ada di ' . $outletB->name . ')'],
                array_map(function($item) {
                    return [$item['Code'], $item['Menu di Outlet A']];
                }, $missingInB)
            );
        }

        return Command::SUCCESS;
    }
}
