<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'title', 'slug', 'content',
        'is_published', 'meta_title', 'meta_description',
    ];

    protected $casts = ['is_published' => 'boolean'];

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
