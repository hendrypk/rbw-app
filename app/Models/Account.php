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
        'is_active',
        'opening_balance'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function ($account) {
            $account->code = $account->category . '-' . trim($account->account_number);
        });
    }

    public const CATEGORIES = [
        '1' => 'Kas & Bank',
        '2' => 'Piutang',
        '3' => 'Persediaan',
        '4' => 'Kewajiban',
        '5' => 'Ekuitas',
        '6' => 'Pendapatan',
        '7' => 'Harga Pokok Penjualan',
        '8' => 'Biaya',
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

        if ($normalBalance === $mutationType) {
            $this->increment('balance', $amount);
        } else {
            $this->decrement('balance', $amount);
        }
    }
}
