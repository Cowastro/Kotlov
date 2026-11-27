<?php

namespace App\Support\Shipping;

use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Builder;

class VamExpressStatus
{
    private const ATTENTION_PREFIXES = [
        'частичный отказ' => 'warning', 'перенос' => 'warning',
        'отказ' => 'cancelled', 'отмена' => 'cancelled', 'отменена' => 'cancelled',
        'обмен' => 'exchange', 'дост. от кл.' => 'return', 'возврат' => 'return',
    ];

    public static function group(?string $status): string
    {
        $status = mb_strtolower(trim($status ?? ''));

        foreach (self::ATTENTION_PREFIXES as $prefix => $group) {
            if (str_starts_with($status, $prefix)) {
                return $group;
            }
        }

        return match (true) {
            in_array($status, ['обработана', 'доставлена', 'завершено'], true) => 'delivered',
            $status === 'оплачено' => 'paid',
            $status === 'не обработана' => 'new',
            $status === 'на складе' => 'warehouse',
            $status === 'в пути' => 'transit',
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
            'work' => $query->whereIn('carrier_status', ['Не обработана', 'На складе']),
            'transit' => $query->where('carrier_status', 'В пути'),
            'delivered' => $query->whereIn('carrier_status', ['Обработана', 'Доставлена', 'Завершено']),
            'attention' => $query->where(function (Builder $q): void {
                foreach (array_keys(self::ATTENTION_PREFIXES) as $prefix) {
                    foreach ([$prefix, mb_strtoupper(mb_substr($prefix, 0, 1)).mb_substr($prefix, 1), mb_strtoupper($prefix)] as $case) {
                        $q->orWhere('carrier_status', 'like', $case.'%');
                    }
                }
            }),
            default => $query,
        };
    }
}
