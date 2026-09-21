<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Menu;
use App\Models\Outlet;
use App\Models\MenuPrice;
use App\Models\MenuRecipe;

class MenuSync extends Command
{
    /**
     * Signature command (interaktif)
     */
    protected $signature = 'menu:check-diff';

    /**
     * Deskripsi
     */
    protected $description = 'Validasi SKU referensi, cek perbedaan, lalu duplikasi menu beserta relasinya ke target dengan output tabel detail';

    /**
     * Helper: Menormalisasi nama menu (mengabaikan spasi/urutan kata)
     */
    private function normalizeMenuName($name)
    {
        $cleanStr = strtolower(trim(preg_replace('/\s+/', ' ', $name)));
        $words = explode(' ', $cleanStr);
        sort($words);
        return implode(' ', $words);
    }

    /**
     * Helper: Validasi format SKU (Format: SKU/xxxxxx, contoh SKU/001001)
     */
    private function isValidSkuFormat(?string $code): bool
    {
        if (empty($code)) {
            return false;
        }
        // Validasi diawali SKU/ lalu dilanjut angka minimal 6 digit
        return (bool) preg_match('/^SKU\/\d{6,}$/i', trim($code));
    }

    /**
     * Helper: Mendapatkan nomor urut tertinggi saat ini dari format SKU/001001
     */
    private function getMaxProductSequenceForOutlet(string $outletSeq): int
    {
        $maxSeq = 0;
        // Ambil semua kode menu yang diawali dengan SKU/{outletSeq} (misal: SKU/001...)
        $allCodes = Menu::where('code', 'LIKE', "SKU/{$outletSeq}%")->pluck('code');

        foreach ($allCodes as $code) {
            // Ambil 3 digit terakhir dari format SKU/001005 -> ambil 005
            if (preg_match('/^SKU\/' . $outletSeq . '(\d+)$/i', trim($code), $matches)) {
                $seq = (int) $matches[1];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        return $maxSeq;
    }

    /**
     * Helper: Mendapatkan urutan ID Outlet (3 digit)
     */
    private function getOutletSequence(Outlet $outlet): string
    {
        $outlets = Outlet::orderBy('created_at', 'asc')->pluck('id')->toArray();
        $index = array_search($outlet->id, $outlets);

        if ($index === false) {
            $index = 0;
        }

        return str_pad($index + 1, 3, '0', STR_PAD_LEFT);
    }

    public function handle()
    {
        $this->info("Mengambil data outlet dari database...");

        $outlets = Outlet::all(['id', 'name', 'created_at']);

        if ($outlets->count() < 2) {
            $this->error("Minimal harus ada 2 outlet di database untuk melakukan perbandingan!");
            return Command::FAILURE;
        }

        $outletNames = $outlets->pluck('name')->toArray();

        // 1. Prompt Pemilihan Outlet
        $outletAName = $this->choice('Pilih Outlet Referensi (Acuan Menu)', $outletNames);
        $outletA = $outlets->where('name', $outletAName)->first();

        $outletBName = $this->choice('Pilih Outlet Target (Tujuan Duplikasi)', $outletNames);
        $outletB = $outlets->where('name', $outletBName)->first();

        if ($outletA->id === $outletB->id) {
            $this->error("Anda memilih outlet yang sama! Perbandingan dibatalkan.");
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info("=================================================");
        $this->info("STEP 1: MERAPIKAN KODE SKU PADA OUTLET REFERENSI");
        $this->info("=================================================");

        // Ambil semua menu dari outlet referensi
        $menusA = Menu::where('outlet_id', $outletA->id)->get();
        $invalidMenusA = [];
        $previewTableA = [];

        foreach ($menusA as $m) {
            if (!$this->isValidSkuFormat($m->code)) {
                $invalidMenusA[] = $m;
                $previewTableA[] = [
                    'Nama Menu' => $m->name,
                    'Kode Saat Ini' => $m->code ?? 'NULL (Kosong)',
                ];
            }
        }

        if (!empty($invalidMenusA)) {
            $this->warn("⚠️ Ditemukan " . count($invalidMenusA) . " menu di [{$outletA->name}] dengan kode NULL atau format SKU tidak standar:");

            // Tampilkan Tabel Preview SEBELUM konfirmasi
            $this->table(['Nama Menu', 'Kode Saat Ini'], $previewTableA);
            $this->newLine();

            // Ubah teks konfirmasi
            if ($this->confirm("Apakah Anda ingin memperbarui kode menu di atas menjadi format SKU/xxxxxx (contoh: SKU/001001) secara otomatis?")) {

                $outletSeq = $this->getOutletSequence($outletA);
                $currentMaxSeq = $this->getMaxProductSequence();

                DB::beginTransaction();
                try {
                    $fixedTable = [];
                    foreach ($invalidMenusA as $menuToFix) {
                        $currentMaxSeq++;
                        // Pad urutan produk dengan 3 digit (contoh: 001, 002)
                        $productSeq = str_pad($currentMaxSeq, 3, '0', STR_PAD_LEFT);

                        // Gabungkan outletSeq (3 digit) dan productSeq (3 digit) tanpa slash
                        $newCode = "SKU/{$outletSeq}{$productSeq}";

                        $oldCode = $menuToFix->code ?? 'NULL';
                        $menuToFix->code = $newCode;
                        $menuToFix->save();

                        $fixedTable[] = [
                            'Nama Menu' => $menuToFix->name,
                            'Kode Lama' => $oldCode,
                            'Kode Baru' => $newCode,
                        ];
                    }

                    DB::commit();
                    $this->newLine();
                    $this->info(" HASIL PERBAIKAN SKU:");
                    // Tampilkan Tabel Hasil SETELAH update
                    $this->table(['Nama Menu', 'Kode Lama', 'Kode Baru'], $fixedTable);
                    $this->info("✅ Berhasil merapikan " . count($invalidMenusA) . " kode SKU di [{$outletA->name}].");

                    // Refresh data menu outlet A setelah perbaikan agar Step 2 membaca kode yang baru
                    $menusA = Menu::where('outlet_id', $outletA->id)->get();

                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("❌ Gagal memperbarui kode SKU: " . $e->getMessage());
                    return Command::FAILURE;
                }
            } else {
                $this->warn("Pembersihan SKU dilewati. Proses lanjut menggunakan kode yang ada.");
            }
        } else {
            $this->info("✅ Semua kode SKU di [{$outletA->name}] sudah dalam format yang benar.");
        }

        $this->newLine();
        $this->info("=================================================");
        $this->info("STEP 2: CEK PERBEDAAN & DUPLIKASI KE TARGET");
        $this->info("=================================================");

        // Ambil data menu outlet B
        $menusB = Menu::where('outlet_id', $outletB->id)->get();

        $this->info("Total Menu {$outletA->name} : " . $menusA->count());
        $this->info("Total Menu {$outletB->name} : " . $menusB->count());
        $this->newLine();

        $normalizedNamesB = [];
        foreach ($menusB as $mb) {
            $normalizedNamesB[] = $this->normalizeMenuName($mb->name);
        }

        $missingMenus = [];
        $missingTableData = [];

        foreach ($menusA as $ma) {
            $normalizedNameA = $this->normalizeMenuName($ma->name);

            if (!in_array($normalizedNameA, $normalizedNamesB)) {
                $missingMenus[] = $ma;
                $missingTableData[] = [
                    'Code SKU' => $ma->code,
                    'Nama Menu' => $ma->name,
                ];
            }
        }

        if (empty($missingMenus)) {
            $this->info("✅ Semua menu di {$outletA->name} sudah terdaftar di {$outletB->name}.");
            return Command::SUCCESS;
        }

        // Tampilkan tabel daftar menu yang kurang di Target
        $this->warn("⚠️ Ditemukan " . count($missingMenus) . " menu di {$outletA->name} yang TIDAK ADA di {$outletB->name}:");
        $this->table(['Kode SKU', 'Nama Menu'], $missingTableData);
        $this->newLine();

        // KONFIRMASI DUPLIKASI
        if ($this->confirm("Apakah Anda ingin MENDUPLIKASI " . count($missingMenus) . " menu ini (beserta MenuPrice) ke {$outletB->name}?")) {

            $this->info("Memulai proses sinkronisasi duplikasi...");
            $progressBar = $this->output->createProgressBar(count($missingMenus));
            $progressBar->start();

            $duplicatedTableData = []; // Array untuk menampung hasil sukses duplikasi

            DB::beginTransaction();

            try {
                foreach ($missingMenus as $oldMenu) {
                    // a) Duplikasi Master Menu (SKU tetap sama)
                    $newMenu = $oldMenu->replicate();
                    $newMenu->outlet_id = $outletB->id;
                    $newMenu->code = $oldMenu->code;
                    $newMenu->save();

                    // b) Duplikasi MenuPrice
                    $oldPrices = MenuPrice::where('menu_id', $oldMenu->id)->get();
                    foreach ($oldPrices as $oldPrice) {
                        $newPrice = $oldPrice->replicate();
                        $newPrice->menu_id = $newMenu->id;
                        $newPrice->save();
                    }

                    // c) Duplikasi MenuRecipe
                    // $oldRecipes = MenuRecipe::where('menu_id', $oldMenu->id)->get();
                    // foreach ($oldRecipes as $oldRecipe) {
                    //     $newRecipe = $oldRecipe->replicate();
                    //     $newRecipe->menu_id = $newMenu->id;
                    //     $newRecipe->save();
                    // }

                    // Simpan data untuk dirender ke tabel hasil akhir
                    $duplicatedTableData[] = [
                        'Kode SKU' => $newMenu->code,
                        'Nama Menu' => $newMenu->name,
                        'Jml Harga (Price)' => $oldPrices->count() . ' item',
                        // 'Jml Bahan (Recipe)' => $oldRecipes->count() . ' item',
                    ];

                    $progressBar->advance();
                }

                DB::commit();
                $progressBar->finish();

                $this->newLine(2);
                $this->info("=================================================");
                $this->info("REKAPITULASI HASIL DUPLIKASI");
                $this->info("=================================================");

                // Render tabel hasil akhir
                $this->table(['Kode SKU', 'Nama Menu', 'Jml Harga (Price)', 'Jml Bahan (Recipe)'], $duplicatedTableData);

                $this->newLine();
                $this->info("✅ Berhasil! " . count($missingMenus) . " menu beserta relasinya telah disalin ke {$outletB->name} dengan kode SKU yang sama.");

            } catch (\Exception $e) {
                DB::rollBack();
                $progressBar->finish();
                $this->newLine(2);
                $this->error("❌ Gagal! Terjadi kesalahan: " . $e->getMessage());
                $this->info("Proses dibatalkan, tidak ada data yang tersimpan (Rollback sukses).");
                return Command::FAILURE;
            }
        } else {
            $this->info("Proses sinkronisasi dibatalkan oleh pengguna.");
        }

        return Command::SUCCESS;
    }
}
