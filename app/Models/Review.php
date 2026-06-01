<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Review extends Model
{
    protected $fillable = [
        'user_id', 'author_name', 'author_email',
        'reviewable_type', 'reviewable_id',
        'rating', 'text', 'photos', 'is_approved',
    ];

    protected $casts = [
        'photos'      => 'array',
        'is_approved' => 'boolean',
    ];

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }
}
