<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id', 
        'menu_id', 
        'quantity', 
        'price', // Harga jual saat transaksi terjadi
        'hpp',   // HPP saat transaksi terjadi (untuk laporan laba rugi)
        'overhead_cost',
        'subtotal'
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}