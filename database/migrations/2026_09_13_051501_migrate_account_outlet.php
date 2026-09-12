<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Hapus unique index lama jika ada
        try { DB::statement('ALTER TABLE accounts DROP INDEX accounts_category_account_number_unique'); } catch (\Throwable $e) {}
        try { DB::statement('ALTER TABLE accounts DROP INDEX accounts_code_unique'); } catch (\Throwable $e) {}
        try { DB::statement('ALTER TABLE accounts DROP INDEX accounts_category_account_number_index'); } catch (\Throwable $e) {}

        // 2. Tambahkan unique index baru hanya jika belum ada
        Schema::table('accounts', function (Blueprint $table) {
            $indexes = collect(DB::select("SHOW INDEXES FROM accounts"))->pluck('Key_name')->unique();

            if (!$indexes->contains('accounts_outlet_category_number_unique')) {
                try {
                    $table->unique(['outlet_id', 'category', 'account_number'], 'accounts_outlet_category_number_unique');
                } catch (\Throwable $e) {}
            }

            if (!$indexes->contains('accounts_outlet_code_unique')) {
                try {
                    $table->unique(['outlet_id', 'code'], 'accounts_outlet_code_unique');
                } catch (\Throwable $e) {}
            }
        });

        // 3. Ambil ID dua outlet pertama
        $outlets = DB::table('outlets')->orderBy('created_at')->limit(2)->pluck('id');
        
        if ($outlets->count() < 2) {
            if ($outlets->count() === 1) {
                DB::table('accounts')->update(['outlet_id' => $outlets[0]]);
            }
            return;
        }

        $outlet1 = $outlets[0];
        $outlet2 = $outlets[1];

        // 4. Set semua akun yang ada saat ini ke outlet id pertama
        DB::table('accounts')->update(['outlet_id' => $outlet1]);

        // 5. Hapus duplikat di outlet kedua jika migrasi dijalankan ulang, lalu salin ulang
        DB::table('accounts')->where('outlet_id', $outlet2)->delete();

        $existingAccounts = DB::table('accounts')->where('outlet_id', $outlet1)->get();

        $newAccountsData = [];
        foreach ($existingAccounts as $acc) {
            $newAccountsData[] = [
                'id'             => (string) Str::uuid(),
                'outlet_id'      => $outlet2,
                'category'       => $acc->category,
                'account_number' => $acc->account_number,
                'code'           => $acc->code,
                'name'           => $acc->name,
                'normal_balance' => $acc->normal_balance,
                'opening_balance'=> $acc->opening_balance,
                'balance'        => $acc->balance,
                'is_active'      => $acc->is_active,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        // 6. Masukkan hasil salinan akun ke outlet kedua
        if (!empty($newAccountsData)) {
            DB::table('accounts')->insert($newAccountsData);
        }
    }

    public function down(): void
    {
        try { DB::statement('ALTER TABLE accounts DROP INDEX accounts_outlet_category_number_unique'); } catch (\Throwable $e) {}
        try { DB::statement('ALTER TABLE accounts DROP INDEX accounts_outlet_code_unique'); } catch (\Throwable $e) {}

        $outlets = DB::table('outlets')->orderBy('created_at')->limit(2)->pluck('id');
        if (isset($outlets[1])) {
            DB::table('accounts')->where('outlet_id', $outlets[1])->delete();
        }
    }
};