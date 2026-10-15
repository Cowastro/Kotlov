<?php

namespace App\Filament\Resources\CrmMailThreads\Actions;

use App\Models\CrmMailThread;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

final class ManageMailThreadNotesAction
{
    public static function make(): Action
    {
        return Action::make('manage_notes')
            ->label('Заметка и напоминание')
            ->icon('heroicon-o-bell-alert')
            ->color(fn (CrmMailThread $record): string => match (true) {
                $record->next_follow_up_at?->isPast() => 'danger',
                in_array($record->priority, ['high', 'urgent'], true) => 'warning',
                $record->next_follow_up_at !== null => 'info',
                default => 'gray',
            })
            ->visible(fn (CrmMailThread $record): bool => ! $record->trashed()
                && ! in_array($record->status, ['closed', 'spam'], true))
            ->fillForm(fn (CrmMailThread $record): array => [
                'priority' => $record->priority ?: 'normal',
                'manager_note' => $record->manager_note,
                'reminder_enabled' => $record->next_follow_up_at !== null,
                'next_follow_up_at' => $record->next_follow_up_at,
                'follow_up_note' => $record->follow_up_note,
            ])
            ->schema([
                Section::make('Внутренняя пометка')
                    ->description('Эти данные видят только сотрудники. Клиенту они не отправляются.')
                    ->schema([
                        Select::make('priority')
                            ->label('Важность переписки')
                            ->options(CrmMailThread::PRIORITIES)
                            ->required(),
                        Textarea::make('manager_note')
                            ->label('Заметка менеджера')
                            ->placeholder('Что важно помнить: договорённости, ограничения, следующий шаг…')
                            ->rows(4)
                            ->maxLength(3000),
                    ]),
                Section::make('Напоминание')
                    ->schema([
                        Toggle::make('reminder_enabled')
                            ->label('Напомнить вернуться к переписке')
                            ->live(),
                        DateTimePicker::make('next_follow_up_at')
                            ->label('Дата и время напоминания')
                            ->seconds(false)
                            ->native(false)
                            ->required(fn (Get $get): bool => (bool) $get('reminder_enabled'))
                            ->visible(fn (Get $get): bool => (bool) $get('reminder_enabled')),
                        Textarea::make('follow_up_note')
                            ->label('Что нужно сделать')
                            ->placeholder('Например: уточнить наличие, перезвонить, проверить ответ поставщика')
                            ->rows(3)
                            ->maxLength(3000)
                            ->required(fn (Get $get): bool => (bool) $get('reminder_enabled'))
                            ->visible(fn (Get $get): bool => (bool) $get('reminder_enabled')),
                    ]),
            ])
            ->modalHeading('Заметка и напоминание по переписке')
            ->modalDescription(fn (CrmMailThread $record): string => $record->subject ?: 'Без темы')
            ->modalSubmitActionLabel('Сохранить')
            ->action(function (CrmMailThread $record, array $data): void {
                $reminderEnabled = (bool) ($data['reminder_enabled'] ?? false);

                $record->forceFill([
                    'priority' => $data['priority'] ?? 'normal',
                    'manager_note' => filled($data['manager_note'] ?? null)
                        ? trim((string) $data['manager_note'])
                        : null,
                    'next_follow_up_at' => $reminderEnabled ? $data['next_follow_up_at'] : null,
                    'follow_up_note' => $reminderEnabled && filled($data['follow_up_note'] ?? null)
                        ? trim((string) $data['follow_up_note'])
                        : null,
                ])->save();

                Notification::make()
                    ->success()
                    ->title($reminderEnabled ? 'Заметка и напоминание сохранены' : 'Заметка сохранена')
                    ->body($reminderEnabled
                        ? 'Переписка появится во вкладке «Нужен контроль» в указанное время.'
                        : 'Напоминание для этой переписки выключено.')
                    ->send();
            });
    }
}
