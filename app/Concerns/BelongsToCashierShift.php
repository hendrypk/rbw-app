<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CashierShift;

/**
 * @method static void addGlobalScope(string $identifier, \Closure $scope)
 * @method static void creating(\Closure $callback)
 * @method BelongsTo belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null)
 */

trait BelongsToCashierShift
{
    /**
     * Boot the BelongsToCashierShift trait for a model.
     */
    public static function bootBelongsToCashierShift(): void
    {
        // Global scope opsional jika Anda ingin query order secara default terfilter shift aktif (biasanya untuk POS)
        /*
        static::addGlobalScope('cashier_shift', function (Builder $builder) {
            $shiftId = request()->header('X-Shift-ID') ?? session('active_cashier_shift_id');
            if ($shiftId) {
                $builder->where($builder->getModel()->getTable() . '.cashier_shift_id', $shiftId);
            }
        });
        */

        // Otomatis isi cashier_shift_id saat record order baru dibuat
        static::creating(function ($model) {
            if (empty($model->cashier_shift_id)) {
                $model->cashier_shift_id = request()->header('X-Shift-ID') 
                    ?? session('active_cashier_shift_id') 
                    ?? CashierShift::where('outlet_id', $model->outlet_id)
                        ->where('status', 'open')
                        ->value('id') 
                    ?? null;
            }
        });
    }

    public function cashierShift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }
}