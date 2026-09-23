<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\Account;
use App\Services\PosService; // Sesuaikan namespace jika berbeda

class SyncJournal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'journal:sync {--force : Jalankan tanpa konfirmasi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menghapus semua jurnal, mereset saldo akun, dan men-generate ulang jurnal dari order berstatus paid.';

    /**
     * Execute the console command.
     */
public function handle(\App\Services\PosService $posService)
    {
        // Peringatan sebelum eksekusi (bisa di-skip dengan php artisan journal:sync --force)
        if (!$this->option('force') && !$this->confirm('PERINGATAN: Ini akan MENGHAPUS SEMUA JURNAL dan membuatnya ulang. Lanjutkan?')) {
            $this->info('Sinkronisasi dibatalkan.');
            return;
        }

        $this->info('Memulai proses Sinkronisasi Ulang Jurnal...');

        try {
            // 1. HAPUS SEMUA JURNAL (Di luar transaksi agar tidak error Implicit Commit)
            $this->info('1. Menghapus semua data jurnal lama (Entries & Items)...');
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table('journal_items')->truncate();
            DB::table('journal_entries')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            // Mulai Transaksi Database
            DB::beginTransaction();

            // 2. RESET SALDO AKUN
            $this->info('2. Mereset saldo semua akun menjadi 0...');
            \App\Models\Account::query()->update(['balance' => 0]);

            // Siapkan default outlet jika ada order yang outlet-nya kosong
            // $defaultOutlet = \App\Models\Outlet::first();

            // 3. AMBIL ORDER PAID
            $orders = \App\Models\Order::where('status', 'paid')->get();
            $this->info("3. Menemukan {$orders->count()} order berstatus PAID. Memulai penjurnalan ulang...");

            // Buat Progress Bar agar terlihat prosesnya di terminal
            $bar = $this->output->createProgressBar($orders->count());
            $bar->start();

            foreach ($orders as $order) {
                // AUTO-PATCH: Jika outlet_id kosong, set ke outlet pertama
                // if (empty($order->outlet_id) && $defaultOutlet) {
                //     $order->outlet_id = $defaultOutlet->id;
                //     $order->saveQuietly();
                // }

                // Eksekusi fungsi penjurnalan bawaan POS Service Anda
                $posService->recordCheckoutJournals($order);

                // Update jurnal yang baru saja dibuat agar tanggal & outlet sesuai dengan Order
                $journalEntries = DB::table('journal_entries')
                    ->where('reference_id', $order->id)
                    ->get();

                foreach ($journalEntries as $entry) {
                    DB::table('journal_entries')
                        ->where('id', $entry->id)
                        ->update([
                            'outlet_id'  => $order->outlet_id,
                            'entry_date' => $order->created_at->toDateString(),
                            'created_at' => $order->created_at,
                            'updated_at' => $order->updated_at,
                        ]);

                    DB::table('journal_items')
                        ->where('journal_entry_id', $entry->id)
                        ->update([
                            'created_at' => $order->created_at,
                            'updated_at' => $order->updated_at,
                        ]);
                }

                // Majukan progress bar 1 langkah
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            // 4. HITUNG ULANG SALDO
            $this->info('4. Menghitung ulang saldo akhir akun berdasarkan jurnal baru...');
            // Pastikan Anda memiliki fungsi ini di dalam file Command Anda
            $this->recalculateAccountBalances();

            // Simpan semua perubahan
            DB::commit();
            $this->info('SINKRONISASI SELESAI DENGAN SUKSES!');

        } catch (\Exception $e) {
            // Jika ada error di tengah jalan, batalkan semua (Rollback)
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->error('Terjadi kesalahan: ' . $e->getMessage());
        }
    }
    /**
     * Fungsi helper untuk memastikan kolom balance di tabel accounts
     * benar-benar akurat sesuai dengan arus Debit & Kredit di journal_items.
     */
private function recalculateAccountBalances()
    {
        $accounts = \App\Models\Account::all();

        foreach ($accounts as $account) {
            // JOIN ke journal_entries agar kita yakin jurnalnya tidak terhapus (deleted_at null)
            $mutations = DB::table('journal_items')
                ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
                ->where('journal_items.account_id', $account->id)
                ->whereNull('journal_entries.deleted_at') // Pastikan ambil dari entry yang aktif
                ->select(
                    'journal_items.type',
                    DB::raw('SUM(journal_items.amount) as total_amount')
                )
                ->groupBy('journal_items.type')
                ->get();

            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($mutations as $mut) {
                if ($mut->type === 'debit') $totalDebit = (float) $mut->total_amount;
                if ($mut->type === 'credit') $totalCredit = (float) $mut->total_amount;
            }

            // Hitung net balance berdasarkan saldo normal
            $balance = 0;
            if ($account->normal_balance === 'debit') {
                $balance = $totalDebit - $totalCredit;
            } elseif ($account->normal_balance === 'credit') {
                $balance = $totalCredit - $totalDebit;
            }

            // Update ke tabel global account
            $account->update(['balance' => $balance]);
        }
    }
}
