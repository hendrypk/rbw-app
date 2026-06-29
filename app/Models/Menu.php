<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;

class Menu extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name', 'category_id', 'description', 'image_path', 'hpp', 'is_active', 'overhead_cost'
    ];

    protected $casts = [
        'overhead_cost' => 'float',
        'hpp'       => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function recipes(): HasMany
    {
        return $this->hasMany(MenuRecipe::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(MenuPrice::class);
    }

    public function recalculateHpp(): void
    {
        $this->hpp = $this->recipes()->sum('subtotal_cost');
        $this->save();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}