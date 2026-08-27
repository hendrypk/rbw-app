<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPoint extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id', 
        'order_id', 
        'points', 
        'type', 
        'description'
    ];

    protected $casts = [
        'points' => 'integer',
    ];

    /**
     * Relasi ke model Customer.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relasi opsional ke model Order (jika poin didapat/dipakai dari transaksi).
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}