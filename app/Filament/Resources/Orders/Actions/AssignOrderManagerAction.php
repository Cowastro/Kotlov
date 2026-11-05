<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Models\Order;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** A manager takes the order over or hands it to a colleague; the change is kept in the order comments. */
class AssignOrderManagerAction
{
    public static function make(): Action
    {
        return Action::make('assignManager')
            ->label(fn (Order $record): string => $record->manager_id || filled($record->assigned_to) ? 'Сменить менеджера' : 'Назначить менеджера')
            ->icon(Heroicon::OutlinedUserCircle)
            ->color('gray')
            ->modalHeading('Ответственный менеджер заказа')
            ->modalWidth('md')
            ->fillForm(fn (Order $record): array => ['manager_id' => $record->manager_id ?: Order::managerIdFor($record->assigned_to)])
            ->schema([
                Select::make('manager_id')->label('Менеджер')
                    ->options(fn (): array => static::options())
                    ->searchable()->required(),
            ])
            ->action(function (Order $record, array $data): void {
                $manager = User::query()->findOrFail($data['manager_id']);
                $before = $record->manager?->name ?: ltrim((string) $record->assigned_to, '@');
                $record->forceFill([
                    'manager_id' => $manager->id,
                    'assigned_to' => '@'.($manager->telegram_username ?: $manager->name),
                ])->save();
                $author = auth()->user();
                $record->managerComments()->create([
                    'user_id' => $author?->id,
                    'author_name' => $author?->name ?? 'система',
                    'body' => 'Ответственный менеджер: '.($before !== '' ? $before.' → ' : '').$manager->name,
                ]);
                Notification::make()->title('Менеджер заказа: '.$manager->name)->success()->send();
            });
    }

    /** @return array<int, string> */
    public static function options(): array
    {
        return User::query()->whereIn('role', ['admin', 'manager', 'sales_manager'])
            ->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }
}
