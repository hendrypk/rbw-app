<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Modifikasi unique constraint di database terlebih dahulu
        Schema::table('account_mappings', function (Blueprint $table) {
            // Hapus unique index lama pada transaction_type (sesuaikan nama index jika berbeda, misal: account_mappings_transaction_type_unique)
            $table->dropUnique('account_mappings_transaction_type_unique');
            
            // Buat unique index gabungan baru agar per outlet bisa punya transaction_type yang sama
            $table->unique(['outlet_id', 'transaction_type'], 'outlet_transaction_type_unique');
        });

        // 2. Ambil data account_mappings yang sudah ada
        $existingMappings = DB::table('account_mappings')->get();

        // 3. Ambil dua ID outlet pertama dari tabel outlets
        $outlets = DB::table('outlets')->orderBy('created_at')->limit(2)->pluck('id');

        if ($outlets->count() < 2) {
            return; 
        }

        $outletFirstId = $outlets[0];
        $outletSecondId = $outlets[1];

        // 4. Update data yang sudah ada agar terikat ke outlet pertama
        DB::table('account_mappings')->update(['outlet_id' => $outletFirstId]);

        // 5. Duplikasi data mapping untuk outlet kedua dengan UUID baru
        foreach ($existingMappings as $mapping) {
            $data = (array) $mapping;
            
            $data['id'] = (string) Str::uuid(); 
            $data['outlet_id'] = $outletSecondId;
            $data['created_at'] = now();
            $data['updated_at'] = now();

            DB::table('account_mappings')->insert($data);
        }
    }

    public function down(): void
    {
        Schema::table('account_mappings', function (Blueprint $table) {
            $table->dropUnique('outlet_transaction_type_unique');
            $table->unique('transaction_type');
        });

        DB::table('account_mappings')->update(['outlet_id' => null]);
    }
};