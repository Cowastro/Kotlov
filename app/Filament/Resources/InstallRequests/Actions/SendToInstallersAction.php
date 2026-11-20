<?php

namespace App\Filament\Resources\InstallRequests\Actions;

use App\Models\InstallRequest;
use App\Services\InstallerLeads\InstallerLeadDispatcher;
use App\Services\InstallerLeads\InstallerLeadMatcher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/** The manager's button: e-mail the request to the installers of our base (those who have not got it yet). */
class SendToInstallersAction
{
    public static function make(): Action
    {
        return Action::make('sendToInstallers')
            ->label(fn (InstallRequest $record): string => $record->leads()->whereNotNull('notified_at')->exists() ? 'Монтажникам ещё' : 'Монтажникам')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->visible(fn (InstallRequest $record): bool => ! in_array($record->status, ['done', 'cancelled'], true))
            ->requiresConfirmation()
            ->modalHeading('Разослать заявку монтажникам?')
            ->modalDescription(function (InstallRequest $record): string {
                $count = self::newRecipients($record);
                $takers = (int) config('installer_leads.max_takers', 3);

                return $count === 0
                    ? 'Новых получателей нет: все монтажники с почтой уже получили эту заявку или отказались от рассылки.'
                    : "Письмо получат монтажников: {$count}. В письме нет контактов клиента — их увидят первые {$takers}, кто нажмёт «Взять заявку».";
            })
            ->modalSubmitActionLabel('Разослать')
            ->action(function (InstallRequest $record): void {
                $result = app(InstallerLeadDispatcher::class)->dispatch($record, again: true);

                Notification::make()
                    ->title($result['sent'] > 0 ? 'Заявка разослана монтажникам: '.$result['sent'] : 'Новых получателей нет')
                    ->body($result['failed'] > 0 ? 'Не удалось отправить: '.$result['failed'].'. Подробности в логе.' : null)
                    ->color($result['failed'] > 0 ? 'warning' : 'success')
                    ->send();
            });
    }

    private static function newRecipients(InstallRequest $record): int
    {
        $notified = $record->leads()->whereNotNull('notified_at')->pluck('installer_profile_id');

        return app(InstallerLeadMatcher::class)->candidates($record)->whereNotIn('id', $notified)->count();
    }
}
