<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountMapping extends Model
{
    use HasUuids;

    protected $fillable = [
        'transaction_event',
        'debit_account_id',
        'credit_account_id',
        'description_template'
    ];

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
    }

    /**
     * Helper untuk membuat deskripsi teks dinamis dari template
     * Contoh: mengubah "POS #{{no}}" menjadi "POS #20260001"
     */
    public function parseDescription(array $replacements): string
    {
        $template = $this->description_template ?? 'Transaksi Otomatis: ' . $this->transaction_event;
        
        foreach ($replacements as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        
        return $template;
    }
}