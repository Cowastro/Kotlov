<?php

namespace App\Support\Shipping;

use Illuminate\Database\Eloquent\Builder;

class EvropochtaStatus
{
    public const CANCELLED = ['Отменено', 'Отменена', 'Заявка отменена', 'отменено', 'отменена', 'заявка отменена', 'ОТМЕНЕНО', 'ОТМЕНЕНА', 'ЗАЯВКА ОТМЕНЕНА'];

    public static function scope(Builder $query, string $tab): Builder
    {
        if ($tab === 'work') return $query->whereNull('archived_at')->whereNotIn('status', ['cancelled', 'delivered'])
            ->where(fn (Builder $q): Builder => $q->whereNull('carrier_status_text')->orWhereNotIn('carrier_status_text', self::CANCELLED));
        if ($tab === 'closed') return $query->where(fn (Builder $q): Builder => $q->whereNotNull('archived_at')
            ->orWhereIn('status', ['cancelled', 'delivered'])->orWhereIn('carrier_status_text', self::CANCELLED));
        return $query;
    }
}
