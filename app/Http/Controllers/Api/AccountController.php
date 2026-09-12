<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function __construct(private AccountService $accountService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $outletId = $request->input('outlet_id', 'all');

        $filters = $request->only(['search', 'category']);
        $accounts = $this->accountService->getAllAccounts($filters, $outletId);
        
        return response()->json($accounts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category'       => ['required', Rule::in(['1', '2', '3', '4', '5'])],
            'account_number' => [
                'required', 
                'string', 
                'max:20',
                Rule::unique('accounts')->where(function ($query) use ($request) {
                    return $query->where('category', $request->category)
                                 ->where('account_number', trim($request->account_number));
                })
            ],
            'name'           => ['required', 'string', 'max:255'],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
            'is_active'      => ['boolean']
        ], [
            'account_number.unique' => 'Nomor akun ini sudah terpakai di bawah kategori terpilih.'
        ]);

        $account = $this->accountService->createAccount($data);

        return response()->json([
            'message' => 'Akun rekening berhasil ditambahkan.',
            'data'    => $account
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Account $account): JsonResponse
    {
        return response()->json($account);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Account $account): JsonResponse
    {
        $data = $request->validate([
            'category'       => ['required', Rule::in(['1', '2', '3', '4', '5'])],
            'account_number' => [
                'required', 
                'string', 
                'max:20',
                Rule::unique('accounts')->where(function ($query) use ($request, $account) {
                    return $query->where('category', $request->category)
                                 ->where('account_number', trim($request->account_number));
                })->ignore($account->id)
            ],
            'name'           => ['required', 'string', 'max:255'],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
            'is_active'      => ['boolean']
        ], [
            'account_number.unique' => 'Nomor akun ini sudah terpakai di bawah kategori terpilih.'
        ]);

        $updatedAccount = $this->accountService->updateAccount($account, $data);

        return response()->json([
            'message' => 'Data akun rekening berhasil diperbarui.',
            'data'    => $updatedAccount
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Account $account): JsonResponse
    {
        try {
            $this->accountService->deleteAccount($account);
            
            return response()->json([
                'message' => 'Akun berhasil dihapus.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
public function updateOpeningBalances(Request $request): JsonResponse
{
    $request->validate([
        'balances' => 'required|array',
        'balances.*.id' => 'required|exists:accounts,id',
        'balances.*.opening_balance' => 'required|numeric|min:0',
        'effective_date' => 'required|date',
    ]);

    try {
        // Validasi Kronologis: Tanggal saldo awal tidak boleh lebih baru dari jurnal terlama yang sudah ada
        $oldestJournalDate = \App\Models\JournalEntry::min('entry_date');
        
        if ($oldestJournalDate && $request->effective_date > $oldestJournalDate) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal efektif saldo awal (' . $request->effective_date . ') tidak boleh lebih baru dari tanggal transaksi jurnal terlama (' . $oldestJournalDate . '). Saldo awal harus mendahului seluruh transaksi.'
            ], 422);
        }

        DB::transaction(function () use ($request) {
            $totalDebit = 0;
            $totalCredit = 0;
            $journalItemsData = [];

            foreach ($request->balances as $item) {
                $account = Account::find($item['id']);
                if (!$account) continue;

                $newOpening = (float) $item['opening_balance'];
                $oldOpening = (float) $account->opening_balance;
                $selisih = $newOpening - $oldOpening;

                // Update opening_balance dan balance berjalan di model Account
                $account->opening_balance = $newOpening;
                $account->balance = (float) $account->balance + $selisih;
                $account->save();

                if ($newOpening > 0) {
                    $isDebit = $account->normal_balance === 'debit';
                    
                    if ($isDebit) {
                        $totalDebit += $newOpening;
                    } else {
                        $totalCredit += $newOpening;
                    }

                    // Format array disesuaikan dengan skema JournalItem (type & amount)
                    $journalItemsData[] = [
                        'account_id' => $account->id,
                        'type'       => $isDebit ? 'debit' : 'credit',
                        'amount'     => $newOpening,
                    ];
                }
            }

            // Validasi Keseimbangan (Double-Entry Balance Check)
            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                throw new \InvalidArgumentException("Total saldo Debit (" . number_format($totalDebit, 2, ',', '.') . ") dan Kredit (" . number_format($totalCredit, 2, ',', '.') . ") harus seimbang.");
            }

            // Simpan Jurnal Utama
            $journalEntry = \App\Models\JournalEntry::create([
                'outlet_id'      => session('active_outlet_id'),
                'entry_date'     => $request->effective_date,
                'description'    => 'Saldo Awal Periode Akuntansi',
                'reference_type' => Account::class,
                'reference_id'   => null,
                'total_amount'   => $totalDebit,
            ]);

            // Simpan Item Jurnal
            foreach ($journalItemsData as $jItem) {
                $journalEntry->items()->create($jItem);
            }
        });
    } catch (\InvalidArgumentException $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 422);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal mencatat jurnal saldo awal: ' . $e->getMessage()
        ], 500);
    }

    return response()->json([
        'success' => true,
        'message' => 'Saldo awal berhasil dicatat ke jurnal dan neraca seimbang.'
    ]);
}
}