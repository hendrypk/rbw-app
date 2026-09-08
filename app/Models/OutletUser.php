<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutletUser extends Model
{
    protected $table = 'outlet_users';

    protected $fillable = [
        'outlet_id',
        'user_id',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}