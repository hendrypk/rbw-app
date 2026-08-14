<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    /**
     * Menampilkan daftar jurnal umum dengan filter rentang tanggal
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Validasi parameter filter tanggal yang dikirim dari Vue
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date'   => 'nullable|date_format:Y-m-d',
        ]);

        try {
            // 2. Inisialisasi query dengan eager loading relasi ke items dan master account
            $query = JournalEntry::with(['items.account' => function ($q) {
                $q->select('id', 'category', 'account_number', 'code', 'name');
            }])->orderBy('entry_date', 'desc')
               ->orderBy('created_at', 'desc');

            // 3. Terapkan filter rentang tanggal jika dikirim dari frontend
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $startDate = Carbon::parse($request->start_date)->startOfDay();
                $endDate = Carbon::parse($request->end_date)->endOfDay();
                
                $query->whereBetween('entry_date', [$startDate, $endDate]);
            }

            $entries = $query->get();

            // 4. Transformasi struktur data agar cocok dengan Interface Vue Composable Anda
            $transformedData = $entries->map(function ($entry) {
                return [
                    'id'           => $entry->id,
                    'entry_date'   => $entry->entry_date->format('Y-m-d'),
                    'description'  => $entry->description,
                    'total_amount' => (float) $entry->total_amount,
                    'items'        => $entry->items->map(function ($item) {
                        return [
                            'account_code' => $item->account->code ?? '-',
                            'account_name' => $item->account->name ?? 'Akun Tidak Ditemukan',
                            'type'         => $item->type, // 'debit' atau 'credit'
                            'amount'       => (float) $item->amount,
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data jurnal umum berhasil dimuat.',
                'data'    => $transformedData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data buku jurnal.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
