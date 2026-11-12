<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Local SEO of one category on one city site (lida.kotlov.by/gazovye): its own heading, title, description and
 * text, so each city reads differently and can be promoted on its own. Empty fields fall back to the shared template.
 */
class CityCategorySeo extends Model
{
    protected $table = 'city_category_seo';

    protected $fillable = ['city_id', 'category_id', 'h1', 'meta_title', 'meta_description', 'content', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public static function for(?City $city, Category $category): ?self
    {
        if (! $city?->exists) {
            return null;
        }

        return static::query()->where('city_id', $city->id)->where('category_id', $category->id)->where('is_active', true)->first();
    }
}
