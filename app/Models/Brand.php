<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Brand extends Model
{
    public static function isKotlovAlias(string $value): bool
    {
        return in_array(preg_replace('/[\s_-]+/u', '', mb_strtolower(trim($value))), ['kotlov', 'kotlovpro', 'koktlov'], true);
    }

    protected static function booted(): void
    {
        static::saving(function (Brand $brand): void {
            if (! self::isKotlovAlias((string) $brand->name) && ! self::isKotlovAlias((string) $brand->slug)) {
                return;
            }
            $canonicalId = self::query()->where('slug', 'kotlov')->value('id');
            if ($canonicalId && (int) $brand->id !== (int) $canonicalId) {
                throw ValidationException::withMessages(['name' => 'Это бренд KOTLOV. Выберите существующий KOTLOV; PRO — серия, а не отдельный бренд.']);
            }
        });
    }

    protected $fillable = [
        'name', 'slug', 'h1', 'logo', 'country',
        'producer', 'content',
        'meta_title', 'meta_keywords', 'meta_description',
        'is_active', 'is_featured', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)->withTimestamps();
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
            return '/'.ltrim($path, '/');
        }

        if (str_starts_with($path, 'brands/')) {
            return asset('storage/'.$path);
        }

        $legacyBrandPath = 'images/catalog_brand/'
            .sprintf('%03d', floor(((int) $this->id) / 1000))
            .'/'
            .str_pad((string) $this->id, 6, '0', STR_PAD_LEFT)
            .'/'
            .$path;

        $legacyBrandFile = public_path($legacyBrandPath);
        if ($this->id && file_exists($legacyBrandFile)) {
            return '/'.$legacyBrandPath;
        }

        return '/proxy-image/'.ltrim($path, '/');
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
