<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogPost extends Model
{
    protected $fillable = [
        'category_id', 'author_id',
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
}
