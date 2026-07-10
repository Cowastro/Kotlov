<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $fillable = [
        'name', 'slug', 'h1', 'logo', 'country',
        'producer', 'content',
        'meta_title', 'meta_keywords', 'meta_description',
        'is_active', 'is_featured', 'sort_order',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getImageUrlAttribute(): string
    {
        return $this->imageUrl();
    }

    public function imageUrl(): string
    {
        $placeholder = asset('img/products/product-placeholder.jpg');
        $path = trim((string) $this->logo);

        if ($path === '') {
            return $placeholder;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'img/') || str_starts_with($path, '/img/')) {
            return '/' . ltrim($path, '/');
        }

        if (str_starts_with($path, 'brands/')) {
            return asset('storage/' . $path);
        }

        return '/proxy-image/' . ltrim($path, '/');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}
