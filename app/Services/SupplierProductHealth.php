<?php

namespace App\Services;

use App\Models\SupplierProduct;
use Carbon\CarbonInterface;

class SupplierProductHealth
{
    /** @return array{key:string,label:string,description:string,color:string} */
    public function describe(SupplierProduct $product, ?CarbonInterface $now = null): array
    {
        $now ??= now();

        return match (true) {
            $product->price_byn === null || (float) $product->price_byn <= 0 => $this->result(
                'missing_price',
                'Без цены',
                'Передайте положительную цену в следующей синхронизации.',
                'danger',
            ),
            $product->product_id === null => $this->result(
                'unlinked',
                'Не привязан',
                'Карточка KOTLOV ещё не сопоставлена.',
                'warning',
            ),
            $product->last_synced_at === null => $this->result(
                'never_synced',
                'Нет синхронизации',
                'Нет отметки времени последнего обновления.',
                'danger',
            ),
            $product->last_synced_at->lt($now->copy()->subHours(SupplierProduct::STALE_AFTER_HOURS)) => $this->result(
                'stale',
                'Устарел',
                'Данные не обновлялись более '.SupplierProduct::STALE_AFTER_HOURS.' часов.',
                'danger',
            ),
            ! $product->in_stock && (int) $product->stock_quantity <= 0 => $this->result(
                'out_of_stock',
                'Нет в наличии',
                'Цена и привязка готовы, но остаток равен нулю.',
                'gray',
            ),
            default => $this->result(
                'healthy',
                'Готов',
                'Цена, остаток, привязка и актуальность в порядке.',
                'success',
            ),
        };
    }

    /** @return array{key:string,label:string,description:string,color:string} */
    private function result(string $key, string $label, string $description, string $color): array
    {
        return compact('key', 'label', 'description', 'color');
    }
}
