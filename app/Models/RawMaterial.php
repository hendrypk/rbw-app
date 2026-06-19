<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RawMaterial extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name', 
        'base_unit',
        'purchase_unit',
        'conversion_factor',
        'stock_qty', 
        'avg_cost', 
        'last_cost', 
        'min_stock', 
        'is_active',
    ];

    protected $casts = [
        'stock_qty'         => 'decimal:4',
        'avg_cost'          => 'decimal:4',
        'last_cost'         => 'decimal:4',
        'min_stock'         => 'decimal:4',
        'conversion_factor' => 'decimal:4',
        'is_active'         => 'boolean',
    ];
    protected $appends = ['is_low_stock'];

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function stockLedger(): HasMany
    {
        return $this->hasMany(StockLedger::class);
    }

    public function menuRecipes(): HasMany
    {
        return $this->hasMany(MenuRecipe::class);
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->stock_qty <= $this->min_stock;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}