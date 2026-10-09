<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProduct extends Model
{
    public const STALE_AFTER_HOURS = 24;

    protected $fillable = [
        'supplier_id',
        'supplier_sync_id',
        'product_id',
        'product_sku',
        'supplier_article',
        'supplier_article_normalized',
        'supplier_article_compact',
        'supplier_name',
        'source_url',
        'source_wp_id',
        'price',
        'currency',
        'currency_rate',
        'price_byn',
        'in_stock',
        'stock_quantity',
        'stock_status',
        'stock_text',
        'warehouse_name',
        'delivery_days',
        'last_stock_synced_at',
        'match_status',
        'match_confidence',
        'raw',
        'last_synced_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'currency_rate' => 'float',
        'price_byn' => 'decimal:2',
        'in_stock' => 'boolean',
        'stock_quantity' => 'integer',
        'delivery_days' => 'integer',
        'raw' => 'array',
        'last_synced_at' => 'datetime',
        'last_stock_synced_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierSync(): BelongsTo
    {
        return $this->belongsTo(SupplierSync::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeForSupplierIds(Builder $query, iterable $supplierIds): Builder
    {
        return $query->whereIn('supplier_id', collect($supplierIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values());
    }

    public function scopeStale(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $staleBefore = ($now ?? now())->copy()->subHours(self::STALE_AFTER_HOURS);

        return $query->where(fn (Builder $query): Builder => $query
            ->whereNull('last_synced_at')
            ->orWhere('last_synced_at', '<', $staleBefore));
    }

    public function scopeNeedsAttention(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $staleBefore = ($now ?? now())->copy()->subHours(self::STALE_AFTER_HOURS);

        return $query->where(fn (Builder $query): Builder => $query
            ->whereNull('product_id')
            ->orWhereNull('price_byn')
            ->orWhere('price_byn', '<=', 0)
            ->orWhereNull('last_synced_at')
            ->orWhere('last_synced_at', '<', $staleBefore));
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query
            ->where('in_stock', true)
            ->orWhere('stock_quantity', '>', 0));
    }
}
