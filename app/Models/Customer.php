<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids;

    protected $fillable = [
        'name', 'phone', 'email', 'password', 'shipping_address'
    ];

    protected $hidden = [
        'password',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}