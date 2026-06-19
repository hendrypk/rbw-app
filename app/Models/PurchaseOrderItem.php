<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'purchase_order_id', 'raw_material_id', 'qty', 'unit_price', 'subtotal',
    ];

    protected $casts = [
        'qty'        => 'decimal:4',
        'unit_price' => 'decimal:4',
        'subtotal'   => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function ($item) {
            $item->subtotal = $item->qty * $item->unit_price;
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }
}