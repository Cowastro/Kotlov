<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class BlogPost extends Model
{
    private const DEFAULT_COVER_IMAGES = [
        'kak-vybrat-teplovoy-nasos' => 'img/blog/blog-heatpump.jpg',
        'pelletnyy-kotel-ili-gazovyy' => 'img/blog/blog-boiler.jpg',
        'dymohod-dlya-kamina' => 'img/blog/blog-chimney.jpg',
        'dymohody-teplov-i-sukhov-v-belarusi' => 'img/blog/teplov-sukhov-chimneys-cover-1800x1040.jpg',
        'teplovye-nasosy-ge-r290-vysokotemperaturnye' => 'img/blog/ge-r290-heat-pumps-cover.jpg',
    ];

    protected $fillable = [
        'category_id', 'author_id', 'city_id',
        'title', 'slug', 'excerpt', 'content',
        'cover_image', 'images', 'tags',
        'is_published', 'published_at', 'views_count',
        'meta_title', 'meta_description',
    ];

    protected $casts = [
        'images'       => 'array',
        'tags'         => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function scopeGeneral($query)
    {
        return $query->whereNull('city_id');
    }

    public function scopeRegional($query)
    {
        return $query->whereNotNull('city_id')->whereHas('city', fn ($city) => $city
            ->where('is_active', true)->where('storefront_enabled', true)->where('slug', '!=', 'minsk')
            ->whereHas('partnerBranches', fn ($branch) => $branch->where('is_active', true)
                ->whereHas('client', fn ($client) => $client->where('status', 'approved'))));
    }

    public function publicUrl(): string
    {
        return $this->city_id
            ? $this->city->storefrontUrl().'/news/'.$this->slug
            : 'https://'.config('app.base_domain', 'kotlov.by').'/blog/'.$this->slug;
    }

    protected static function booted(): void
    {
        $invalidate = function (self $post): void {
            Cache::forget('home:data:v9');
            Cache::forget('sitemap.xml.v6');
            foreach (array_unique(array_filter([$post->city_id, $post->getOriginal('city_id')])) as $cityId) {
                if ($city = City::find($cityId)) {
                    Cache::forget('sitemap.city.'.$city->slug.'.v4');
                }
            }
        };
        static::saved($invalidate);
        static::deleted($invalidate);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
                     ->whereNotNull('published_at')
                     ->where('published_at', '<=', now());
    }

    public function getCoverImageUrlAttribute(): string
    {
        $path = $this->cover_image ?: (self::DEFAULT_COVER_IMAGES[$this->slug] ?? 'img/blog/blog-boiler.jpg');

        return asset(str_starts_with($path, 'img/') ? $path : 'storage/' . $path);
    }
}
