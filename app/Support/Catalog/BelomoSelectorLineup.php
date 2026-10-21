<?php

namespace App\Support\Catalog;

use App\Models\Product;
use App\Services\PriceList\BelomoPriceList;

final class BelomoSelectorLineup
{
    /** @return array<string, array{nominal: float, max: float, purpose: string}> */
    public static function sizes(): array
    {
        return GasMeterSelectionGuide::SIZES + [
            'G10' => ['nominal' => 10.0, 'max' => 16.0, 'purpose' => 'повышенный расход до 16 м³/ч — только после проверки проекта'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function models(?BelomoPriceList $priceList = null): array
    {
        $priceList ??= new BelomoPriceList();

        return array_values(array_filter(array_map(
            fn (array $row): ?array => self::fromPriceRow($row, $priceList),
            $priceList->rows(),
        )));
    }

    public static function matchesProduct(Product $product, array $model): bool
    {
        $article = (string) ($model['article_compact'] ?? '');
        if ($article !== '' && $product->supplierProducts->contains(
            fn ($offer): bool => self::compact((string) $offer->supplier_article) === $article
        )) {
            return true;
        }

        $sku = self::compact((string) $product->sku);
        if ($sku !== '' && ($sku === (string) ($model['article_short'] ?? '') || $sku === $article)) {
            return true;
        }

        return self::searchable((string) $product->name) === self::searchable((string) $model['name']);
    }

    public static function attachProduct(array $model, Product $product): array
    {
        $inStock = $product->hasAvailableSupplierChannel()
            && $product->effectiveAvailabilityStatus() === Product::AVAILABILITY_IN_STOCK;
        $buyable = $inStock && $product->canBeAddedToCart();
        $price = (float) $product->price;
        $slug = trim((string) $product->slug);
        $categorySlug = trim((string) ($product->category?->slug ?? 'schetchiki-gaza'));

        return array_replace($model, [
            'product_id' => $product->id,
            'name' => (string) $product->name,
            'stock_priority' => $inStock,
            'stock_program' => $inStock,
            'availability_status' => $inStock ? Product::AVAILABILITY_IN_STOCK : Product::AVAILABILITY_CHECK,
            'availability_label' => $inStock ? $product->publicAvailabilityLabel() : 'Под заказ · уточним цену и срок',
            'price' => $price > 0 ? $price : null,
            'price_label' => $price > 0 ? number_format($price, 2, '.', ' ').' BYN' : 'Цена по запросу',
            'buyable' => $buyable,
            'request_only' => ! $buyable,
            'url' => $slug !== '' ? '/'.$categorySlug.'/'.$slug : GasMeterSelectionGuide::PAGE_URL,
        ]);
    }

    /** @param array<int, array<string, mixed>> $models */
    public static function families(array $models): array
    {
        return collect($models)
            ->groupBy('family_key')
            ->map(function ($familyModels): array {
                $first = $familyModels->first();

                return [
                    'key' => $first['family_key'],
                    'label' => $first['family_label'],
                    'size' => $first['size'],
                    'technology' => $first['technology'],
                    'technology_label' => $first['technology'] === 'ultrasonic' ? 'Ультразвуковой' : 'Диафрагменный',
                    'max_flow' => $first['max_flow'],
                    'purpose' => $first['purpose'],
                    'executions' => $familyModels->count(),
                ];
            })
            ->sortBy(fn (array $family): string => sprintf('%05.1f-%s', $family['max_flow'], $family['label']))
            ->values()
            ->all();
    }

    private static function fromPriceRow(array $row, BelomoPriceList $priceList): ?array
    {
        $name = trim((string) $row['name']);
        if (! preg_match('/\bG\s*[-]?\s*(1[,.]6|2[,.]5|4|6|10)(?=$|[^\d,.])/ui', $name, $sizeMatch)) {
            return null;
        }

        $size = 'G'.str_replace('.', ',', $sizeMatch[1]);
        $sizeData = self::sizes()[$size] ?? null;
        if (! $sizeData) {
            return null;
        }

        $lower = mb_strtolower($name);
        $technology = str_contains($lower, 'ультразвуков') ? 'ultrasonic' : 'diaphragm';
        $thermal = str_contains($lower, 'сгд-3т') || str_contains($lower, 'g4ти') || str_contains($lower, 'термокомпенс');
        $direction = match (mb_strtolower((string) ($row['flow_direction'] ?? ''))) {
            'левый' => 'left',
            'правый' => 'right',
            default => null,
        };
        [$familyKey, $familyLabel] = self::family($lower, $size, $thermal);
        $article = trim((string) $row['article']);

        return [
            'article' => $priceList->shortArticle($article),
            'article_label' => $article,
            'article_compact' => $priceList->compactArticle($article),
            'article_short' => $priceList->shortArticle($article),
            'name' => $name,
            'size' => $size,
            'max_flow' => $sizeData['max'],
            'purpose' => $sizeData['purpose'],
            'direction' => $direction,
            'direction_label' => match ($direction) {
                'left' => 'левый',
                'right' => 'правый',
                default => 'направление не требуется уточнять',
            },
            'length' => $row['center_distance_mm'],
            'thermal' => $thermal,
            'technology' => $technology,
            'technology_label' => $technology === 'ultrasonic' ? 'ультразвуковой' : 'диафрагменный',
            'family_key' => $familyKey,
            'family_label' => $familyLabel,
            'stock_priority' => false,
            'stock_program' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'availability_label' => 'Под заказ · уточним цену и срок',
            'price' => null,
            'price_label' => 'Цена по запросу',
            'buyable' => false,
            'request_only' => true,
            'product_id' => null,
            'url' => GasMeterSelectionGuide::PAGE_URL,
            'technical_url' => in_array($familyKey, ['kata-'.$size, 'skat-'.$size], true)
                ? BelomoGasMeterDesignations::PAGE_URL.'#variants'
                : null,
        ];
    }

    /** @return array{0:string,1:string} */
    private static function family(string $name, string $size, bool $thermal): array
    {
        $sizeKey = str_replace(',', '-', mb_strtolower($size));

        return match (true) {
            str_contains($name, 'сгмн') => ['sgmn-'.$sizeKey, 'СГМН '.$size],
            str_contains($name, 'сгд4') => ['sgd4-'.$sizeKey, 'СГД4 '.$size.($thermal ? ' с ТК' : '')],
            str_contains($name, 'сгд-3т') => ['sgd-3t-'.$sizeKey, 'СГД-3Т '.$size],
            str_contains($name, 'сгд-') => ['sgd-'.$sizeKey, 'СГД '.$size],
            str_contains($name, 'ката') => ['kata-'.$size, 'КАТА '.$size],
            str_contains($name, 'скат') => ['skat-'.$size, 'СКАТ '.$size],
            default => ['vega-'.$sizeKey, 'ВЕГА '.$size],
        };
    }

    private static function compact(string $value): string
    {
        return mb_strtoupper(preg_replace('/[^\pL\pN]+/u', '', str_replace('*', '', $value)) ?? '');
    }

    private static function searchable(string $value): string
    {
        return mb_strtolower(preg_replace('/[^\pL\pN]+/u', '', str_replace(',', '.', $value)) ?? '');
    }
}
