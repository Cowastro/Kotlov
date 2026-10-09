<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationProduct extends Model
{
    protected $fillable = [
        'integration_source_id', 'integration_category_id', 'target_category_id', 'product_id', 'external_id', 'external_code', 'external_sku',
        'barcode', 'name', 'price', 'stock_quantity', 'match_status',
        'match_method', 'match_confidence', 'candidates', 'payload',
        'matched_at', 'last_seen_at', 'last_offer_seen_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock_quantity' => 'decimal:3',
        'match_confidence' => 'float',
        'candidates' => 'array',
        'payload' => 'array',
        'matched_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_offer_seen_at' => 'datetime',
    ];

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function formattedStockQuantity(): string
    {
        $quantity = (float) $this->stock_quantity;

        $formatted = abs($quantity - round($quantity)) < 0.0005
            ? number_format($quantity, 0, '.', ' ')
            : rtrim(rtrim(number_format($quantity, 3, '.', ' '), '0'), '.');

        return $formatted.' шт.';
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
