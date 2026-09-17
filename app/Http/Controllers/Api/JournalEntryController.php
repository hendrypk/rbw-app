<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date'   => 'nullable|date_format:Y-m-d',
            'page'       => 'nullable|integer',
        ]);

        try {
            $query = JournalEntry::with(['items.account' => function ($q) {
                $q->select('id', 'category', 'account_number', 'code', 'name');
            }])->orderBy('entry_date', 'desc')
               ->orderBy('created_at', 'desc');

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $startDate = Carbon::parse($request->start_date)->startOfDay();
                $endDate = Carbon::parse($request->end_date)->endOfDay();

                $query->whereBetween('entry_date', [$startDate, $endDate]);
            }

            // Gunakan paginate alih-alih get()
            $perPage = $request->get('per_page', 10);
            $paginatedEntries = $query->paginate($perPage);

            // Transformasi data item di dalam paginator tanpa menghilangkan meta paginasi
            $paginatedEntries->getCollection()->transform(function ($entry) {
                return [
                    'id'           => $entry->id,
                    'entry_date'   => $entry->entry_date->format('Y-m-d'),
                    'description'  => $entry->description,
                    'total_amount' => (float) $entry->total_amount,
                    'is_manual_journal' => $entry->is_manual_journal,
                    'items'        => $entry->items->map(function ($item) {
                        return [
                            'account_code' => $item->account->code ?? '-',
                            'account_name' => $item->account->name ?? 'Akun Tidak Ditemukan',
                            'type'         => $item->type,
                            'amount'       => (float) $item->amount,
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data jurnal umum berhasil dimuat.',
                'data'    => $paginatedEntries // Berisi data collection sekaligus meta paginasi (current_page, last_page, total, dll)
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data buku jurnal.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'entry_date'   => 'required|date_format:Y-m-d',
            'description'  => 'required|string|max:255',
            'items'        => 'required|array|min:2', // Jurnal berpasangan minimal 2 baris (debit & kredit)
            'items.*.account_id' => 'required|exists:accounts,id',
            'items.*.type'       => 'required|in:debit,credit',
            'items.*.amount'     => 'required|numeric|min:0.01',
        ]);

        try {
            // Validasi keseimbangan Double-Entry (Total Debet == Total Kredit)
            $totalDebit = collect($request->items)->where('type', 'debit')->sum('amount');
            $totalCredit = collect($request->items)->where('type', 'credit')->sum('amount');

            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                return response()->json([
                    'success' => false,
                    'message' => "Jurnal tidak seimbang (Unbalanced). Total Debet (Rp " . number_format($totalDebit, 2, ',', '.') . ") harus sama dengan Total Kredit (Rp " . number_format($totalCredit, 2, ',', '.') . ")."
                ], 422);
            }

            $outletId = session('active_outlet_id') ?? $request->outlet_id;

            \DB::transaction(function () use ($request, $totalDebit, $outletId) {
                $entry = JournalEntry::create([
                    'outlet_id'    => $outletId,
                    'entry_date'   => $request->entry_date,
                    'description'  => $request->description,
                    'total_amount' => $totalDebit,
                    'is_manual_journal' => true,
                ]);

                foreach ($request->items as $item) {
                    $entry->items()->create([
                        'account_id' => $item['account_id'],
                        'type'       => $item['type'],
                        'amount'     => $item['amount'],
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Entri jurnal umum berhasil disimpan.',
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan jurnal.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'entry_date'   => 'required|date',
            'description'  => 'required|string|max:255',
            'items'        => 'required|array|min:2',
            'items.*.account_id' => 'required|uuid|exists:accounts,id',
            'items.*.type'       => 'required|in:debit,credit',
            'items.*.amount'     => 'required|numeric|min:0.01',
        ]);

        // Validasi keseimbangan Debet & Kredit di backend
        $totalDebit = collect($request->items)->where('type', 'debit')->sum('amount');
        $totalCredit = collect($request->items)->where('type', 'credit')->sum('amount');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Total Debet dan Kredit harus seimbang (balance).'
            ], 422);
        }

        try {
            $journal = JournalEntry::findOrFail($id);

            // Pastikan hanya jurnal manual yang dapat diubah (opsional, sesuaikan kebutuhan)
            if ($journal->reference_type !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jurnal otomatis dari sistem tidak dapat diubah.'
                ], 403);
            }

            \DB::transaction(function () use ($request, $journal, $totalDebit) {
                // 1. Update informasi utama jurnal
                $journal->update([
                    'entry_date'   => $request->entry_date,
                    'description'  => $request->description,
                    'total_amount' => $totalDebit,
                    'is_manual_journal' => true,
                ]);

                // 2. Hapus item jurnal lama (atau lakukan sinkronisasi)
                $journal->items()->delete();

                // 3. Masukkan item jurnal yang baru
                foreach ($request->items as $item) {
                    $journal->items()->create([
                        'account_id' => $item['account_id'],
                        'type'       => $item['type'],
                        'amount'     => $item['amount'],
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Jurnal umum berhasil diperbarui.',
                'data'    => $journal->load('items.account')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui jurnal: ' . $e->getMessage()
            ], 500);
        }
    }
    public function destroy($id)
    {
        try {
            $journal = JournalEntry::findOrFail($id);

            // Validasi pengaman: Jurnal otomatis sistem tidak boleh dihapus
            if (!$journal->is_manual_journal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jurnal otomatis dari sistem tidak dapat dihapus.'
                ], 403);
            }

            \DB::transaction(function () use ($journal) {
                // Hapus item jurnal berpasangan terlebih dahulu (jika tidak menggunakan onDelete('cascade'))
                $journal->items()->delete();

                // Hapus entri utama jurnal
                $journal->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Jurnal manual berhasil dihapus.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus jurnal: ' . $e->getMessage()
            ], 500);
        }
    }
}
