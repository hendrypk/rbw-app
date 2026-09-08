<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Outlet;

/**
 * @method static void addGlobalScope(string $identifier, \Closure $scope)
 * @method static void creating(\Closure $callback)
 * @method BelongsTo belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null)
 */
trait BelongsToOutlet
{
    /**
     * Boot the BelongsToOutlet trait for a model.
     */
    public static function bootBelongsToOutlet(): void
    {
        // Global scope untuk filter data otomatis berdasarkan outlet aktif
        static::addGlobalScope('outlet', function (Builder $builder) {
            $outletId = request()->header('X-Outlet-ID') ?? session('active_outlet_id');
            if ($outletId) {
                // ⬅️ Gunakan getModel()->getTable() agar aman dari error undefined method
                $builder->where($builder->getModel()->getTable() . '.outlet_id', $outletId);
            }
        });

        // Otomatis isi outlet_id saat record baru dibuat (create)
        static::creating(function ($model) {
            if (empty($model->outlet_id)) {
                $model->outlet_id = request()->header('X-Outlet-ID') 
                    ?? session('active_outlet_id') 
                    ?? app('currentOutletId') 
                    ?? null;
            }
        });
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}