<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Menu;
use App\Models\Outlet;

class CheckMenuDifferences extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'menu:check-diff
                            {outlet_a_id : ID outlet referensi (Misal: Jakal 12)}
                            {outlet_b_id : ID outlet yang akan dicek (Misal: Borobudur)}';

    /**
     * The console command description.
     */
    protected $description = 'Mengecek menu yang ada di Outlet A tetapi TIDAK ADA di Outlet B berdasarkan ID (mengabaikan urutan kata)';

    /**
     * Helper: Menormalisasi nama menu dengan mengurutkan kata-katanya
     * Contoh: "Roti Bakar Keju" -> ["bakar", "keju", "roti"] -> "bakar keju roti"
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
        $outletAId = $this->argument('outlet_a_id');
        $outletBId = $this->argument('outlet_b_id');

        $outletA = Outlet::find($outletAId);
        $outletB = Outlet::find($outletBId);

        if (!$outletA) {
            $this->error("Outlet A dengan ID ({$outletAId}) tidak ditemukan!");
            return Command::FAILURE;
        }

        if (!$outletB) {
            $this->error("Outlet B dengan ID ({$outletBId}) tidak ditemukan!");
            return Command::FAILURE;
        }

        $this->info("Membandingkan Menu: {$outletA->name} vs {$outletB->name}...");
        $this->newLine();

        $menusA = Menu::where('outlet_id', $outletA->id)->get(['code', 'name']);
        $menusB = Menu::where('outlet_id', $outletB->id)->get(['code', 'name']);

        $this->info("Total Menu {$outletA->name}: " . $menusA->count());
        $this->info("Total Menu {$outletB->name}: " . $menusB->count());
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
            $this->info("✅ Semua menu di {$outletA->name} sudah terdaftar di {$outletB->name}.");
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
