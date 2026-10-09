<?php

namespace App\Services\Integrations;

use App\Models\IntegrationProduct;
use App\Models\Product;

class IntegrationProductMatchAdvisor
{
    /**
     * @return array{
     *     product_id:int,
     *     product_name:string,
     *     product_sku:?string,
     *     category_name:?string,
     *     confidence:float,
     *     method_label:string,
     *     reason:string,
     *     warning:string
     * }|null
     */
    public function explain(IntegrationProduct $item): ?array
    {
        if ($item->match_status !== 'suggested' || $item->product_id) {
            return null;
        }

        $candidate = $item->candidates[0] ?? null;
        $productId = (int) ($candidate['product_id'] ?? 0);
        if ($productId <= 0) {
            return null;
        }

        $product = Product::query()
            ->with('category:id,name')
            ->find($productId, ['id', 'category_id', 'sku', 'name']);
        if (! $product) {
            return null;
        }

        $confidence = min(1, max(0, (float) ($candidate['score'] ?? $item->match_confidence ?? 0)));
        $method = (string) $item->match_method;

        return [
            'product_id' => (int) $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'category_name' => $product->category?->name,
            'confidence' => round($confidence, 4),
            'method_label' => match ($method) {
                'exact_name' => 'Полное совпадение названия',
                'fuzzy_name' => 'Сходство названий',
                default => 'Рекомендация сопоставления',
            },
            'reason' => match ($method) {
                'exact_name' => 'Нормализованное название товара полностью совпало с карточкой сайта.',
                'fuzzy_name' => sprintf(
                    'Название похоже на карточку сайта на %d%%; размеры и диаметры не противоречат друг другу.',
                    (int) round($confidence * 100),
                ),
                default => 'Карточка выбрана как лучший доступный кандидат по данным внешнего товара.',
            },
            'warning' => 'Это только рекомендация. Цена, остаток и название карточки не изменятся; будет сохранена постоянная ручная привязка.',
        ];
    }
}
