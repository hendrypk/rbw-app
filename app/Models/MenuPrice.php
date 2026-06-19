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
        'shopeefood' => 20,
        'grabfood'   => 20,
        'gofood'     => 20,
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
     * Hitung selling_price dan nett_price dari hpp + margin
     */
    public static function calculate(float $hpp, float $marginPercent, string $channel): array
    {
        $sellingPrice = $hpp * (1 + $marginPercent / 100);
        $platformFee  = self::PLATFORM_FEES[$channel] ?? 0;
        $nettPrice    = $sellingPrice * (1 - $platformFee / 100);

        return [
            'selling_price'        => round($sellingPrice, 2),
            'platform_fee_percent' => $platformFee,
            'nett_price'           => round($nettPrice, 2),
        ];
    }
}