<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallerProfile extends Model
{
    protected $fillable = [
        'user_id', 'photo', 'bio',
        'city', 'region', 'work_regions', 'specializations',
        'experience_years', 'certificate_photo', 'price_from',
        'is_verified', 'rating', 'reviews_count', 'orders_count',
    ];

    protected $casts = [
        'work_regions'    => 'array',
        'specializations' => 'array',
        'is_verified'     => 'boolean',
        'price_from'      => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }
}
