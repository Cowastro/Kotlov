<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

class ManagerStorefrontProductContext
{
    public function canView(?User $user): bool
    {
        return $user?->is_active === true && $user->isManager();
    }

    /**
     * @return Collection<int, array{
     *     supplier: string,
     *     phones: list<string>,
     *     emails: list<string>,
     *     contact: ?string,
     *     article: ?string,
     *     supplier_product_name: ?string,
     *     purchase_price: ?float,
     *     retail_price: ?float,
     *     margin: ?float,
     *     margin_percent: ?float,
     *     stock: string,
     *     warehouse: ?string,
     *     delivery_days: ?int,
     *     in_stock: bool,
     *     is_fresh: bool,
     *     updated_at: mixed,
     *     locations: list<array{warehouse:?string,stock:string,in_stock:bool,is_fresh:bool,updated_at:mixed}>
     * }>
     */
    public function offers(Product $product, ?User $user): Collection
    {
        if (! $this->canView($user)) {
            return collect();
        }

        $product->loadMissing([
            'brand:id,name,slug',
            'supplierProducts.supplier.crmCounterparty.contactPoints',
            'integrationProducts.source.supplier.crmCounterparty.contactPoints',
            'integrationProducts.source.latestSuccessfulCatalogExchangeRun',
            'warehouseStocks.supplier.crmCounterparty.contactPoints',
        ]);

        $retailPrice = (float) $product->price > 0 ? (float) $product->price : null;
        $productNameKey = $this->productNameKey($product->name);
        $isEcokamin = $product->brand?->slug === 'ecokamin'
            || mb_strtolower((string) $product->brand?->name) === 'экокамин';

        $supplierOffers = $product->supplierProducts
            ->reject(fn ($offer): bool => $this->isTeplovManufacturer($offer->supplier?->name))
            ->map(function ($offer) use ($retailPrice, $productNameKey, $isEcokamin): array {
                $quantity = $offer->stock_quantity;
                $inStock = (float) $quantity > 0 || $offer->in_stock === true;
                $purchasePrice = $offer->stock_status !== 'missing_from_feed' && (float) $offer->price_byn > 0
                    ? (float) $offer->price_byn
                    : null;

                return [
                    'supplier_key' => $this->supplierKey($offer->supplier?->name),
                    'matches_product_name' => $this->productNameKey($offer->supplier_name) === $productNameKey,
                    'supplier' => $offer->supplier?->name ?: ($offer->supplier?->code ?: 'Поставщик'),
                    'phones' => $offer->supplier?->contactPhoneNumbers() ?? [],
                    'emails' => $offer->supplier?->contactEmailAddresses() ?? [],
                    'contact' => $offer->supplier?->contact,
                    'article' => $offer->supplier_article,
                    'supplier_product_name' => $offer->supplier_name,
                    'purchase_price' => $purchasePrice,
                    'retail_price' => $retailPrice,
                    'margin' => $this->margin($retailPrice, $purchasePrice),
                    'margin_percent' => $this->marginPercent($retailPrice, $purchasePrice),
                    'stock' => $quantity !== null
                        ? $this->formatQuantity((float) $quantity)
                        : ($inStock ? 'В наличии' : ($offer->stock_text ?: 'Нет в наличии')),
                    'stock_quantity' => $quantity !== null ? (float) $quantity : null,
                    'warehouse' => $this->warehouseLabel($offer->warehouse_name, $isEcokamin),
                    'delivery_days' => $offer->delivery_days,
                    'in_stock' => $inStock,
                    'is_fresh' => $offer->confirmsAvailability(),
                    'updated_at' => $offer->last_stock_synced_at ?? $offer->last_synced_at ?? $offer->updated_at,
                ];
            });

        $integrationOffers = $product->integrationProducts
            ->reject(fn ($offer): bool => $this->isTeplovManufacturer($offer->source?->supplier?->name))
            ->map(function ($offer) use ($retailPrice, $productNameKey, $isEcokamin): array {
                $quantity = (float) $offer->stock_quantity;
                $source = $offer->source;
                $purchasePrice = $offer->normalizedPriceByn();

                return [
                    'supplier_key' => $this->supplierKey($source?->supplier?->name ?: $source?->name),
                    'matches_product_name' => $this->productNameKey($offer->name) === $productNameKey,
                    'supplier' => $source?->supplier?->name ?: ($source?->name ?: 'Интеграция'),
                    'phones' => $source?->supplier?->contactPhoneNumbers() ?? [],
                    'emails' => $source?->supplier?->contactEmailAddresses() ?? [],
                    'contact' => $source?->supplier?->contact,
                    'article' => $offer->external_sku,
                    'supplier_product_name' => $offer->name,
                    'purchase_price' => $purchasePrice,
                    'retail_price' => $retailPrice,
                    'margin' => $this->margin($retailPrice, $purchasePrice),
                    'margin_percent' => $this->marginPercent($retailPrice, $purchasePrice),
                    'stock' => $this->formatQuantity($quantity),
                    'stock_quantity' => $quantity,
                    'warehouse' => $this->warehouseLabel(
                        data_get($source?->settings, 'warehouse_label'),
                        $isEcokamin,
                    ),
                    'delivery_days' => null,
                    'in_stock' => $quantity > 0,
                    'is_fresh' => $offer->hasFreshPositiveStock(),
                    'updated_at' => $offer->stock_confirmed_at ?? $offer->last_offer_seen_at ?? $offer->last_seen_at,
                ];
            });

        $warehouseOffers = $product->warehouseStocks->map(function ($stock) use ($retailPrice): array {
            $supplier = $stock->supplier;

            return [
                'supplier_key' => $this->supplierKey($supplier?->name),
                'matches_product_name' => true,
                'supplier' => $supplier?->name ?: 'ООО «СанБизнесГруп»',
                'phones' => $supplier?->contactPhoneNumbers() ?? [],
                'emails' => $supplier?->contactEmailAddresses() ?? [],
                'contact' => $supplier?->contact,
                'article' => $stock->external_article,
                'supplier_product_name' => $stock->source_product_name,
                'purchase_price' => null,
                'retail_price' => $retailPrice,
                'margin' => null,
                'margin_percent' => null,
                'stock' => $stock->formattedAvailableQuantity(),
                'stock_quantity' => (float) $stock->available_quantity,
                'warehouse' => $stock->warehouse_name,
                'delivery_days' => $stock->delivery_days,
                'in_stock' => (float) $stock->available_quantity > 0,
                'is_fresh' => true,
                'updated_at' => $stock->as_of_date ?? $stock->last_synced_at,
            ];
        });

        return $supplierOffers
            ->concat($integrationOffers)
            ->concat($warehouseOffers)
            ->sortBy([
                ['matches_product_name', 'desc'],
                ['is_fresh', 'desc'],
                ['in_stock', 'desc'],
                ['purchase_price', 'asc'],
            ])
            // One supplier may expose several warehouses or retain historical
            // links. Keep the supplier once and preserve its warehouse breakdown
            // inside that single offer instead of repeating the same name.
            ->groupBy('supplier_key')
            ->map(fn (Collection $offers): array => $this->mergeSupplierOffers($offers))
            ->values();
    }

    /** @param Collection<int, array<string, mixed>> $offers */
    private function mergeSupplierOffers(Collection $offers): array
    {
        $best = $offers->first();
        $locations = $offers
            ->unique(fn (array $offer): string => mb_strtolower((string) ($offer['warehouse'] ?? '')))
            ->map(fn (array $offer): array => [
                'warehouse' => $offer['warehouse'],
                'stock' => $offer['stock'],
                'stock_quantity' => $offer['stock_quantity'],
                'in_stock' => $offer['in_stock'],
                'is_fresh' => $offer['is_fresh'],
                'updated_at' => $offer['updated_at'],
            ])
            ->values();

        $best['phones'] = $offers->pluck('phones')->flatten()->filter()->unique()->values()->all();
        $best['emails'] = $offers->pluck('emails')->flatten()->filter()->unique()->values()->all();
        $best['locations'] = $locations->all();

        if ($locations->count() === 1) {
            return $best;
        }

        $best['warehouse'] = null;
        $best['in_stock'] = $locations->contains(fn (array $location): bool => $location['in_stock']);
        $best['is_fresh'] = $locations->contains(
            fn (array $location): bool => $location['in_stock'] && $location['is_fresh'],
        );
        $best['updated_at'] = $locations->pluck('updated_at')->filter()->sortDesc()->first();

        $freshQuantity = $locations
            ->filter(fn (array $location): bool => $location['in_stock'] && $location['is_fresh'])
            ->sum(fn (array $location): float => max(0, (float) ($location['stock_quantity'] ?? 0)));
        $warehouseCount = $locations->count();
        $warehouseLabel = $warehouseCount.' '.($warehouseCount === 2 ? 'склада' : 'складов');
        $best['stock'] = $freshQuantity > 0
            ? $this->formatQuantity($freshQuantity).' · '.$warehouseLabel
            : ($best['in_stock'] ? 'В наличии · '.$warehouseLabel : 'Нет в наличии');

        return $best;
    }

    private function supplierKey(?string $name): string
    {
        $normalized = preg_replace('/[^\pL\pN]+/u', '', mb_strtolower($name ?? ''));

        return $normalized !== '' ? $normalized : 'unknown-supplier';
    }

    private function productNameKey(?string $name): string
    {
        return preg_replace('/[^\pL\pN]+/u', '', mb_strtolower($name ?? '')) ?? '';
    }

    private function isTeplovManufacturer(?string $name): bool
    {
        // These manufacturer catalogue links are not a separate sales supplier.
        // Keep their source data intact; SBG's real offers carry price and stock.
        return preg_replace('/[^\pL\pN]/u', '', mb_strtolower($name ?? '')) === 'тепловисухов';
    }

    private function warehouseLabel(?string $label, bool $isEcokamin): ?string
    {
        $label = trim((string) $label);
        if ($isEcokamin && in_array(mb_strtolower($label), ['', 'основной', 'основной склад'], true)) {
            return 'Склад Минск';
        }

        return $label !== '' ? $label : null;
    }

    private function formatQuantity(float $quantity): string
    {
        $formatted = abs($quantity - round($quantity)) < 0.0005
            ? number_format($quantity, 0, '.', ' ')
            : rtrim(rtrim(number_format($quantity, 3, '.', ' '), '0'), '.');

        return $formatted.' шт.';
    }

    private function margin(?float $retailPrice, ?float $purchasePrice): ?float
    {
        if ($retailPrice === null || $purchasePrice === null) {
            return null;
        }

        return round($retailPrice - $purchasePrice, 2);
    }

    private function marginPercent(?float $retailPrice, ?float $purchasePrice): ?float
    {
        if ($retailPrice === null || $retailPrice <= 0 || $purchasePrice === null) {
            return null;
        }

        return round((($retailPrice - $purchasePrice) / $retailPrice) * 100, 1);
    }
}
