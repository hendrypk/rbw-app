<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountMapping extends Model
{
    use HasUuids;

    protected $fillable = [
        'transaction_event',   // Sesuai seeder & data dummy
        'debit_account_id',
        'credit_account_id',
        'template'             // Ganti description_template menjadi template agar match ke DB
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
     */
    public function parseDescription(array $replacements): string
    {
        // Gunakan $this->template sesuai nama kolom fisik MySQL
        $template = $this->template ?? 'Transaksi Otomatis: ' . $this->transaction_event;
        
        foreach ($replacements as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        
        return $template;
    }
}