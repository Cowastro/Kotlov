<?php

namespace App\Support\Catalog;

use App\Models\SupplierProduct;
use App\Services\PriceList\BelomoPriceList;

final class EliconGasMeterCatalog
{
    // Planned purchases are a ranking preference, never proof of stock.
    public const PLANNED_ARTICLES = ['818110', '818124', '807221', '807223'];

    public static function models(): array
    {
        $snapshot = json_decode(file_get_contents(database_path('data/supplier-catalogs/elicon-gas-meters-2026-11-23.json')), true, 512, JSON_THROW_ON_ERROR);

        return array_map(function (array $row): array {
            $article = self::fullArticle($row['article']);
            preg_match('/L\s*=\s*(\d+)/u', $row['name'], $length);
            preg_match('/\b(левый|правый)\b/ui', $row['name'], $direction);

            return $row + ['full_article' => $article, 'center_distance_mm' => isset($length[1]) ? (int) $length[1] : null,
                'flow_direction' => $direction[1] ?? null];
        }, $snapshot['models']);
    }

    public static function fullArticle(string $value): string
    {
        $compact = preg_replace('/[^0-9]/', '', $value);
        if (preg_match('/^(\d{4})-(\d{2})$/', $value, $m)) {
            return $m[1].'.00.00.000'.($m[2] === '00' && in_array($m[1], ['8345', '8348', '8349'], true) ? '' : '-'.$m[2]);
        }
        if (preg_match('/^(\d{4})0000000(\d{2})$/', $compact, $m)) {
            return $m[1].'.00.00.000-'.$m[2];
        }

        return $value;
    }

    public static function planned(string $article): bool
    {
        return in_array((new BelomoPriceList)->shortArticle($article), self::PLANNED_ARTICLES, true);
    }

    public static function prioritizeCatalog($query): void
    {
        $articles = [];
        foreach (self::PLANNED_ARTICLES as $article) {
            $short = substr($article, 0, 4).'-'.substr($article, 4);
            $articles[] = $short;
            $articles[] = self::fullArticle($short);
        }
        $ids = SupplierProduct::query()->select('product_id')->whereNotNull('product_id')->whereIn('supplier_article', $articles);
        $query->orderByRaw('CASE WHEN products.sku = ? OR products.id IN ('.$ids->toSql().') THEN 0 ELSE 1 END', ['BELOMO-8181-24', ...$ids->getBindings()]);
    }
}
