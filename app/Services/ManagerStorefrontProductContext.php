<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Services\Pricing\B2bBrandRetailPricing;
use App\Support\Suppliers\EcokaminChannel;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

        return $this->buildOffers($product);
    }

    /** Only city-level stock labels are public. */
    public function ecokaminWarehouseLabels(Product $product): array
    {
        if (! $this->isEcokamin($product)) {
            return [];
        }

        return $this->buildOffers($product)->pluck('city_stock')->filter()->first() ?? [];
    }

    private function isEcokamin(Product $product): bool
    {
        return $product->brand?->slug === 'ecokamin'
            || mb_strtolower((string) $product->brand?->name) === 'экокамин';
    }

    private function buildOffers(Product $product): Collection
    {
        $product->loadMissing([
            'brand:id,name,slug',
            'supplierProducts.supplier.crmCounterparty.contactPoints',
            'integrationProducts.source.supplier.crmCounterparty.contactPoints',
            'integrationProducts.source.latestSuccessfulCatalogExchangeRun',
            'warehouseStocks.supplier.crmCounterparty.contactPoints',
        ]);

        $retailPrice = (float) $product->price > 0 ? (float) $product->price : null;
        $productNameKey = $this->productNameKey($product->name);
        $isEcokamin = $this->isEcokamin($product);
        // СанБизнесГруп sells ЭкоКамин to СБГ бай at the retail price less the dealer discount (−20%, the same
        // fixed discount as the partner price), so this is our purchase price for every СанБизнесГруп channel.
        $sbgPurchase = $isEcokamin && $retailPrice !== null
            ? app(B2bBrandRetailPricing::class)->priceFor($product)
            : null;
        // Теплов и Сухов: the wholesale price of our own loaded price list (shop.b2b_price_list_wholesale_suppliers)
        // is the price of every СанБизнесГруп channel; the 1C price of these items is old or missing.
        $sbgPurchase ??= app(B2bBrandRetailPricing::class)->priceListWholesale($product);
        $isSbg = fn ($supplier): bool => $supplier?->code === EcokaminChannel::SUPPLIER_CODE;

        $supplierOffers = $product->supplierProducts
            ->reject(fn ($offer): bool => $this->isTeplovManufacturer($offer->supplier?->name))
            ->map(function ($offer) use ($retailPrice, $productNameKey, $isEcokamin, $product, $sbgPurchase, $isSbg): array {
                $quantity = $offer->stock_quantity;
                $inStock = (float) $quantity > 0 || $offer->in_stock === true;
                $purchasePrice = $offer->stock_status !== 'missing_from_feed' && (float) $offer->price_byn > 0
                    ? (float) $offer->price_byn
                    : null;
                if ($sbgPurchase !== null && $isSbg($offer->supplier)) {
                    $purchasePrice = $sbgPurchase;
                }

                return [
                    'manual_match' => false,
                    'legacy_supplier_offer' => true,
                    'confirmed_quantity' => $offer->supplier?->is_active && $offer->match_status !== 'ignored'
                        && $offer->stock_status !== 'missing_from_feed' && $offer->hasFreshStockQuantity(),
                    'assignment_only' => $offer->match_confidence === 'brand_assignment',
                    'supplier_key' => $this->supplierKey($offer->supplier?->name),
                    'matches_product_name' => $this->productNameKey($offer->supplier_name) === $productNameKey,
                    'supplier' => $offer->supplier?->name ?: ($offer->supplier?->code ?: 'Поставщик'),
                    'phones' => $offer->supplier?->contactPhoneNumbers() ?? [],
                    'emails' => $offer->supplier?->contactEmailAddresses() ?? [],
                    'contact' => $offer->supplier?->contact,
                    'article' => $offer->supplier_article,
                    'stock_key' => 'supplier:'.$offer->id,
                    'catalog_code' => $offer->supplier_catalog_code,
                    'warehouse_breakdown' => $offer->warehouseLines(),
                    'source_delivery_date' => $offer->fulfillment_details['delivery_date'] ?? null,
                    'warehouse_quantity_conflict' => $offer->fulfillment_details['quantity_conflict'] ?? false,
                    'supplier_product_name' => $offer->supplier_name,
                    'purchase_price' => $purchasePrice,
                    'retail_price' => $retailPrice,
                    'margin' => $this->margin($retailPrice, $purchasePrice),
                    'margin_percent' => $this->marginPercent($retailPrice, $purchasePrice),
                    'stock' => $quantity !== null
                        ? $this->formatQuantity((float) $quantity, $product->unit)
                        : ($inStock ? 'В наличии' : ($offer->stock_text ?: 'Нет в наличии')),
                    'stock_quantity' => $quantity !== null ? (float) $quantity : null,
                    // The ecokamin.ru catalogue only says the maker has it: not our Minsk warehouse.
                    'warehouse' => $isEcokamin && str_contains(mb_strtolower((string) $offer->source_url), 'ecokamin.ru')
                        ? 'Сайт производителя ecokamin.ru'
                        : $this->warehouseLabel($offer->warehouse_name, $isEcokamin),
                    'delivery_days' => $offer->delivery_days,
                    'in_stock' => $inStock,
                    'is_fresh' => $offer->confirmsAvailability(),
                    'updated_label' => $offer->last_stock_synced_at ? 'Остаток обновлён' : ($quantity !== null || $inStock ? 'Данные поставщика обновлены' : ((float) $offer->price_byn > 0 ? 'Прайс обновлён' : 'Привязка поставщика обновлена')),
                    'updated_at' => $offer->last_stock_synced_at ?? $offer->last_synced_at ?? $offer->updated_at,
                ];
            });

        $integrationOffers = $product->integrationProducts
            ->reject(fn ($offer): bool => $this->isTeplovManufacturer($offer->source?->supplier?->name))
            ->map(function ($offer) use ($retailPrice, $productNameKey, $isEcokamin, $product, $sbgPurchase, $isSbg): array {
                $quantity = $offer->hasFreshStock() ? (float) $offer->stock_quantity : null;
                $source = $offer->source;
                $purchasePrice = $sbgPurchase !== null && $isSbg($source?->supplier)
                    ? $sbgPurchase
                    : $offer->normalizedPriceByn();

                return [
                    // A manager-confirmed 1C link is the authoritative offer for
                    // this card even when an older legacy supplier row happens
                    // to repeat the public product name verbatim.
                    'manual_match' => in_array($offer->match_method, [
                        'manual_search',
                        'manual_candidate',
                        'manual_suggestion',
                    ], true),
                    'legacy_supplier_offer' => false,
                    'assignment_only' => false,
                    'updated_label' => $quantity !== null ? 'Остаток обновлён' : 'Данные 1С обновлены',
                    'confirmed_quantity' => $offer->match_status === 'matched' && $quantity !== null,
                    'supplier_key' => $this->supplierKey($source?->supplier?->name ?: $source?->name),
                    'matches_product_name' => $this->productNameKey($offer->name) === $productNameKey,
                    'supplier' => $source?->supplier?->name ?: ($source?->name ?: 'Интеграция'),
                    'phones' => $source?->supplier?->contactPhoneNumbers() ?? [],
                    'emails' => $source?->supplier?->contactEmailAddresses() ?? [],
                    'contact' => $source?->supplier?->contact,
                    'article' => $offer->external_sku,
                    'stock_key' => 'integration:'.$offer->id,
                    'supplier_product_name' => $offer->name,
                    'purchase_price' => $purchasePrice,
                    'retail_price' => $retailPrice,
                    'margin' => $this->margin($retailPrice, $purchasePrice),
                    'margin_percent' => $this->marginPercent($retailPrice, $purchasePrice),
                    'stock' => $quantity !== null ? $this->formatQuantity($quantity, $product->unit) : 'Остаток устарел · уточнить',
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

        $warehouseOffers = $product->warehouseStocks->map(function ($stock) use ($retailPrice, $sbgPurchase, $isSbg): array {
            $supplier = $stock->supplier;
            $purchasePrice = $sbgPurchase !== null && $isSbg($supplier) ? $sbgPurchase : null;

            return [
                'manual_match' => false,
                'legacy_supplier_offer' => false,
                'assignment_only' => false,
                'updated_label' => 'Остаток обновлён',
                'supplier_key' => $this->supplierKey($supplier?->name),
                'matches_product_name' => true,
                'supplier' => $supplier?->name ?: 'ООО «СанБизнесГруп»',
                'phones' => $supplier?->contactPhoneNumbers() ?? [],
                'emails' => $supplier?->contactEmailAddresses() ?? [],
                'contact' => $supplier?->contact,
                'article' => $stock->external_article,
                'supplier_product_name' => $stock->source_product_name,
                'purchase_price' => $purchasePrice,
                'retail_price' => $retailPrice,
                'margin' => $this->margin($retailPrice, $purchasePrice),
                'margin_percent' => $this->marginPercent($retailPrice, $purchasePrice),
                'stock' => $stock->isFresh() ? $stock->formattedAvailableQuantity() : 'Остаток уточняется',
                'stock_quantity' => $stock->isFresh() ? (float) $stock->available_quantity : null,
                'stock_key' => 'warehouse:'.$stock->id,
                'confirmed_quantity' => $stock->isFresh() && $stock->available_quantity !== null,
                'warehouse' => $stock->warehouse_name,
                'delivery_days' => $stock->delivery_days,
                'in_stock' => $stock->hasFreshPositiveStock(),
                'is_fresh' => $stock->isFresh(),
                'updated_at' => $stock->as_of_date ?? $stock->last_synced_at,
            ];
        });

        return $supplierOffers
            ->concat($integrationOffers)
            ->concat($warehouseOffers)
            ->sortBy([
                ['manual_match', 'desc'],
                ['matches_product_name', 'desc'],
                ['is_fresh', 'desc'],
                ['in_stock', 'desc'],
                ['purchase_price', 'asc'],
            ])
            // One supplier may expose several warehouses or retain historical
            // links. Keep the supplier once and preserve its warehouse breakdown
            // inside that single offer instead of repeating the same name.
            ->groupBy('supplier_key')
            ->map(fn (Collection $offers): array => $this->mergeSupplierOffers($offers, $isEcokamin))
            ->values();
    }

    /** @param Collection<int, array<string, mixed>> $offers */
    private function mergeSupplierOffers(Collection $offers, bool $isEcokamin): array
    {
        // A brand-to-supplier assignment is a contact fallback, not a second
        // physical warehouse. Keep it only when there is no real source.
        if ($offers->contains(fn (array $offer): bool => ! ($offer['assignment_only'] ?? false))) {
            $offers = $offers->reject(fn (array $offer): bool => $offer['assignment_only'] ?? false)->values();
        }
        if ($offers->contains(fn (array $offer): bool => $offer['manual_match'])) {
            // A confirmed integration row supersedes legacy rows for the same
            // supplier. Keep true warehouse records, which can represent an
            // additional physical location rather than a duplicate feed row.
            $offers = $offers
                ->reject(fn (array $offer): bool => $offer['legacy_supplier_offer'])
                ->values();
        }

        $best = $offers->sortBy([['manual_match', 'desc'], ['is_fresh', 'desc'], ['in_stock', 'desc'], ['matches_product_name', 'desc']])->first();
        $locations = $offers
            ->filter(fn (array $offer): bool => ! ($offer['assignment_only'] ?? false) && ($offer['stock_quantity'] !== null || $offer['in_stock']))
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
        if ($isEcokamin && $offers->contains(fn (array $row): bool => ! $row['assignment_only']
            && in_array($row['warehouse'], ['Склад Москва', 'Склад Минск'], true))) {
            foreach (['Москва', 'Минск'] as $city) {
                $confirmed = $offers->first(fn (array $row): bool => $row['warehouse'] === 'Склад '.$city
                    && ! $row['assignment_only'] && $row['confirmed_quantity']);
                $best['city_stock'][$city] = $confirmed['stock'] ?? 'наличие уточняется';
            }
        }
        if ($best['assignment_only'] ?? false) {
            $best['warehouse'] = null;
            $best['stock'] = 'Остаток не загружался · уточнить';
        }

        if ($locations->count() <= 1) {
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

    private function formatQuantity(float $quantity, ?string $unit = null): string
    {
        $formatted = abs($quantity - round($quantity)) < 0.0005
            ? number_format($quantity, 0, '.', ' ')
            : rtrim(rtrim(number_format($quantity, 3, '.', ' '), '0'), '.');

        $suffix = Str::contains(Str::lower(trim((string) $unit)), ['пог', 'метр']) ? ' пог. м' : ' шт.';

        return $formatted.$suffix;
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
