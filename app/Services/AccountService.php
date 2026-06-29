<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Exception;

class AccountService
{
    /**
     * Ambil semua COA dengan filter pencarian & kategori
     */
    public function getAllAccounts(array $filters = [])
    {
        $query = Account::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        return $query->orderBy('code', 'asc')->get();
    }

    /**
     * Simpan Akun Baru
     */
    public function createAccount(array $data): Account
    {
        return Account::create($data);
    }

    /**
     * Update Data Akun
     */
    public function updateAccount(Account $account, array $data): Account
    {
        $account->update($data);
        return $account;
    }

    /**
     * Hapus Akun dengan Proteksi Transaksi Jurnal
     */
    public function deleteAccount(Account $account): void
    {
        // Proteksi: Cek riwayat penggunaan di jurnal_items atau account_mappings
        $isUsedInJournal = DB::table('journal_items')->where('account_id', $account->id)->exists();
        $isUsedInMapping = DB::table('account_mappings')
            ->where('debit_account_id', $account->id)
            ->orWhere('credit_account_id', $account->id)
            ->exists();

        if ($isUsedInJournal || $isUsedInMapping) {
            throw new Exception("Akun tidak bisa dihapus karena sudah memiliki riwayat mutasi jurnal atau terikat di aturan pemetaan otomatis.");
        }

        $account->delete();
    }
}