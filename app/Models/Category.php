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

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(Attribute::class)->orderBy('sort_order');
    }

    public function getImageUrlAttribute(): string
    {
        return $this->imageUrl();
    }

    public function imageUrl(): string
    {
        $path = trim((string) $this->image);

        if ($path !== '') {
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            if (str_starts_with($path, 'img/') || str_starts_with($path, '/img/')) {
                return asset(ltrim($path, '/'));
            }

            return asset('storage/' . ltrim($path, '/'));
        }

        return asset(static::fallbackImagePath($this->slug) ?? 'img/popular/catalog.jpg');
    }

    public static function fallbackImagePath(?string $slug): ?string
    {
        return [
            'kotly' => 'img/popular/boiler_img.jpg',
            'kotly-otopleniya' => 'img/popular/boiler_img.jpg',
            'teplovyie-nasosyi' => 'img/popular/heatpump.jpg',
            'teplovye-nasosy' => 'img/popular/heatpump.jpg',
            'kaminy' => 'img/popular/fireplace.jpg',
            'pechki' => 'img/popular/pech.jpg',
            'pechi' => 'img/popular/pech.jpg',
            'pechi-dlya-bani' => 'img/popular/pech.jpg',
            'dymohody' => 'img/popular/chimney.jpg',
            'bani-i-sauny' => 'img/popular/sauna.jpg',
            'vodonagrevateli' => 'img/banners/baner_boiler.jpg',
            'pelletnye-gorelki' => 'img/popular/pellet_burner.jpg',
            'otoplenie' => 'img/popular/heater.jpg',
            'vodosnabzhenie' => 'img/popular/nasosy.jpg',
            'nasosy' => 'img/popular/nasosy.jpg',
            'nasosyi' => 'img/popular/nasosy.jpg',
            'klimat' => 'img/popular/air.jpg',
            'radiatory' => 'img/popular/radiatory.jpg',
            'truby-i-fitingi' => 'img/popular/truby-i-fitingi.jpg',
            'teplyj-pol' => 'img/popular/teplyj-pol.jpg',
            'elektricheskie-konvektoryi' => 'img/popular/elektricheskie-konvektoryi.jpg',
            'komplektuyushhie-dlya-otopleniya' => 'img/popular/komplektuyushhie-dlya-otopleniya.jpg',
            'filtry' => 'img/popular/filtry.jpg',
        ][$slug] ?? null;
    }

    public function scopeRoot($query)
    {
        return $query->where('parent_id', 0);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
