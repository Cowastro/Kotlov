<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value',
        'min_order_amount', 'uses_limit', 'uses_count',
        'is_active', 'expires_at',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'expires_at'       => 'datetime',
        'value'            => 'decimal:2',
        'min_order_amount' => 'decimal:2',
    ];

    // Проверить валидность купона
    public function isValid(float $orderAmount): bool
    {
        if (!$this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->uses_limit && $this->uses_count >= $this->uses_limit) return false;
        if ($orderAmount < $this->min_order_amount) return false;
        return true;
    }

    // Рассчитать скидку
    public function calculateDiscount(float $amount): float
    {
        if ($this->type === 'percent') {
            return round($amount * $this->value / 100, 2);
        }
        return min($this->value, $amount);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
