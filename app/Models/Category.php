<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasUuids;

    // Tambahkan 'id' ke dalam array $fillable
    protected $fillable = [
        'id',
        'name',
        'is_visible',
    ];

    // Karena id menggunakan UUID (string), nonaktifkan auto-incrementing integer bawaan Eloquent
    public $incrementing = false;

    // Set tipe data primary key menjadi string
    protected $keyType = 'string';

    /**
     * Relasi ke tabel Menu
     */
    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }
}
