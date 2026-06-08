<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Review extends Model
{
    protected $fillable = [
        'user_id', 'author_name', 'author_email', 'author_phone',
        'reviewable_type', 'reviewable_id',
        'rating', 'text', 'photos', 'is_approved',
        'reply', 'replied_at',
    ];

    protected $casts = [
        'photos'      => 'array',
        'is_approved' => 'boolean',
        'replied_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $review) {
            // Авто-дата ответа
            if ($review->isDirty('reply')) {
                $review->replied_at = $review->reply ? now() : null;
            }
        });

        static::saved(fn(self $review) => $review->recalculateTarget());
        static::deleted(fn(self $review) => $review->recalculateTarget());
    }

    public function recalculateTarget(): void
    {
        if ($this->reviewable_type !== 'App\\Models\\Product') {
            return;
        }

        $product = Product::find($this->reviewable_id);
        if (!$product) return;

        $stats = self::where('reviewable_type', 'App\\Models\\Product')
            ->where('reviewable_id', $product->id)
            ->where('is_approved', true)
            ->selectRaw('COUNT(*) as cnt, COALESCE(AVG(rating), 0) as avg')
            ->first();

        $product->updateQuietly([
            'reviews_count' => (int) $stats->cnt,
            'rating'        => round((float) $stats->avg, 1),
        ]);
    }

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
