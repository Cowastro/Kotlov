<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductWarehouseStock extends Model
{
    public const ECOKAMIN_VALIDITY_MINUTES = 20160;

    public function isFresh(): bool
    {
        $confirmedAt = $this->last_synced_at ?? $this->as_of_date ?? $this->updated_at;
        $minutes = $this->warehouse_code === 'ecokamin-moscow' ? self::ECOKAMIN_VALIDITY_MINUTES : 1440;

        return $confirmedAt !== null && $confirmedAt->gte(now()->subMinutes($minutes));
    }

    public function hasFreshPositiveStock(): bool
    {
        return (float) $this->available_quantity > 0 && $this->isFresh();
    }

    public function scopeFresh(Builder $query): Builder
    {
        return $query->where(fn (Builder $fresh) => $fresh
            ->where(fn (Builder $moscow) => $moscow->where('warehouse_code', 'ecokamin-moscow')
                ->whereRaw('COALESCE(last_synced_at, as_of_date, updated_at) >= ?', [now()->subMinutes(self::ECOKAMIN_VALIDITY_MINUTES)]))
            ->orWhere(fn (Builder $other) => $other->where('warehouse_code', '!=', 'ecokamin-moscow')
                ->whereRaw('COALESCE(last_synced_at, as_of_date, updated_at) >= ?', [now()->subDay()])));
    }

    protected $fillable = [
        'product_id', 'supplier_id', 'warehouse_code', 'warehouse_name',
        'external_article', 'source_product_name', 'total_quantity',
        'reserved_quantity', 'shipping_quantity', 'available_quantity',
        'delivery_days', 'as_of_date', 'source_file', 'last_synced_at', 'raw',
    ];

    protected $casts = [
        'total_quantity' => 'decimal:3',
        'reserved_quantity' => 'decimal:3',
        'shipping_quantity' => 'decimal:3',
        'available_quantity' => 'decimal:3',
        'delivery_days' => 'integer',
        'as_of_date' => 'date',
        'last_synced_at' => 'datetime',
        'raw' => 'array',
    ];

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('available_quantity', '>', 0);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function formattedAvailableQuantity(): string
    {
        $quantity = (float) $this->available_quantity;
        $formatted = abs($quantity - round($quantity)) < 0.0005
            ? number_format($quantity, 0, '.', ' ')
            : rtrim(rtrim(number_format($quantity, 3, '.', ' '), '0'), '.');

        return $formatted.' шт.';
    }
}
