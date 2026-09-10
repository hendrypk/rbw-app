<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Exception;

class JournalEntry extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['outlet_id', 'entry_date', 'reference_type', 'reference_id', 'description', 'total_amount'];
    protected $casts = ['entry_date' => 'date', 'total_amount' => 'decimal:2'];

    public function items(): HasMany
    {
        return $this->hasMany(JournalItem::class, 'journal_entry_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * CORE ENGINE: Membuat jurnal otomatis dengan opsi akun dinamis dan tanggal kustom dari user
     */
    public static function createEntryFromMapping(
        string $type, 
        float $j1Amount, 
        float $j2Amount = 0, 
        ?Model $reference = null, 
        array $replacements = [],
        ?string $customDebitAccountId = null,
        ?string $customCreditAccountId = null,
        ?string $entryDate = null // ⬅️ Tambahkan parameter tanggal kustom
    ): void {
        // Ambil outlet_id dari reference (misal: Order) atau dari session/request aktif
        $outletId = $reference->outlet_id ?? session('active_outlet_id');

        $mapping = AccountMapping::where('transaction_type', $type)
            ->where(function($query) use ($outletId) {
                $query->where('outlet_id', $outletId)->orWhereNull('outlet_id');
            })
            ->first();
            
        if (!$mapping) throw new Exception("Mapping untuk tipe '{$type}' belum terdaftar.");

        // Tentukan akun riil yang dipakai (Prioritas input user, fallback ke mapping)
        $debitAccountJ1 = $customDebitAccountId ?? $mapping->debit_account_id;
        $creditAccountJ1 = $customCreditAccountId ?? $mapping->credit_account_id;

        if (!$debitAccountJ1) {
            throw new Exception("Gagal menjurnal: Transaksi '{$type}' membutuhkan input pilihan akun Debet dari user.");
        }
        if (!$creditAccountJ1) {
            throw new Exception("Gagal menjurnal: Transaksi '{$type}' membutuhkan input pilihan akun Kredit dari user.");
        }

        // Tentukan tanggal jurnal (Gunakan input user jika ada, jika tidak fallback ke hari ini)
        $dateToUse = $entryDate ?? now()->toDateString();

        DB::transaction(function () use ($mapping, $j1Amount, $j2Amount, $reference, $replacements, $debitAccountJ1, $creditAccountJ1, $outletId, $dateToUse) {
            
            // --- AYAT JURNAL 1 ---
            if ($j1Amount > 0) {
                $desc1 = self::parseTemplate($mapping->description_template ?? 'Transaksi', $replacements);
                $entry1 = self::create([
                    'outlet_id'      => $outletId,
                    'entry_date'     => $dateToUse, // ⬅️ Gunakan tanggal kustom
                    'reference_type' => $reference ? get_class($reference) : null,
                    'reference_id'   => $reference ? $reference->id : null,
                    'description'    => $desc1,
                    'total_amount'   => $j1Amount
                ]);
                $entry1->items()->create(['account_id' => $debitAccountJ1, 'type' => 'debit', 'amount' => $j1Amount]);
                $entry1->items()->create(['account_id' => $creditAccountJ1, 'type' => 'credit', 'amount' => $j1Amount]);
            }

            // --- AYAT JURNAL 2 (Stok & HPP) ---
            if ($j2Amount > 0 && $mapping->j2_debit_account_id && $mapping->j2_credit_account_id) {
                $desc2 = self::parseTemplate($mapping->j2_description_template ?? 'Transaksi', $replacements);
                $entry2 = self::create([
                    'outlet_id'      => $outletId,
                    'entry_date'     => $dateToUse, // ⬅️ Gunakan tanggal kustom
                    'reference_type' => $reference ? get_class($reference) : null,
                    'reference_id'   => $reference ? $reference->id : null,
                    'description'    => $desc2,
                    'total_amount'   => $j2Amount
                ]);
                $entry2->items()->create(['account_id' => $mapping->j2_debit_account_id, 'type' => 'debit', 'amount' => $j2Amount]);
                $entry2->items()->create(['account_id' => $mapping->j2_credit_account_id, 'type' => 'credit', 'amount' => $j2Amount]);
            }
        });
    }

    /**
     * REVERSAL AUTOMATION: Membalik otomatis debet-kredit jika void/cancel/delete order
     */
    public static function reverseEntriesFor(?Model $reference, string $reason = 'Void/Cancel Transaksi'): void
    {
        if (!$reference) return;

        DB::transaction(function () use ($reference, $reason) {
            $existingEntries = self::where('reference_type', get_class($reference))
                                   ->where('reference_id', $reference->id)
                                   ->with('items')
                                   ->get();

            foreach ($existingEntries as $oldEntry) {
                $newEntry = self::create([
                    'outlet_id'      => $oldEntry->outlet_id,
                    'entry_date'     => now(),
                    'reference_type' => get_class($reference),
                    'reference_id'   => $reference->id,
                    'description'    => "[REVERSAL] RE: {$oldEntry->description} ({$reason})",
                    'total_amount'   => $oldEntry->total_amount
                ]);

                foreach ($oldEntry->items as $oldItem) {
                    $newEntry->items()->create([
                        'account_id' => $oldItem->account_id,
                        'type'       => $oldItem->type === 'debit' ? 'credit' : 'debit', 
                        'amount'     => $oldItem->amount
                    ]);
                }

                $oldEntry->delete();
            }
        });
    }

    private static function parseTemplate(string $template, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        return $template;
    }
}