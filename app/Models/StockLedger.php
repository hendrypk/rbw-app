<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockLedger extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'raw_material_id', 'reference_id', 'reference_type',
        'movement_type', 'qty', 'unit_cost',
        'avg_cost_before', 'avg_cost_after',
        'stock_before', 'stock_after', 'notes', 'created_at',
    ];

    protected $casts = [
        'qty'              => 'decimal:4',
        'unit_cost'        => 'decimal:4',
        'avg_cost_before'  => 'decimal:4',
        'avg_cost_after'   => 'decimal:4',
        'stock_before'     => 'decimal:4',
        'stock_after'      => 'decimal:4',
        'created_at'       => 'datetime',
    ];

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}