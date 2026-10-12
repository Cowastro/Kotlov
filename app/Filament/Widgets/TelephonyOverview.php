<?php

namespace App\Filament\Widgets;

use App\Models\TelephonyCall;
use App\Models\TelephonyConnection;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TelephonyOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '20s';

    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $connection = TelephonyConnection::query()->where('provider', 'a1')->first();
        $inbound = TelephonyCall::query()->where('direction', 'inbound')->where('started_at', '>=', $today)->count();
        $answered = TelephonyCall::query()->where('direction', 'inbound')->where('started_at', '>=', $today)->where('status', 'answered')->count();
        $attention = TelephonyCall::query()->needsAttention()->count();

        return [
            Stat::make('Нужна реакция', $attention)->description('Новые и ожидающие перезвона')
                ->descriptionIcon('heroicon-o-exclamation-triangle')->color($attention ? 'danger' : 'success'),
            Stat::make('Входящие сегодня', $inbound)->description('Отвечено: '.$answered)
                ->descriptionIcon('heroicon-o-phone-arrow-down-left')->color('info'),
            Stat::make('Приём звонков', $inbound ? round($answered / $inbound * 100).'%' : '—')
                ->description('Доля отвеченных входящих')->descriptionIcon('heroicon-o-chart-bar')->color($inbound && $answered < $inbound ? 'warning' : 'success'),
            Stat::make('Связь с A1', $connection?->is_enabled ? 'Включена' : 'Не настроена')
                ->description($connection?->webhook_last_received_at
                    ? 'Webhook '.$connection->webhook_last_received_at->timezone('Europe/Minsk')->format('d.m.Y H:i:s')
                    : 'Событий ещё не получали')
                ->descriptionIcon('heroicon-o-signal')->color($connection?->last_error ? 'danger' : ($connection?->is_enabled ? 'success' : 'gray')),
        ];
    }
}
