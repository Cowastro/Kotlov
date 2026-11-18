<?php

namespace App\Models;

use App\Services\MergedProductIdentity;
use Illuminate\Database\Eloquent\Model;

class CustomerCart extends Model
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

    protected $casts = ['items' => 'array', 'meta' => 'array'];
}
