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

    public function moscowStockNote(OrderItem $item): ?string
    {
        $stock = $item->product?->ecokaminMoscowStock;

        if ($stock?->available_quantity === null) {
            return null;
        }

        $date = ($stock->as_of_date ?? $stock->last_synced_at)?->format('d.m.Y');
        $quantity = $stock->formattedAvailableQuantity();
        $datedQuantity = $quantity.($date ? ' на '.$date : '');

        return $stock->isFresh()
            ? 'ЭкоКамин, Москва — '.$datedQuantity
            : 'ЭкоКамин, Москва — наличие уточняется (последний остаток: '.$datedQuantity.')';
    }

    public function ownStockShortage(OrderItem $item): ?string
    {
        if ($item->fulfillment_route !== 'own_stock' || ! $item->fulfillment_confirmed_at
            || $item->fulfillment_availability_status !== 'available') {
            return null;
        }

        $current = $this->current($item);
        $quantity = $current['stock_quantity'] ?? null;
        if (($current['stock_source'] ?? null) !== 'onec' || $quantity === null
            || (float) $quantity >= (float) $item->quantity) {
            return null;
        }

        $free = rtrim(rtrim(number_format((float) $quantity, 3, '.', ' '), '0'), '.');

        return 'На нашем складе свободно '.$free.' шт. из '.$item->quantity.' шт. по заказу. Проверьте резерв и источник исполнения.';
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
