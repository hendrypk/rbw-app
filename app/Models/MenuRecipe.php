<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuRecipe extends Model
{
    use HasUuids;

    protected $fillable = [
        'menu_id', 'raw_material_id', 'qty_usage', 'unit_cost_snapshot', 'subtotal_cost',
    ];

    protected $casts = [
        'qty_usage'           => 'decimal:4',
        'unit_cost_snapshot'  => 'decimal:4',
        'subtotal_cost'       => 'decimal:4',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function ($recipe) {
            // snapshot avg_cost dari bahan saat ini
            if (empty($recipe->unit_cost_snapshot) && $recipe->raw_material_id) {
                $recipe->unit_cost_snapshot = RawMaterial::find($recipe->raw_material_id)?->avg_cost ?? 0;
            }
            $recipe->subtotal_cost = $recipe->qty_usage * $recipe->unit_cost_snapshot;
        });
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }
}