<?php

namespace App\Models;

use App\Services\MergedProductIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerSelection extends Model
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

    protected $guarded = ['id'];

    protected $casts = ['items' => 'array', 'meta' => 'array', 'revision' => 'integer'];

    public function stockWatch(): HasOne
    {
        return $this->hasOne(SelectionStockWatch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function total(): float
    {
        return round(collect($this->items)->sum(fn ($item) => (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 0)), 2);
    }
}
