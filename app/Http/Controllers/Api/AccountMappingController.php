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
public function index(Request $request): JsonResponse
    {
        try {
            $outletId = $request->input('outlet_id');

            $query = AccountMapping::with(['debitAccount', 'creditAccount']);

            if ($outletId && $outletId !== 'all') {
                $query->where('outlet_id', $outletId);
            }

            $mappings = $query->get();

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
        $validated = $request->validate([
            'outlet_id'            => 'nullable|string',
            'debit_account_id'     => 'nullable|string',
            'credit_account_id'    => 'nullable|string',
            'description_template' => 'nullable|string|max:500',
        ]);

        try {
            $accountMapping = AccountMapping::findOrFail($id);

            $accountMapping->update([
                'debit_account_id'     => !empty($validated['debit_account_id']) ? $validated['debit_account_id'] : null,
                'credit_account_id'    => !empty($validated['credit_account_id']) ? $validated['credit_account_id'] : null,
                'description_template' => $validated['description_template'] ?? null,
                'outlet_id'            => !empty($validated['outlet_id']) ? $validated['outlet_id'] : $accountMapping->outlet_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Aturan pemetaan jurnal otomatis berhasil diperbarui.',
                'data'    => $accountMapping->load(['debitAccount', 'creditAccount'])
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating account mapping UUID ' . $id . ': ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan perubahan aturan pemetaan akun: ' . $e->getMessage()
            ], 500);
        }
    }
}