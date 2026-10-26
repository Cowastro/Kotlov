<?php

namespace App\Filament\Resources\CrmMailThreads\Actions;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Models\CrmMailThread;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class AssignMailThreadAction
{
    public static function make(): Action
    {
        return Action::make('assign_responsible')
            ->label('Назначить ответственного')
            ->icon('heroicon-o-user-plus')
            ->color('info')
            ->extraAttributes(['class' => 'crm-thread-action crm-thread-action--assign'])
            ->tooltip('Назначить ответственного')
            ->visible(fn (CrmMailThread $record): bool => ! $record->trashed()
                && ! in_array($record->status, ['draft', 'closed', 'spam'], true))
            ->fillForm(fn (CrmMailThread $record): array => [
                'assigned_user_id' => $record->assigned_user_id,
            ])
            ->schema([
                Select::make('assigned_user_id')
                    ->label('Ответственный менеджер')
                    ->options(fn (CrmMailThread $record): array => self::availableManagers($record)
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required(),
            ])
            ->modalHeading('Назначить ответственного')
            ->modalDescription(fn (CrmMailThread $record): string => $record->subject ?: 'Без темы')
            ->modalSubmitActionLabel('Назначить')
            ->action(function (CrmMailThread $record, array $data): void {
                $assignee = self::availableManagers($record)
                    ->firstWhere('id', (int) $data['assigned_user_id']);

                if (! $assignee) {
                    throw ValidationException::withMessages([
                        'assigned_user_id' => 'Этот сотрудник не имеет доступа к почтовому ящику переписки.',
                    ]);
                }

                $changed = (int) $record->assigned_user_id !== (int) $assignee->id;
                $record->takeFor((int) $assignee->id);

                if ($changed) {
                    Notification::make()
                        ->title('Вам назначена переписка')
                        ->body(self::notificationBody($record))
                        ->icon('heroicon-o-envelope')
                        ->iconColor('info')
                        ->actions([
                            Action::make('open')
                                ->label('Открыть переписку')
                                ->url(CrmMailThreadResource::getUrl('view', ['record' => $record], panel: 'admin')),
                        ])
                        ->sendToDatabase($assignee);
                }

                Notification::make()
                    ->success()
                    ->title($changed ? 'Ответственный назначен' : 'Ответственный не изменился')
                    ->body($assignee->name)
                    ->send();
            });
    }

    /** @return Collection<int, User> */
    private static function availableManagers(CrmMailThread $record): Collection
    {
        return User::query()
            ->crmStaff()
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $record->mailbox === null
                || $record->mailbox->isVisibleTo($user));
    }

    private static function notificationBody(CrmMailThread $record): string
    {
        return implode(' · ', array_filter([
            $record->participant_name ?: $record->participant_email,
            $record->subject ?: 'Без темы',
        ]));
    }
}
