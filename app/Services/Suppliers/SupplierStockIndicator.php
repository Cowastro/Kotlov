<?php

namespace App\Services\Suppliers;

class SupplierStockIndicator
{
    public static function fromOffer(array $offer): array
    {
        if (! ($offer['is_fresh'] ?? false) || (($offer['stock_quantity'] ?? null) === null && ! ($offer['in_stock'] ?? false))) {
            return ['state' => 'unknown', 'level' => 0, 'label' => 'Уточнить', 'title' => 'Актуальный остаток не подтверждён. Откройте поставщиков и склады.'];
        }
        $quantity = $offer['stock_quantity'] ?? null;
        if ($quantity === null) {
            return ['state' => 'available', 'level' => 0, 'label' => 'Есть', 'title' => 'Наличие подтверждено, точное количество не передано.'];
        }
        $quantity = max(0, (float) $quantity);
        $unit = $offer['order_rules']['unit'] ?? 'шт.';
        $label = rtrim(rtrim(number_format($quantity, 3, ',', ' '), '0'), ',').' '.$unit;

        return ['state' => $quantity > 0 ? 'known' : 'empty', 'level' => $quantity <= 0 ? 0 : ($quantity <= 5 ? 1 : ($quantity <= 20 ? 2 : 3)), 'label' => $label, 'title' => $label.'. Шкала: до 5, до 20, больше 20. Подробности — в поставщиках и складах.'];
    }
}
