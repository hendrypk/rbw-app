<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'category', 
        'account_number', 
        'code', 
        'name', 
        'normal_balance', 
        'balance',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Otomatisasi generate format code sebelum simpan (Create / Update)
        static::saving(function ($account) {
            $account->code = $account->category . '-' . trim($account->account_number);
        });
    }

    /**
     * Label Helper untuk membaca nama Kategori teks di Frontend
     */
    public const CATEGORIES = [
        '1' => 'Kas & Bank',
        '2' => 'Pendapatan',
        '3' => 'Kewajiban',
        '4' => 'Ekuitas',
        '5' => 'Biaya',
    ];

    public static function getCategoryLabels(): array
    {
        return self::CATEGORIES;
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? '-';
    }

    public function updateBalance(string $mutationType, float $amount): void
    {
        $mutationType = strtolower($mutationType);
        $normalBalance = strtolower($this->normal_balance);

        // Tentukan apakah mutasi ini sifatnya menambah atau mengurangi saldo normal
        if ($normalBalance === $mutationType) {
            // Jika Saldo Normal DEBIT bertemu DEBIT, atau KREDIT bertemu KREDIT -> Bertambah
            $this->increment('balance', $amount);
        } else {
            // Jika berbeda (misal Saldo Normal DEBIT bertemu KREDIT) -> Berkurang
            $this->decrement('balance', $amount);
        }
    }
}
