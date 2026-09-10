<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Outlet;
use Illuminate\Support\Facades\Request;

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
        static::addGlobalScope('outlet', function (Builder $builder) {
            $outletId = request()->header('X-Outlet-ID') ?? session('active_outlet_id');
            if ($outletId) {
                $builder->where($builder->getModel()->getTable() . '.outlet_id', $outletId);
            }
        });

        static::creating(function ($model) {
if (empty($model->outlet_id)) {
                $outletId = Request::header('X-Outlet-ID');
                
                if ($outletId && $outletId !== 'all') {
                    $model->outlet_id = $outletId;
                }
            }
        });
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}