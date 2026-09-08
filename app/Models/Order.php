<?php

namespace App\Models;

use App\Concerns\BelongsToCashierShift;
use App\Concerns\BelongsToOutlet;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasUuids, SoftDeletes, BelongsToOutlet, BelongsToCashierShift;

    protected $fillable = [
        'order_number', 
        'customer_id', 
        'outlet_id',
        'cashier_shift_id',
        'voucher_id',
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
        'notes',
        'transaction_fee',
        'amount_paid'
    ];

    protected static function booted()
    {
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $mmyy = \Carbon\Carbon::now()->format('my');
                $random4Digit = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

                // Keduanya sekarang menggunakan format yang sama: # + MMYY + 4 digit acak
                $order->order_number = $mmyy . $random4Digit;
            }
        });
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

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

    public function points() : HasOne
    {
        return $this->hasOne(CustomerPoint::class);
    }

public static function getDashboardSummary(?string $outletId = null)
    {
        // Tambahkan withoutGlobalScopes() di sini untuk mematikan paksaan filter dari trait BelongsToOutlet
        $query = self::withoutGlobalScopes()->where('status', 'paid');

        $cleanOutletId = strtolower(trim((string) $outletId));

        // Jika user memilih outlet spesifik, baru kita filter secara manual
        if ($cleanOutletId !== '' && $cleanOutletId !== 'all' && $cleanOutletId !== 'null' && $cleanOutletId !== 'undefined') {
            $query->where('outlet_id', $outletId);
        }

        return $query->with('items.menu')->get();
    }
}