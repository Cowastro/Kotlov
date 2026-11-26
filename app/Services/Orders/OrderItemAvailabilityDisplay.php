<?php

namespace App\Services\Orders;

use App\Models\OrderItem;
use Illuminate\Support\Carbon;

/** Present current stock separately from order history and the manager's fulfillment decision. */
class OrderItemAvailabilityDisplay
{
    private function current(OrderItem $item): array
    {
        return $item->currentAvailabilityForDisplay() ?: app(OrderItemSupplyContextResolver::class)->resolveCurrent($item);
    }

    public function label(OrderItem $item): string
    {
        $current = $this->current($item);
        if (($current['stock_source'] ?? null) === 'onec' && ($current['stock_quantity'] ?? null) !== null) {
            $quantity = rtrim(rtrim(number_format((float) $current['stock_quantity'], 3, '.', ' '), '0'), '.');

            return 'Свободно: '.$quantity.' шт. · 1С, склад Основной';
        }

        return $current['stock_label'] ?? 'Актуальное наличие неизвестно';
    }

    public function details(OrderItem $item): string
    {
        $current = $this->current($item);
        $at = $current['source_updated_at'] ?? $current['stock_updated_at'] ?? null;
        $decision = $item->fulfillment_confirmed_at
            ? 'Исполнение: '.($item->fulfillmentAvailabilityLabel() ?: 'подтверждено').' · '
                .($item->fulfillmentConfirmedBy?->name ?: 'система').' · '.$item->fulfillment_confirmed_at->timezone('Europe/Minsk')->format('d.m.Y H:i')
            : 'Исполнение менеджером ещё не подтверждено';

        return collect([
            $at ? 'Данные склада: '.Carbon::parse($at)->timezone('Europe/Minsk')->format('d.m.Y H:i') : null,
            $decision,
        ])->filter()->implode(' · ');
    }

    public function color(OrderItem $item): string
    {
        $current = $this->current($item);
        if (($current['is_available'] ?? null) === true) {
            return isset($current['stock_quantity']) && (float) $current['stock_quantity'] < (float) $item->quantity ? 'warning' : 'success';
        }

        return ($current['is_available'] ?? null) === false ? 'danger' : 'warning';
    }

    public function history(OrderItem $item): ?string
    {
        return $item->supply_captured_at ? 'При оформлении заказа: '.$item->supplyContext()['stock_label'] : null;
    }
}
