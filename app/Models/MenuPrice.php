<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuPrice extends Model
{
    use HasUuids;

    const CHANNELS = ['offline', 'shopeefood', 'grabfood', 'gofood'];

    const PLATFORM_FEES = [
        'offline'    => 0,
        'shopeefood' => 30,
        'grabfood'   => 30,
        'gofood'     => 30,
    ];

    protected $fillable = [
        'menu_id', 'channel', 'margin_percent',
        'selling_price', 'platform_fee_percent', 'nett_price', 'is_active',
    ];

    protected $casts = [
        'margin_percent'       => 'decimal:2',
        'selling_price'        => 'decimal:2',
        'platform_fee_percent' => 'decimal:2',
        'nett_price'           => 'decimal:2',
        'is_active'            => 'boolean',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * Hitung selling_price dan nett_price dari Modal Dasar (HPP + Overhead) + Margin
     */
    public static function calculate(float $baseCost, float $marginPercent, string $channel): array
    {
        $platformFee = self::PLATFORM_FEES[$channel] ?? 0;

        // Margin dihitung dari harga jual (gross margin)
        $targetPriceBeforeOjol = $baseCost / (1 - ($marginPercent / 100));

        if ($platformFee > 0 && $platformFee < 100) {
            // Naikkan harga agar setelah dipotong fee marketplace,
            // margin tetap sesuai target.
            $sellingPrice = $targetPriceBeforeOjol / (1 - ($platformFee / 100));
        } else {
            $sellingPrice = $targetPriceBeforeOjol;
        }

        $nettPrice = $sellingPrice * (1 - ($platformFee / 100));

        return [
            'selling_price'        => round($sellingPrice),
            'platform_fee_percent' => $platformFee,
            'nett_price'           => round($nettPrice),
            'clean_profit'         => round($nettPrice - $baseCost),
        ];
    }
}