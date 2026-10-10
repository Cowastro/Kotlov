<?php

namespace App\Models;

use App\Services\Integrations\IntegrationSourcePricingAuditRecorder;
use App\Services\Pricing\CurrencyPriceConverter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IntegrationSource extends Model
{
    public const PRICE_TAX_EXCLUSIVE = 'exclusive';

    public const PRICE_TAX_INCLUSIVE = 'inclusive';

    protected $fillable = [
        'supplier_id', 'code', 'name', 'driver', 'price_currency', 'price_currency_rate',
        'username', 'password_hash', 'is_active', 'create_products',
        'update_prices', 'update_stock', 'settings', 'last_authenticated_at',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'is_active' => 'boolean',
        'create_products' => 'boolean',
        'update_prices' => 'boolean',
        'update_stock' => 'boolean',
        'price_currency_rate' => 'float',
        'settings' => 'array',
        'last_authenticated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (IntegrationSource $source): void {
            $source->settings = array_merge([
                'price_tax_mode' => self::PRICE_TAX_EXCLUSIVE,
                'vat_rate' => 20,
                'warehouse_label' => 'Основной',
                'warehouse_external_id' => null,
                'b2b_enabled' => false,
                'b2b_category_ids' => [],
                'order_interval_minutes' => 5,
                'order_dispatch_delay_minutes' => 10,
                'order_response_timeout_minutes' => 15,
                'catalog_interval_minutes' => 10,
                'stale_after_minutes' => 15,
                'zero_missing_stock_on_complete' => true,
                'all_stock_positive_warning_min_products' => 20,
                'monitor_orders_from' => now()->toIso8601String(),
                'order_status_rules' => [],
                'payment_status_rules' => [],
            ], $source->settings ?? []);
        });

        static::updated(function (IntegrationSource $source): void {
            app(IntegrationSourcePricingAuditRecorder::class)->recordUpdatedSource($source);
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

    /** @return list<int> */
    public function b2bCategoryIds(): array
    {
        $ids = data_get($this->settings, 'b2b_category_ids', []);

        if (! is_array($ids)) {
            return [];
        }

        return collect($ids)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
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

    public function priceCurrency(): string
    {
        return CurrencyPriceConverter::normalizeCurrency($this->price_currency);
    }

    public function priceCurrencyRate(): ?float
    {
        if ($this->priceCurrency() === CurrencyPriceConverter::BASE_CURRENCY) {
            return 1.0;
        }

        $rate = (float) $this->price_currency_rate;

        return $rate > 0 ? $rate : null;
    }

    public function normalizePriceToByn(float $price): ?float
    {
        $rate = $this->priceCurrencyRate();
        if ($price <= 0 || $rate === null) {
            return null;
        }

        return $this->priceIncludingTax($price * $rate);
    }

    /** @return array{price_currency:string,price_currency_rate:?float,price_tax_mode:string,price_vat_rate:float,price_byn:?float} */
    public function priceSnapshot(float $price): array
    {
        return [
            'price_currency' => $this->priceCurrency(),
            'price_currency_rate' => $this->priceCurrencyRate(),
            'price_tax_mode' => $this->priceTaxMode(),
            'price_vat_rate' => $this->vatRate(),
            'price_byn' => $this->normalizePriceToByn($price),
        ];
    }

    public function sourcePriceTaxLabel(): string
    {
        return $this->priceTaxMode() === self::PRICE_TAX_INCLUSIVE
            ? 'с НДС'
            : 'без НДС';
    }

    public function pricingRuleLabel(): string
    {
        $tax = $this->priceTaxMode() === self::PRICE_TAX_INCLUSIVE
            ? 'Передаётся с НДС'
            : 'Без НДС → +'.number_format($this->vatRate(), 0).'%';

        $currency = $this->priceCurrency();
        $rate = $this->priceCurrencyRate();
        $currencyRule = $currency === CurrencyPriceConverter::BASE_CURRENCY
            ? 'BYN'
            : ($rate === null
                ? $currency.' · курс не задан'
                : $currency.' × '.rtrim(rtrim(number_format($rate, 6, '.', ''), '0'), '.'));

        return $currencyRule.' · '.$tax;
    }

    public function orderIntervalMinutes(): int
    {
        return max(2, (int) data_get($this->settings, 'order_interval_minutes', 5));
    }

    public function orderDispatchDelayMinutes(): int
    {
        return max(5, (int) data_get(
            $this->settings,
            'order_dispatch_delay_minutes',
            max(10, $this->orderIntervalMinutes() * 2),
        ));
    }

    public function orderResponseTimeoutMinutes(): int
    {
        return max(5, (int) data_get(
            $this->settings,
            'order_response_timeout_minutes',
            max(15, $this->orderIntervalMinutes() * 3),
        ));
    }

    public function catalogIntervalMinutes(): int
    {
        return max(5, (int) data_get($this->settings, 'catalog_interval_minutes', 10));
    }

    public function staleAfterMinutes(): int
    {
        return max(5, (int) data_get($this->settings, 'stale_after_minutes', 15));
    }

    public function exportsOrders(): bool
    {
        return $this->code === 'onec' || (bool) data_get($this->settings, 'allow_order_export', false);
    }

    public function zeroMissingStockOnComplete(): bool
    {
        return (bool) data_get($this->settings, 'zero_missing_stock_on_complete', true);
    }

    public function allStockPositiveWarningMinimum(): int
    {
        return max(0, (int) data_get($this->settings, 'all_stock_positive_warning_min_products', 20));
    }

    public function warehouseExternalId(): ?string
    {
        $externalId = trim((string) data_get($this->settings, 'warehouse_external_id'));

        return $externalId !== '' ? $externalId : null;
    }

    public function warehouseLabel(): string
    {
        $label = trim((string) data_get($this->settings, 'warehouse_label', 'Основной'));

        return $label !== '' ? $label : 'Основной';
    }

    public function matchingSupplierCode(): string
    {
        $supplierCode = trim((string) data_get($this->settings, 'matching_supplier_code'));

        return $supplierCode !== '' ? $supplierCode : 'integration:'.$this->code;
    }

    public function scheduleLabel(): string
    {
        return 'Заказы '.$this->orderIntervalMinutes().' мин · цены/остатки '.$this->catalogIntervalMinutes().' мин';
    }

    public function statusRulesCount(): int
    {
        return $this->countStatusRules(data_get($this->settings, 'order_status_rules', []))
            + $this->countStatusRules(data_get($this->settings, 'payment_status_rules', []));
    }

    private function countStatusRules(mixed $rules): int
    {
        if (! is_array($rules)) {
            return 0;
        }

        return count(array_filter($rules, array_is_list($rules) ? 'is_array' : 'is_string'));
    }

    public function products(): HasMany
    {
        return $this->hasMany(IntegrationProduct::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(IntegrationCategory::class);
    }

    public function exchangeRuns(): HasMany
    {
        return $this->hasMany(IntegrationExchangeRun::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(IntegrationWarehouse::class);
    }

    public function orderDeliveries(): HasMany
    {
        return $this->hasMany(OrderIntegrationDelivery::class);
    }

    public function channelTransitions(): HasMany
    {
        return $this->hasMany(SupplierChannelTransition::class);
    }

    public function pricingChanges(): HasMany
    {
        return $this->hasMany(IntegrationSourcePricingChange::class);
    }

    public function latestChannelTransition(): HasOne
    {
        return $this->hasOne(SupplierChannelTransition::class)->latestOfMany();
    }

    public function latestExchangeRun(): HasOne
    {
        return $this->hasOne(IntegrationExchangeRun::class)->latestOfMany('started_at');
    }

    public function latestSuccessfulExchangeRun(): HasOne
    {
        return $this->hasOne(IntegrationExchangeRun::class)->ofMany(
            ['finished_at' => 'max', 'id' => 'max'],
            fn ($query) => $query->where('status', 'success'),
        );
    }
}
