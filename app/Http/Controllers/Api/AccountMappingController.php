<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AccountMappingController extends Controller
{
    /**
     * Menampilkan daftar semua pemetaan akun beserta relasi COA-nya.
     * Dipanggil oleh `fetchMappings` di frontend.
     */
    public function index(): JsonResponse
    {
        try {
            // Load relasi debitAccount dan creditAccount agar frontend bisa mereturn nama & kode akun
            $mappings = AccountMapping::with(['debitAccount', 'creditAccount'])->get();

            // Transformasi key agar sesuai dengan camelCase/snakeCase interface di Vue (jika diperlukan)
            // Di sini kita return langsung karena frontend sudah disesuaikan dengan snake_case bawaan Eloquent
            return response()->json([
                'success' => true,
                'data'    => $mappings
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching account mappings: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data konfigurasi jurnal otomatis.'
            ], 500);
        }
    }

    /**
     * Memperbarui pemetaan akun (Debet, Kredit, dan Template Keterangan).
     * Dipanggil oleh `updateMapping` di frontend.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        // 1. Validasi input disesuaikan dengan key database penampung relasi COA Anda
        $validated = $request->validate([
            'debit_account_id'     => 'nullable|uuid|exists:accounts,id',
            'credit_account_id'    => 'nullable|uuid|exists:accounts,id',
            'description_template' => 'nullable|string|max:500', // Ditangkap dari Vue
        ]);

        try {
            $accountMapping = AccountMapping::findOrFail($id);

            // 2. Map payload Vue 'description_template' ke kolom fisik DB 'template'
            $accountMapping->update([
                'debit_account_id'  => $validated['debit_account_id'],
                'credit_account_id' => $validated['credit_account_id'],
                'description_template'          => $validated['description_template'], // Disimpan ke kolom 'template'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Aturan pemetaan jurnal otomatis berhasil diperbarui.',
                'data'    => $accountMapping->load(['debitAccount', 'creditAccount'])
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating account mapping UUID ' . $id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan perubahan aturan pemetaan akun.'
            ], 500);
        }
    }
}