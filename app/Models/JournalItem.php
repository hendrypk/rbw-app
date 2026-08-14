<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalItem extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'type',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    protected static function booted(): void
    {
        // 1. Saat item jurnal baru tercipta (Debit / Credit)
        static::created(function ($item) {
            $item->account->updateBalance($item->type, (float) $item->amount);
        });

        // 2. Saat terjadi Reversal / Soft Delete / Hard Delete pada jurnal lama
        static::deleted(function ($item) {
            // Balik arah mutasi untuk mengembalikan saldo ke posisi semula
            $reverseType = $item->type === 'debit' ? 'credit' : 'debit';
            $item->account->updateBalance($reverseType, (float) $item->amount);
        });
    }
}