<?php

namespace App\Support\Shipping;

use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Builder;

class VamExpressStatus
{
    public static function group(?string $status): string
    {
        $status = mb_strtolower(trim($status ?? ''));

        return match (true) {
            in_array($status, ['доставлена', 'завершено'], true) => 'delivered',
            $status === 'оплачено' => 'paid',
            $status === 'не обработана' => 'new',
            $status === 'обработана' => 'processed',
            $status === 'на складе' => 'warehouse',
            $status === 'в пути' => 'transit',
            str_starts_with($status, 'частичный отказ'), str_starts_with($status, 'перенос') => 'warning',
            str_starts_with($status, 'отказ'), str_starts_with($status, 'отмена'), $status === 'отменена' => 'cancelled',
            str_starts_with($status, 'обмен') => 'exchange',
            str_starts_with($status, 'дост. от кл.') => 'return',
            default => 'unknown',
        };
    }

    public static function color(?string $status): array
    {
        return match (self::group($status)) {
            'new', 'warning' => Color::Amber,
            'processed' => Color::Sky,
            'warehouse' => Color::Teal,
            'transit', 'exchange' => Color::Violet,
            'delivered', 'paid' => Color::Green,
            'cancelled' => Color::Red,
            'return' => Color::Blue,
            default => Color::Gray,
        };
    }

    public static function icon(?string $status): string
    {
        return 'heroicon-o-'.match (self::group($status)) {
            'new' => 'clock', 'processed' => 'clipboard-document-check', 'warehouse' => 'building-storefront',
            'transit' => 'truck', 'delivered' => 'check-circle', 'paid' => 'banknotes', 'cancelled' => 'x-circle',
            'warning' => 'exclamation-triangle', 'exchange' => 'arrows-right-left', 'return' => 'arrow-uturn-left',
            default => 'question-mark-circle',
        };
    }

    public static function scope(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'work' => $query->whereIn('carrier_status', ['Не обработана', 'Обработана', 'На складе']),
            'transit' => $query->where('carrier_status', 'В пути'),
            'delivered' => $query->whereIn('carrier_status', ['Доставлена', 'Завершено']),
            'attention' => $query->where(fn (Builder $q) => $q->where('carrier_status', 'like', 'Перенос%')
                ->orWhere('carrier_status', 'like', 'Отказ%')->orWhere('carrier_status', 'like', 'Частичный отказ%')),
            default => $query,
        };
    }
}
