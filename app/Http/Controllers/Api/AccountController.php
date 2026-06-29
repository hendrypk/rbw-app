<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Exception;

class AccountController extends Controller
{
    public function __construct(private AccountService $accountService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'category']);
        $accounts = $this->accountService->getAllAccounts($filters);
        
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
}