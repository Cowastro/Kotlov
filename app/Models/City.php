<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = ['slug', 'name', 'name_in', 'name_title', 'region', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->where('is_active', true)->first();
    }
}
