<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokuTransaction extends Model
{
    protected $fillable = [
        'order_number',
        'original_reference_no',
        'amount',
        'qr_content',
        'status',
        'expired_at',
        'raw_response'
    ];

    protected $casts = [
        'raw_response' => 'array',
    ];
    
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_number', 'order_number');
    }
}