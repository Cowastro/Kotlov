<?php

namespace App\Models;

use App\Services\MergedProductIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bSavedCart extends Model
{
    protected static function booted(): void
    {
        static::saving(function ($model): void {
            if (! $model->isDirty('items') && ! $model->isDirty('meta')) {
                return;
            }
            $identity = app(MergedProductIdentity::class);
            $map = $identity->map();
            if (! $map) {
                return;
            }
            $model->items = $identity->items($model->items ?? [], $map);
            if (array_key_exists('meta', $model->getAttributes())) {
                $model->meta = $identity->meta($model->meta ?? [], $map);
            }
        });
    }

    protected $fillable = [
        'user_id', 'name', 'items', 'items_count', 'total',
    ];

    protected $casts = [
        'items' => 'array',
        'items_count' => 'integer',
        'total' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
