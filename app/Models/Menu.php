<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Menu extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name', 'category', 'description', 'image_path', 'hpp', 'is_active',
    ];

    protected $casts = [
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}