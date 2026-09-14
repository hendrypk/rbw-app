<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportOrdersData extends Command
{
    protected $signature = 'import:orders';
    protected $description = 'Import orders and order items from CSV file';

    public function handle()
    {
        $path = storage_path('app/private/offline_pos_invoices.csv');

        if (!file_exists($path)) {
            $this->error("File CSV tidak ditemukan di: {$path}");
            return;
        }

        $outletId = DB::table('outlets')->value('id');
        if (!$outletId) {
            $this->error('Outlet tidak ditemukan!');
            return;
        }

        $this->info('Membaca file CSV...');

        $file = fopen($path, 'r');

        // Bersihkan header dari spasi atau BOM tersembunyi
        $header = array_map(function($h) {
            return trim(str_replace("\xEF\xBB\xBF", '', $h));
        }, fgetcsv($file));

        $rows = [];
        while (($data = fgetcsv($file)) !== false) {
            if (count($header) === count($data)) {
                $rows[] = array_combine($header, $data);
            }
        }
        fclose($file);

        // Grouping berdasarkan Ref Number
        $groupedOrders = collect($rows)->groupBy('Ref Number');

        $bar = $this->output->createProgressBar($groupedOrders->count());
        $bar->start();

        DB::transaction(function () use ($groupedOrders, $outletId, $bar) {
            foreach ($groupedOrders as $refNumber => $items) {
                $first = $items->first();

                $transDateInput = $first['Trans Date'] ?? null;
                $transDate = !empty($transDateInput) ? Carbon::parse($transDateInput)->toDateTimeString() : now();
                $createdAt = !empty($first['Created At']) ? $first['Created At'] : now();

                $mmyy = Carbon::parse($transDate)->format('my');
                $random4Digit = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $orderNumber = $mmyy . $random4Digit;

                $orderId = (string) Str::uuid();

                // Ambil customer name dengan fallback berbagai kemungkinan header
                $customerName = $first['name'] ?? $first['Name'] ?? $first['Customer Name'] ?? 'POS Customer';

                DB::table('orders')->insert([
                    'id'             => $orderId,
                    'order_number'   => $orderNumber,
                    'customer_name'  => $customerName,
                    'transaction_at' => $transDate,
                    'final_total'    => $first['Amount'] ?? 0,
                    'created_at'     => $createdAt,
                    'updated_at'     => $createdAt,
                    'outlet_id'      => $outletId
                ]);

                // Insert Order Items
                $orderItems = [];
                foreach ($items as $item) {
                    $menu = DB::table('menus')->where('code', $item['Product Code'])->first();

                    $orderItems[] = [
                        'id'           => (string) Str::uuid(),
                        'order_id'     => $orderId,
                        'menu_id'      => $menu ? $menu->id : null,
                        'quantity'     => $item['Qty'] ?? 1,
                        'price'        => $item['Price'] ?? 0,
                        'created_at'   => $createdAt,
                        'updated_at'   => $createdAt,
                    ];
                }

                if (!empty($orderItems)) {
                    DB::table('order_items')->insert($orderItems);
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->info("\nImport selesai!");
    }
}
