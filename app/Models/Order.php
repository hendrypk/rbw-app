<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'order_number', 
        'customer_name', 
        'customer_phone',     // Tambahan untuk self-order
        'shipping_address',   // Tambahan untuk self-order
        'user_id',            // Relasi ke tabel Customer/User
        'total_hpp', 
        'total_overhead', 
        'subtotal', 
        'tax', 
        'discount', 
        'final_total', 
        'payment_method', 
        'status',
        'notes'               // Pastikan notes juga bisa disimpan
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}