<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationSource extends Model
{
    public const PRICE_TAX_EXCLUSIVE = 'exclusive';

    public const PRICE_TAX_INCLUSIVE = 'inclusive';

    protected $fillable = [
        'code', 'name', 'driver', 'username', 'password_hash', 'is_active', 'create_products',
        'update_prices', 'update_stock', 'settings',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'is_active' => 'boolean',
        'create_products' => 'boolean',
        'update_prices' => 'boolean',
        'update_stock' => 'boolean',
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (IntegrationSource $source): void {
            $source->settings = array_merge([
                'price_tax_mode' => self::PRICE_TAX_EXCLUSIVE,
                'vat_rate' => 20,
                'warehouse_label' => 'Основной',
                'b2b_enabled' => false,
                'monitor_orders_from' => now()->toIso8601String(),
            ], $source->settings ?? []);
        });
    }

    public function isB2bEnabled(): bool
    {
        return (bool) data_get($this->settings, 'b2b_enabled', false);
    }

    public function partnerName(): string
    {
        return (string) data_get($this->settings, 'partner_name', $this->name);
    }

    public function priceTaxMode(): string
    {
        return data_get($this->settings, 'price_tax_mode') === self::PRICE_TAX_INCLUSIVE
            ? self::PRICE_TAX_INCLUSIVE
            : self::PRICE_TAX_EXCLUSIVE;
    }

    public function vatRate(): float
    {
        return max(0, (float) data_get($this->settings, 'vat_rate', 20));
    }

    public function priceIncludingTax(float $price): float
    {
        if ($this->priceTaxMode() === self::PRICE_TAX_INCLUSIVE) {
            return round($price, 2);
        }

        return round($price * (1 + $this->vatRate() / 100), 2);
    }

    public function sourcePriceTaxLabel(): string
    {
        return $this->priceTaxMode() === self::PRICE_TAX_INCLUSIVE
            ? 'с НДС'
            : 'без НДС';
    }

    public function pricingRuleLabel(): string
    {
        return $this->priceTaxMode() === self::PRICE_TAX_INCLUSIVE
            ? 'Передаётся с НДС'
            : 'Без НДС → +'.number_format($this->vatRate(), 0).'%';
    }

    public function products(): HasMany
    {
        return $this->hasMany(IntegrationProduct::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(IntegrationCategory::class);
    }

    public function exchangeRuns(): HasMany
    {
        return $this->hasMany(IntegrationExchangeRun::class);
    }
}
