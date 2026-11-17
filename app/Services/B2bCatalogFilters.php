<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class B2bCatalogFilters
{
    public const LABELS = ['in_stock' => 'В наличии', 'priced' => 'С ценой', 'new' => 'Новинки', 'sale' => 'Акции', 'selected' => 'В моём подборе'];

    public function apply(Builder $query, array $flags, ?float $min, ?float $max, array $selectedIds = [], int $supplierId = 0): void
    {
        foreach ($flags as $flag) {
            match ($flag) {
                'in_stock' => $this->stock($query, $supplierId),
                'priced' => $query->where('price', '>', 0),
                'new' => $query->where('is_new', true),
                'sale' => $query->where('is_sale', true)->whereColumn('price_old', '>', 'price')->where('price', '>', 0),
                'selected' => $query->whereIn('id', $selectedIds),
                default => null,
            };
        }
        if ($min !== null) {
            $query->where('price', '>=', $min);
        }
        if ($max !== null) {
            $query->where('price', '<=', $max);
        }
        if ($supplierId > 0) {
            $query->where(fn (Builder $sources) => $sources
                ->whereHas('supplierProducts', fn (Builder $offers) => $offers->where('supplier_id', $supplierId))
                ->orWhereHas('integrationProducts', fn (Builder $offers) => $offers->where('match_status', 'matched')->whereHas('source', fn (Builder $source) => $source->where('supplier_id', $supplierId)->where('is_active', true)))
                ->orWhereHas('warehouseStocks', fn (Builder $stock) => $stock->where('supplier_id', $supplierId)));
        }
    }

    public function stock(Builder $query, int $supplierId = 0): void
    {
        $fresh = now()->subHours(24);
        $query->where(function (Builder $stock) use ($fresh, $supplierId) {
            $stock->where(fn (Builder $manual) => $manual->when($supplierId > 0, fn ($none) => $none->whereRaw('1 = 0'))->where('manual_stock_management', true)->where('in_stock', true)->where('availability_status', Product::AVAILABILITY_IN_STOCK))
                ->orWhereHas('supplierProducts', fn (Builder $offers) => $offers->when($supplierId > 0, fn ($supplier) => $supplier->where('supplier_id', $supplierId))->whereHas('supplier', fn ($supplier) => $supplier->where('is_active', true))
                    ->where(fn ($present) => $present->whereNull('stock_status')->orWhere('stock_status', '!=', 'missing_from_feed'))
                    ->where(fn ($matched) => $matched->where('match_status', 'matched')->orWhereIn('match_confidence', ['manual_price_list', 'official_source', 'brand_assignment']))
                    ->where(fn ($available) => $available->where(fn ($numeric) => $numeric->where('stock_quantity', '>', 0)->whereRaw('COALESCE(last_stock_synced_at, last_synced_at, updated_at) >= ?', [$fresh]))
                        ->orWhere(fn ($always) => $always->where('stock_status', 'always_available')->where('in_stock', true))))
                ->orWhereHas('integrationProducts', fn (Builder $offers) => $offers->where('match_status', 'matched')->where('stock_quantity', '>', 0)
                    ->whereRaw('COALESCE(stock_confirmed_at, last_offer_seen_at, last_seen_at) >= ?', [$fresh])
                    ->whereHas('source', fn ($source) => $source->when($supplierId > 0, fn ($supplier) => $supplier->where('supplier_id', $supplierId))->where('is_active', true)->whereHas('supplier', fn ($supplier) => $supplier->where('is_active', true))))
                ->orWhereHas('warehouseStocks', fn (Builder $warehouses) => $warehouses->when($supplierId > 0, fn ($supplier) => $supplier->where('supplier_id', $supplierId))->where('available_quantity', '>', 0)->whereHas('supplier', fn ($supplier) => $supplier->where('is_active', true))
                    ->whereRaw('COALESCE(last_synced_at, as_of_date, updated_at) >= ?', [$fresh]));
        });
    }

    public function rowMatches(array $row, array $flags, ?float $min, ?float $max): bool
    {
        $product = $row['product'];

        return ($min === null || $row['price'] >= $min) && ($max === null || $row['price'] <= $max)
            && (! in_array('priced', $flags, true) || $row['price'] > 0)
            && (! in_array('new', $flags, true) || $product->is_new)
            && (! in_array('sale', $flags, true) || ($product->is_sale && (float) $product->price_old > (float) $product->price && (float) $product->price > 0));
    }
}
