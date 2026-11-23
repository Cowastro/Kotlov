<?php

namespace App\Models;

use App\Services\Integrations\OneCBridge\OneCItemLinks;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IntegrationProduct extends Model
{
    protected $fillable = [
        'integration_source_id', 'integration_category_id', 'target_category_id', 'product_id', 'external_id', 'external_code', 'external_sku',
        'barcode', 'name', 'price', 'price_currency', 'price_currency_rate',
        'price_tax_mode', 'price_vat_rate', 'price_byn', 'stock_quantity', 'match_status',
        'b2b_enabled',
        'match_method', 'match_confidence', 'candidates', 'payload',
        'matched_at', 'last_seen_at', 'last_offer_seen_at', 'stock_confirmed_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_currency_rate' => 'float',
        'price_vat_rate' => 'float',
        'price_byn' => 'decimal:2',
        'stock_quantity' => 'decimal:3',
        'b2b_enabled' => 'boolean',
        'match_confidence' => 'float',
        'candidates' => 'array',
        'payload' => 'array',
        'matched_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_offer_seen_at' => 'datetime',
        'stock_confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(fn (self $row) => app(OneCItemLinks::class)->changed($row, false, true));
        static::updated(function (self $row): void {
            if ($row->wasChanged(['product_id', 'match_status', 'external_id', 'integration_source_id'])) {
                app(OneCItemLinks::class)->changed($row);
            }
        });
        static::deleted(fn (self $row) => app(OneCItemLinks::class)->changed($row, true));
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeWithUsablePrice(Builder $query): Builder
    {
        return $query->where(function (Builder $price): void {
            $price->where('price_byn', '>', 0)
                ->orWhere(function (Builder $legacy): void {
                    $legacy->where('price', '>', 0)
                        ->whereNull('price_currency')
                        ->whereNull('price_currency_rate')
                        ->whereNull('price_tax_mode')
                        ->whereNull('price_vat_rate');
                });
        });
    }

    public function scopeWithoutUsablePrice(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $normalized): void {
                $normalized->whereNull('price_byn')->orWhere('price_byn', '<=', 0);
            })
            ->where(function (Builder $price): void {
                $price->whereNull('price')
                    ->orWhere('price', '<=', 0)
                    ->orWhere(function (Builder $unresolved): void {
                        $unresolved->where(function (Builder $snapshot): void {
                            $snapshot->whereNotNull('price_currency')
                                ->orWhereNotNull('price_currency_rate')
                                ->orWhereNotNull('price_tax_mode')
                                ->orWhereNotNull('price_vat_rate');
                        });
                    });
            });
    }

    public function formattedStockQuantity(): string
    {
        // Reading a decimal cast asks Eloquent for a database grammar. Use the
        // stored value directly so formatting also works for an unsaved offer.
        $attributes = $this->getAttributes();
        $quantity = (float) ($attributes['stock_quantity'] ?? 0);

        $formatted = abs($quantity - round($quantity)) < 0.0005
            ? number_format($quantity, 0, '.', ' ')
            : rtrim(rtrim(number_format($quantity, 3, '.', ' '), '0'), '.');

        $product = $this->relationLoaded('product')
            ? $this->getRelation('product')
            : (filled($attributes['product_id'] ?? null) ? $this->product : null);
        $unit = Str::lower(trim((string) $product?->unit));
        $suffix = Str::contains($unit, ['пог', 'метр']) ? ' м' : ' шт.';

        return $formatted.$suffix;
    }

    /**
     * A null value intentionally preserves the source/category publication rule.
     * False is a per-offer safety stop; true records an explicit manager approval.
     */
    public function allowsB2bPublication(): bool
    {
        return $this->b2b_enabled !== false;
    }

    public function hasFreshPositiveStock(): bool
    {
        return (float) $this->stock_quantity > 0 && $this->hasFreshStock();
    }

    public function hasFreshStock(): bool
    {
        $source = $this->source;
        if ($source?->is_active !== true || $this->stock_quantity === null) {
            return false;
        }

        if ($this->stock_confirmed_at?->gte(now()->subMinutes($source->stockValidityMinutes()))) {
            return true;
        }

        // Refreshing the catalogue does not refresh a missing stock line.
        return false;
    }

    /** SQL equivalent of hasFreshPositiveStock(), including each source's validity window. */
    public function scopeFreshPositiveStock(Builder $query): Builder
    {
        $sources = IntegrationSource::query()->where('is_active', true)->get();

        return $query->where('stock_quantity', '>', 0)->where(function (Builder $stock) use ($sources): void {
            // An empty source list must never turn this into an unrestricted OR.
            $stock->whereRaw('1 = 0');
            foreach ($sources as $source) {
                $cutoff = now()->subMinutes($source->stockValidityMinutes());
                $stock->orWhere(function (Builder $channel) use ($source, $cutoff): void {
                    $channel->where('integration_source_id', $source->id)
                        ->where('stock_confirmed_at', '>=', $cutoff);
                });
            }
        });
    }

    public function normalizedPriceByn(): ?float
    {
        $normalized = (float) $this->price_byn;
        if ($normalized > 0) {
            return round($normalized, 2);
        }

        $raw = (float) $this->price;
        if ($raw <= 0) {
            return null;
        }

        if ($this->hasPriceSnapshot()) {
            return null;
        }

        return $this->source?->normalizePriceToByn($raw);
    }

    public function effectivePriceTaxMode(): string
    {
        return in_array($this->price_tax_mode, [
            IntegrationSource::PRICE_TAX_EXCLUSIVE,
            IntegrationSource::PRICE_TAX_INCLUSIVE,
        ], true)
            ? $this->price_tax_mode
            : ($this->source?->priceTaxMode() ?? IntegrationSource::PRICE_TAX_EXCLUSIVE);
    }

    public function effectiveVatRate(): float
    {
        return $this->price_vat_rate !== null
            ? max(0, (float) $this->price_vat_rate)
            : ($this->source?->vatRate() ?? 0.0);
    }

    public function effectivePriceCurrency(): string
    {
        return filled($this->price_currency)
            ? mb_strtoupper((string) $this->price_currency)
            : ($this->source?->priceCurrency() ?? 'BYN');
    }

    public function hasPriceSnapshot(): bool
    {
        return $this->price_currency !== null
            || $this->price_currency_rate !== null
            || $this->price_tax_mode !== null
            || $this->price_vat_rate !== null;
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(IntegrationSource::class, 'integration_source_id');
    }

    public function integrationCategory(): BelongsTo
    {
        return $this->belongsTo(IntegrationCategory::class);
    }

    public function targetCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'target_category_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function resolvedSiteCategory(): ?Category
    {
        return $this->product?->category
            ?? $this->targetCategory
            ?? $this->integrationCategory?->siteCategory;
    }

    public function categoryResolutionLabel(): string
    {
        return match (true) {
            filled($this->product?->category_id) => 'Категория привязанной карточки',
            filled($this->target_category_id) => 'Индивидуальное назначение',
            filled($this->integrationCategory?->category_id) => 'Правило группы поставщика',
            default => 'Требуется назначить категорию',
        };
    }
}
