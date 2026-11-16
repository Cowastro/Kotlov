<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSelection extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['items' => 'array', 'meta' => 'array', 'revision' => 'integer'];

    public function total(): float
    {
        return round(collect($this->items)->sum(fn ($item) => (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 0)), 2);
    }
}
