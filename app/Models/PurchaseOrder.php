<?php

namespace App\Models;

use App\Concerns\BelongsToOutlet;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Override;

class PurchaseOrder extends Model
{
    use HasUuids, SoftDeletes, BelongsToOutlet;

    protected $fillable = [
        'supplier_id', 'outlet_id', 'po_number', 'order_date',
        'received_date', 'status', 'payment_status',
        'total_amount', 'total_payment', 'notes',
        'payment_account_id'
    ];

    protected $casts = [
        // 'order_date'    => 'date',
        'order_date' => 'date:Y-m-d',
        'received_date' => 'date',
        'total_amount'  => 'decimal:2',
    ];

protected static function boot(): void
{
    parent::boot();

    static::creating(function ($po) {
        if (empty($po->po_number)) {
            // Ambil kode outlet (fallback ke 'PST' jika pusat/kosong)
            $outletCode = 'PST';
            if ($po->outlet_id) {
                $outlet = \App\Models\Outlet::find($po->outlet_id);
                if ($outlet && !empty($outlet->code)) {
                    $outletCode = strtoupper($outlet->code);
                }
            }

            $po->po_number = 'PO/' . $outletCode . '/' . now()->format('ymd') . strtoupper(\Illuminate\Support\Str::random(3));
        }
    });
}
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function recalculateTotal(): void
    {
        $this->total_amount = $this->items()->sum('subtotal');
        $this->save();
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'reference_id')
                    ->where('reference_type', self::class);
    }
}