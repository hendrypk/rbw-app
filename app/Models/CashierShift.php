<?php

namespace App\Models;

use App\Concerns\BelongsToOutlet;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierShift extends Model
{
    use HasUuids, BelongsToOutlet;

    protected $fillable = [
        'outlet_id',
        'user_id',
        'starting_cash',
        'actual_cash',
        'expected_cash',
        'difference',
        'status',
        'notes',
        'opened_at',
        'closed_at'
    ];

    protected $casts = [
        'starting_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'difference' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}