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
     *     updated_at: mixed
     * }>
     */
    public function offers(Product $product, ?User $user): Collection
    {
        if (! $this->canView($user)) {
            return collect();
        }

        $product->loadMissing([
            'supplierProducts.supplier.crmCounterparty.contactPoints',
            'integrationProducts.source.supplier.crmCounterparty.contactPoints',
            'integrationProducts.source.latestSuccessfulCatalogExchangeRun',
        ]);

        $retailPrice = (float) $product->price > 0 ? (float) $product->price : null;
        $productNameKey = $this->productNameKey($product->name);

        $supplierOffers = $product->supplierProducts
            ->reject(fn ($offer): bool => $this->isTeplovManufacturer($offer->supplier?->name))
            ->map(function ($offer) use ($retailPrice, $productNameKey): array {
                $quantity = $offer->stock_quantity;
                $inStock = (float) $quantity > 0 || $offer->in_stock === true;
                $purchasePrice = (float) $offer->price_byn > 0 ? (float) $offer->price_byn : null;

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
                    'warehouse' => $offer->warehouse_name,
                    'delivery_days' => $offer->delivery_days,
                    'in_stock' => $inStock,
                    'is_fresh' => $offer->confirmsAvailability(),
                    'updated_at' => $offer->last_stock_synced_at ?? $offer->last_synced_at ?? $offer->updated_at,
                ];
            });

        $integrationOffers = $product->integrationProducts
            ->reject(fn ($offer): bool => $this->isTeplovManufacturer($offer->source?->supplier?->name))
            ->map(function ($offer) use ($retailPrice, $productNameKey): array {
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
                    'warehouse' => data_get($source?->settings, 'warehouse_label'),
                    'delivery_days' => null,
                    'in_stock' => $quantity > 0,
                    'is_fresh' => $offer->hasFreshPositiveStock(),
                    'updated_at' => $offer->stock_confirmed_at ?? $offer->last_offer_seen_at ?? $offer->last_seen_at,
                ];
            });

        return $supplierOffers
            ->concat($integrationOffers)
            ->sortBy([
                ['matches_product_name', 'desc'],
                ['is_fresh', 'desc'],
                ['in_stock', 'desc'],
                ['purchase_price', 'asc'],
            ])
            // A product can retain several historical links to the same supplier
            // (or have both a legacy and an integration offer). Managers need one
            // best current row per supplier, not repeated labels that consume the
            // compact card's two available slots.
            ->unique('supplier_key')
            ->values();
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
