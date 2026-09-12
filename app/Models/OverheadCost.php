<?php

namespace App\Models;

use App\Concerns\BelongsToOutlet;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OverheadCost extends Model
{
    use SoftDeletes, HasUuids, BelongsToOutlet;

    protected $fillable = [
        'name',
        'amount',
        'type',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'float',
        'is_active' => 'boolean',
    ];
}
