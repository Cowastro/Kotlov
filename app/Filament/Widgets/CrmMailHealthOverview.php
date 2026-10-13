<?php

namespace App\Filament\Widgets;

use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CrmMailHealthOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Проверка почты';

    protected ?string $description = 'Состояние HOSTER, автоматической загрузки входящих писем и серверного времени.';

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $mailboxes = CrmMailbox::query()->where('is_enabled', true)->get();
        $lastAttempt = $mailboxes->sortByDesc('last_sync_at')->first()?->last_sync_at;
        $lastSuccess = $mailboxes->sortByDesc('last_success_at')->first()?->last_success_at;
        $lastIncoming = CrmMailMessage::query()->where('direction', 'incoming')->max('received_at');
        $hasMailboxes = $mailboxes->isNotEmpty();
        $hasError = $mailboxes->contains(fn (CrmMailbox $mailbox): bool => filled($mailbox->last_error));
        $isStale = ! $lastAttempt || $lastAttempt->lt(now()->subMinutes(10));
        $minskNow = now()->timezone('Europe/Minsk');

        return [
            Stat::make('Состояние почты', ! $hasMailboxes ? 'Не настроена' : ($hasError ? 'Есть ошибка' : 'Подключена'))
                ->description($hasMailboxes
                    ? $mailboxes->count().' активн. ящик(а) · проверка каждые 5 минут'
                    : 'Нет активных почтовых ящиков')
                ->descriptionIcon(! $hasMailboxes || $hasError ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->color(! $hasMailboxes || $hasError ? 'danger' : 'success'),
            Stat::make('Последняя проверка', $lastAttempt?->timezone('Europe/Minsk')->format('d.m.Y H:i:s') ?? 'Ещё не запускалась')
                ->description($isStale ? 'Автопроверка давно не запускалась' : 'Планировщик работает')
                ->descriptionIcon($isStale ? 'heroicon-o-clock' : 'heroicon-o-arrow-path')
                ->color($isStale ? 'warning' : 'success'),
            Stat::make('Последнее входящее', $lastIncoming
                ? Carbon::parse($lastIncoming)->timezone('Europe/Minsk')->format('d.m.Y H:i:s')
                : 'Писем ещё нет')
                ->description($lastSuccess
                    ? 'Успешная загрузка: '.$lastSuccess->timezone('Europe/Minsk')->format('d.m.Y H:i:s')
                    : 'Успешных загрузок ещё не было')
                ->descriptionIcon('heroicon-o-envelope')
                ->color('info'),
            Stat::make('Время системы', $minskNow->format('d.m.Y H:i:s'))
                ->description('Минск · UTC '.$minskNow->copy()->utc()->format('H:i:s'))
                ->descriptionIcon('heroicon-o-globe-alt')
                ->color('gray'),
        ];
    }
}
