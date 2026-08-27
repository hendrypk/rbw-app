<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class VoucherMenu extends Pivot
{
    protected $table = 'voucher_menus';

    protected $fillable = [
        'voucher_id',
        'menu_id',
    ];

    public $timestamps = true;
}