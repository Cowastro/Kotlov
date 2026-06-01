<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'brand_id', 'supplier_id',
        'name', 'slug', 'h1', 'sku',
        'price', 'price_old', 'currency',
        'content', 'short_description',
        'images', 'specs', 'video_url',
        'weight', 'unit', 'warranty',
        'is_active', 'in_stock', 'stock_qty',
        'is_featured', 'is_new', 'is_sale', 'sort_order',
        'meta_title', 'meta_keywords', 'meta_description',
        'rating', 'reviews_count', 'views_count',
    ];

    protected $casts = [
        'images'      => 'array',
        'specs'       => 'array',
        'is_active'   => 'boolean',
        'in_stock'    => 'boolean',
        'is_featured' => 'boolean',
        'is_new'      => 'boolean',
        'is_sale'     => 'boolean',
        'price'       => 'decimal:2',
        'price_old'   => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(ProductView::class);
    }

    // Первое фото
    public function getMainImageAttribute(): ?string
    {
        return $this->images[0] ?? null;
    }

    // Есть скидка
    public function getDiscountPercentAttribute(): ?int
    {
        if ($this->price_old && $this->price_old > $this->price) {
            return round((1 - $this->price / $this->price_old) * 100);
        }
        return null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('in_stock', true);
    }
}
