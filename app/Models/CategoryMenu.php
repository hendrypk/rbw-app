<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CategoryMenu extends Pivot
{
    use HasUlids;

    protected $table = 'category_menu';

    protected $fillable = [
        'category_id',
        'menu_id',
        'sort',
    ];
}
