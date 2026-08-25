<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'order_number', 
        'customer_id', 
        'customer_name',
        'customer_phone',
        'shipping_address', 
        'user_id',
        'total_hpp', 
        'total_overhead', 
        'subtotal', 
        'tax', 
        'discount', 
        'final_total', 
        'payment_method', 
        'status',
        'notes'
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function dokuTransaction(): HasOne
    {
        return $this->hasOne(DokuTransaction::class, 'order_number', 'order_number');
    }
}