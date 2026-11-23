<?php

namespace App\Filament\Actions;

use App\Services\Suppliers\EurotermPortalCredentials;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class EurotermPortalSettingsAction
{
    public static function make(): Action
    {
        return Action::make('eurotermPortal')
            ->label('Вход Евротерм')
            ->icon('heroicon-o-key')
            ->visible(fn (): bool => auth()->user()?->isAdmin() === true)
            ->authorize(fn (): bool => auth()->user()?->isAdmin() === true)
            ->modalHeading('Настроить вход в Евротерм')
            ->modalDescription('Введите данные от euroterm.by. Пароль хранится зашифрованным. После сохранения проверка и обновление начнутся в течение 5 минут; затем — каждые 8 часов.')
            ->modalSubmitActionLabel('Сохранить и обновить')
            ->fillForm(function (): array {
                abort_unless(auth()->user()?->isAdmin(), 403);

                return ['username' => app(EurotermPortalCredentials::class)->get()['username'] ?? '', 'password' => ''];
            })
            ->schema([
                TextInput::make('username')->label('Логин на euroterm.by')->required()->maxLength(255),
                TextInput::make('password')->label('Пароль')->password()->autocomplete('new-password')
                    ->required(fn (): bool => blank(app(EurotermPortalCredentials::class)->get()['password'] ?? null))
                    ->helperText('При повторной настройке оставьте пустым, чтобы сохранить прежний пароль.'),
            ])
            ->action(function (array $data): void {
                abort_unless(auth()->user()?->isAdmin(), 403);
                app(EurotermPortalCredentials::class)->save($data['username'], $data['password'] ?? null);
                Notification::make()->success()->title('Данные входа сохранены')
                    ->body('Проверка и обновление запустятся в течение 5 минут. Результат появится в центре синхронизаций.')->send();
            });
    }
}
