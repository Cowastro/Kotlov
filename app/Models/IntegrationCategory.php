<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationCategory extends Model
{
    protected $fillable = [
        'integration_source_id', 'parent_id', 'category_id', 'external_id',
        'parent_external_id', 'name', 'path', 'payload', 'last_seen_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'last_seen_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(IntegrationSource::class, 'integration_source_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function siteCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(IntegrationProduct::class);
    }
}
