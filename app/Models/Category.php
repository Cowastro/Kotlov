<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    protected $fillable = [
        'parent_id', 'name', 'slug', 'h1', 'type',
        'sort_order', 'is_active', 'content',
        'meta_title', 'meta_keywords', 'meta_description',
        'image', 'icon',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Дочерние категории
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // Родительская категория
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Товары категории
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // Только корневые категории
    public function scopeRoot($query)
    {
        return $query->where('parent_id', 0);
    }

    // Только активные
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
