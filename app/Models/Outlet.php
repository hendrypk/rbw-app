<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Outlet extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'is_active',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'outlet_users');
    }
}
