<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Menu;
use App\Models\Outlet;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ImportInvoicesJson extends Command
{
    protected $signature = 'import:invoices-pos';
    protected $description = 'Import data invoice POS Customer dari SQLite kledo_invoices dengan strict transaction (gagal 1, batal semua)';

    public function handle()
    {
        $this->info("=================================================");
        $this->info("STEP 1: Memeriksa koneksi SQLite & Outlet...");
        $this->info("=================================================");

        if (!DB::connection('sqlite_kledo')->getSchemaBuilder()->hasTable('invoices')) {
            $this->error("❌ Tabel 'invoices' tidak ditemukan di database sqlite_kledo!");
            return Command::FAILURE;
        }

        $firstOutlet = Outlet::first();
        $outletId = $firstOutlet ? $firstOutlet->id : null;

        if (!$outletId) {
            $this->error("❌ Tidak ada data outlet di tabel 'outlets'! Buat minimal 1 outlet terlebih dahulu.");
            return Command::FAILURE;
        }

        $this->info("✅ Menggunakan Outlet ID: {$outletId}");

        $query = DB::connection('sqlite_kledo')->table('invoices')->orderBy('id');
        $total = $query->count();

        if ($total === 0) {
            $this->warn("⚠️ Tabel 'invoices' kosong.");
            return Command::SUCCESS;
        }

        $this->info("✅ Ditemukan total {$total} baris data mentah. Memulai proses...");
        $this->newLine();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $successCount = 0;
        $skippedCount = 0;

        // Gunakan chunk biasa, tapi tanpa try-catch penelan error agar jika ada yang gagal,
        // command langsung berhenti total dan menampilkan exception aslinya.
        $query->chunk(100, function ($invoices) use (
            &$successCount,
            &$skippedCount,
            $bar,
            $outletId
        ) {
            foreach ($invoices as $inv) {
                $jsonData = json_decode($inv->raw_data, true);

                if (!is_array($jsonData)) {
                    throw new \Exception("Format JSON tidak valid pada invoice ID: {$inv->id}");
                }

                // Filter: Hanya ambil jika customer name adalah "POS Customer"
                $customerName = $jsonData['contact']['name'] ?? '';
                if ($customerName !== 'POS Customer') {
                    $skippedCount++;
                    $bar->advance();
                    continue;
                }

                $transDate = $jsonData['trans_date'] ?? now()->toDateString();
                $rawCreatedAt = $jsonData['log']['action']['created_at'] ?? null;
                $createdAt = $rawCreatedAt ? Carbon::parse($rawCreatedAt)->toDateTimeString() : ($transDate . ' 00:00:00');

                // Generate order_number format MMYY + 4 digit random
                $dateObj = Carbon::parse($transDate);
                $mmyy = $dateObj->format('my');
                $randomFour = 'ORD-' . date('YmdHis') . '-' . rand(1000, 9999);
                $orderNumber = $mmyy . $randomFour;

                $subtotal = $jsonData['subtotal'] ?? 0;
                $discount = $jsonData['total_additional_discounts'] ?? ($jsonData['discount_amount'] ?? 0);
                $finalTotal = $jsonData['amount'] ?? 0;

                // 🌟 DB Transaction Ketat: Jika ada 1 error di dalam block ini,
                // otomatis rollback dan menghentikan seluruh proses command.
                DB::transaction(function () use ($jsonData, $orderNumber, $transDate, $outletId, $customerName, $subtotal, $discount, $finalTotal, $rawCreatedAt) {
                    $orderId = (string) Str::uuid();

                    $order = Order::create([
                        'id'             => $orderId,
                        'order_number'   => $orderNumber,
                        'transaction_at' => $transDate,
                        'outlet_id'      => $outletId,
                        'customer_name'  => $customerName,
                        'total_hpp'      => 0.00,
                        'total_overhead' => 0.00,
                        'subtotal'       => $subtotal,
                        'discount'       => $discount,
                        'final_total'    => $finalTotal,
                        'payment_method' => 'cash',
                        'status'         => 'unpaid',
                        'created_at'     => $rawCreatedAt,
                        'updated_at'     => $rawCreatedAt,
                    ]);

                    if (!empty($jsonData['items']) && is_array($jsonData['items'])) {
                        $orderItemsData = [];

                        foreach ($jsonData['items'] as $itemData) {
$productSku   = $itemData['product']['code'] ?? null;
        $productName  = $itemData['product']['name'] ?? 'Menu ' . $productSku;
        $productPrice = $itemData['price'] ?? 0;
        $qty          = $itemData['qty'] ?? 1;

                            // Jika produk wajib ada di database lokal, cari.
                            // Jika tidak ditemukan dan ingin dibatalkan, sesuaikan logikanya di sini.
                            $product = null;
if ($productSku) {
            $product = Menu::firstOrCreate(
                [
                    'code'      => $productSku,
                    'outlet_id' => $outletId, // Pastikan variabel $outletId sudah didefinisikan sebelumnya
                ],
                [
                    'id'        => (string) \Illuminate\Support\Str::uuid(), // Hapus baris ini jika Model sudah memakai HasUuids
                    'name'      => $productName,
                    'is_active' => 1,
                ]
            );
        }

                            $orderItemsData[] = [
                                'id'         => (string) Str::uuid(),
                                'order_id'   => $order->id,
                                'menu_id' => $product->id,
                                // 'sku'        => $productSku,
                                'price'      => $productPrice,
                                'quantity'        => $qty,
                                'subtotal'     => $itemData['amount'] ?? ($productPrice * $qty),
                                'created_at' => $rawCreatedAt,
                                'updated_at' => $rawCreatedAt,
                            ];
                        }

                        if (!empty($orderItemsData)) {
                            OrderItem::insert($orderItemsData);
                        }
                    }
                });

                $successCount++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("=================================================");
        $this->info("STEP 4: REKAPITULASI HASIL IMPORT");
        $this->info("=================================================");
        $this->line(" - Berhasil Diimport (POS) : {$successCount} invoice");
        $this->line(" - Dilewati (Selain POS)  : {$skippedCount} invoice");
        $this->info("=================================================");
        $this->info("✅ Seluruh proses import berhasil tanpa ada error.");

        return Command::SUCCESS;
    }
}
