<?php

namespace App\Filament\Resources\OneCAgentTasks\Actions;

use App\Models\OneCAgentToken;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

/** A new key for the agent on the 1C server: the old keys stop working, the new one is shown once. */
class IssueAgentKeyAction
{
    public static function make(): Action
    {
        return Action::make('agentKey')
            ->label('Ключ агента 1С')
            ->icon('heroicon-o-key')
            ->color('gray')
            ->visible(fn (): bool => (bool) auth()->user()?->isAdmin())
            ->requiresConfirmation()
            ->modalHeading('Выпустить новый ключ агента?')
            ->modalDescription('Прежние ключи перестанут работать. Новый ключ показывается один раз: сразу вставьте его в настройку агента на сервере 1С (setup.ps1).')
            ->action(function (): void {
                OneCAgentToken::query()->whereNull('revoked_at')->update(['revoked_at' => now()]);
                [, $plain] = OneCAgentToken::issue('Агент сервера 1С', auth()->id());
                Notification::make()->success()->persistent()->title('Ключ агента (показывается один раз)')
                    ->body(new HtmlString('<code style="user-select:all;word-break:break-all">'.e($plain).'</code>'))->send();
            });
    }
}
