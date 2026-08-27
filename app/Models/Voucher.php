<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Voucher extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'type', 'value', 'min_spend', 'max_discount', 
        'usage_limit', 'used_count', 'started_at', 'expired_at', 'is_active'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_spend' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'started_at' => 'datetime',
        'expired_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'voucher_menus', 'voucher_id', 'menu_id')
                    ->using(VoucherMenu::class)
                    ->withTimestamps();
    }
}