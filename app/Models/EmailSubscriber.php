<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmailSubscriber extends Model
{
    protected $fillable = [
        'email', 'name', 'is_active', 'is_new', 'token', 'confirmed_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'is_new'       => 'boolean',
        'confirmed_at' => 'datetime',
    ];

    // Генерация токена для отписки
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->token = Str::random(32);
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNotNull('confirmed_at');
    }
}
