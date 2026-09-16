<?php

namespace App\Models;

use App\Concerns\BelongsToOutlet;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OverheadCost extends Model
{
    use SoftDeletes, HasUuids, BelongsToOutlet;

    public const TYPE_PER_PORSI = 'per_porsi';
    public const TYPE_DAILY     = 'harian';
    public const TYPE_WEEKLY    = 'mingguan';
    public const TYPE_MONTHLY   = 'bulanan';
    public const TYPE_YEARLY    = 'tahunan';

    protected $fillable = [
        'name',
        'amount',
        'type',
        'is_active',
        'started_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'is_active' => 'boolean',
        'started_at' => 'date',
    ];

    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_PER_PORSI,
            self::TYPE_DAILY,
            self::TYPE_WEEKLY,
            self::TYPE_MONTHLY,
            self::TYPE_YEARLY,
        ];
    }

    public static function getTimeBasedTypes(): array
    {
        return [
            self::TYPE_DAILY,
            self::TYPE_WEEKLY,
            self::TYPE_MONTHLY,
            self::TYPE_YEARLY,
        ];
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'menu_overhead_cost', 'overhead_cost_id', 'menu_id')
                    ->withTimestamps();
    }
}
