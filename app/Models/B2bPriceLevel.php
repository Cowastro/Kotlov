<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2bPriceLevel extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'rank', 'sale_price_discount_percent',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'rank' => 'integer',
        'sale_price_discount_percent' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function clients(): HasMany
    {
        return $this->hasMany(B2bClient::class);
    }

    public function applyToSalePrice(float $basePrice): float
    {
        $discount = $this->is_active
            ? min(99.99, max(0, (float) $this->sale_price_discount_percent))
            : 0;

        return round(max(0, $basePrice * (1 - $discount / 100)), 2);
    }
}
