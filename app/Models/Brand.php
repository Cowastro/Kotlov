<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Brand extends Model
{
    public static function displayName(string $name): string
    {
        return trim(preg_replace('/[\s\x{00a0}\x{200b}\x{feff}]+/u', ' ', $name));
    }

    public static function nameKey(string $name): string
    {
        return mb_strtolower(self::displayName($name));
    }

    public static function canonicalId(int $id): int
    {
        if ($id <= 0 || self::whereKey($id)->exists() || ! Schema::hasTable('brand_aliases')) {
            return $id;
        }

        return (int) (DB::table('brand_aliases')->where('original_id', $id)->value('canonical_id') ?: $id);
    }

    public static function forImport(string $name): self
    {
        $name = self::displayName($name);

        return Cache::lock('brand-name:'.sha1(self::nameKey($name)), 15)->block(10, function () use ($name): self {
            $existing = self::query()->get()->first(fn ($b) => self::nameKey($b->name) === self::nameKey($name));
            if ($existing) {
                return $existing;
            }
            $slug = Str::slug($name) ?: 'brand-'.substr(sha1($name), 0, 10);
            $base = $slug;
            for ($i = 2; self::where('slug', $slug)->exists(); $i++) {
                $slug = $base.'-'.$i;
            }

            return self::firstOrCreate(['name' => $name], ['slug' => $slug, 'is_active' => true]);
        });
    }

    public static function isKotlovAlias(string $value): bool
    {
        return in_array(preg_replace('/[\s_-]+/u', '', mb_strtolower(trim($value))), ['kotlov', 'kotlovpro', 'koktlov'], true);
    }

    protected static function booted(): void
    {
        static::saving(function (Brand $brand): void {
            $brand->name = self::displayName((string) $brand->name);
            if (! $brand->exists || $brand->isDirty('name')) {
                $duplicate = self::query()->where('id', '!=', $brand->id ?? 0)->get(['id', 'name'])
                    ->first(fn ($other) => self::nameKey($other->name) === self::nameKey($brand->name));
                if ($duplicate) {
                    throw ValidationException::withMessages(['name' => 'Такой бренд уже существует. Выберите '.$duplicate->name.' (ID '.$duplicate->id.').']);
                }
            }
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
