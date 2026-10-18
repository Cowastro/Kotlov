<?php

namespace App\Filament\Resources\TelephonyCalls\Pages;

use App\Filament\Resources\TelephonyCalls\TelephonyCallResource;
use App\Models\TelephonyCall;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;

class ViewTelephonyCall extends ViewRecord
{
    protected static string $resource = TelephonyCallResource::class;

    public function getTitle(): string
    {
        return 'Звонок: '.$this->record->contact_name;
    }

    public function getSubheading(): ?string
    {
        $startedAt = $this->record->started_at?->timezone('Europe/Minsk')->format('d.m.Y в H:i');

        return collect([$this->record->customer_phone, $startedAt])->filter()->join(' · ');
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('callClient')
                ->label('Позвонить')
                ->icon('heroicon-o-phone')
                ->color('gray')
                ->visible(fn (): bool => filled($this->record->customer_phone))
                ->url(fn (): string => 'tel:'.$this->record->customer_phone),

            Action::make('takeInWork')
                ->label('Взять в работу')
                ->icon('heroicon-o-hand-raised')
                ->color('warning')
                ->visible(fn (): bool => in_array($this->record->handling_status, ['unprocessed', 'callback_required'], true))
                ->action(function (): void {
                    $this->record->update([
                        'assigned_user_id' => $this->record->assigned_user_id ?: auth()->id(),
                        'handling_status' => 'in_progress',
                    ]);
                    $this->workflowSaved('Звонок взят в работу');
                }),

            Action::make('scheduleFollowUp')
                ->label('Перезвонить')
                ->icon('heroicon-o-clock')
                ->color('primary')
                ->visible(fn (): bool => ! in_array($this->record->handling_status, ['handled', 'irrelevant'], true))
                ->fillForm(fn (): array => [
                    'next_follow_up_at' => $this->record->next_follow_up_at ?: now()->addHour(),
                    'manager_note' => $this->record->manager_note,
                ])
                ->schema([
                    DateTimePicker::make('next_follow_up_at')
                        ->label('Когда перезвонить')
                        ->seconds(false)
                        ->timezone('Europe/Minsk')
                        ->required(),
                    Textarea::make('manager_note')
                        ->label('Что нужно уточнить')
                        ->placeholder('Причина и следующий шаг…')
                        ->rows(3)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'assigned_user_id' => $this->record->assigned_user_id ?: auth()->id(),
                        'handling_status' => 'callback_required',
                        'manager_outcome' => 'callback_later',
                        'next_follow_up_at' => $data['next_follow_up_at'],
                        'manager_note' => trim((string) $data['manager_note']),
                        'handled_at' => now(),
                        'handled_by' => auth()->id(),
                    ]);
                    $this->workflowSaved('Повторный звонок запланирован');
                }),

            Action::make('saveResult')
                ->label('Записать результат')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->fillForm(fn (): array => [
                    'manager_outcome' => $this->record->manager_outcome,
                    'manager_note' => $this->record->manager_note,
                    'next_follow_up_at' => $this->record->next_follow_up_at,
                    'order_id' => $this->record->order_id,
                ])
                ->schema([
                    Select::make('manager_outcome')
                        ->label('Итог разговора')
                        ->options(TelephonyCall::MANAGER_OUTCOMES)
                        ->native(false)
                        ->live()
                        ->required(),
                    Textarea::make('manager_note')
                        ->label('О чём говорили и что делать дальше')
                        ->placeholder('Потребность клиента, договорённости, возражения, следующий шаг…')
                        ->rows(4)
                        ->maxLength(3000)
                        ->required(),
                    DateTimePicker::make('next_follow_up_at')
                        ->label('Когда связаться снова')
                        ->seconds(false)
                        ->timezone('Europe/Minsk')
                        ->required(fn (Get $get): bool => in_array($get('manager_outcome'), ['callback_later', 'no_answer'], true))
                        ->visible(fn (Get $get): bool => in_array($get('manager_outcome'), ['callback_later', 'no_answer'], true)),
                    Select::make('order_id')
                        ->label('Связанный заказ')
                        ->relationship('order', 'number')
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => $get('manager_outcome') === 'order_created')
                        ->required(fn (Get $get): bool => $get('manager_outcome') === 'order_created'),
                ])
                ->modalHeading('Результат разговора')
                ->modalSubmitActionLabel('Сохранить результат')
                ->action(function (array $data): void {
                    $outcome = $data['manager_outcome'];
                    $this->record->update([
                        'assigned_user_id' => $this->record->assigned_user_id ?: auth()->id(),
                        'handling_status' => TelephonyCallResource::handlingStatusForOutcome($outcome),
                        'manager_outcome' => $outcome,
                        'manager_note' => trim((string) $data['manager_note']),
                        'order_id' => $data['order_id'] ?? $this->record->order_id,
                        'next_follow_up_at' => in_array($outcome, ['callback_later', 'no_answer'], true)
                            ? ($data['next_follow_up_at'] ?? null)
                            : null,
                        'handled_at' => now(),
                        'handled_by' => auth()->id(),
                    ]);
                    $this->workflowSaved('Результат разговора сохранён');
                }),

            Action::make('manage')
                ->label('Статус и ответственный')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->fillForm(fn (): array => [
                    'handling_status' => $this->record->handling_status,
                    'assigned_user_id' => $this->record->assigned_user_id,
                    'next_follow_up_at' => $this->record->next_follow_up_at,
                    'manager_note' => $this->record->manager_note,
                ])
                ->schema([
                    Select::make('handling_status')
                        ->label('Статус работы')
                        ->options(TelephonyCall::HANDLING_STATUSES)
                        ->native(false)
                        ->required(),
                    Select::make('assigned_user_id')
                        ->label('Ответственный')
                        ->options(fn (): array => User::query()
                            ->whereIn('role', ['admin', 'manager', 'sales_manager'])
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->native(false),
                    DateTimePicker::make('next_follow_up_at')
                        ->label('Следующий контакт')
                        ->seconds(false)
                        ->timezone('Europe/Minsk'),
                    Textarea::make('manager_note')
                        ->label('Комментарий / следующий шаг')
                        ->rows(4)
                        ->maxLength(3000),
                ])
                ->action(function (array $data): void {
                    $finished = in_array($data['handling_status'], ['handled', 'irrelevant'], true);
                    $this->record->update([
                        'handling_status' => $data['handling_status'],
                        'assigned_user_id' => $data['assigned_user_id'] ?? null,
                        'next_follow_up_at' => $finished ? null : ($data['next_follow_up_at'] ?? null),
                        'manager_note' => filled($data['manager_note'] ?? null) ? trim((string) $data['manager_note']) : null,
                        'handled_at' => $finished ? ($this->record->handled_at ?: now()) : null,
                        'handled_by' => $finished ? ($this->record->handled_by ?: auth()->id()) : null,
                    ]);
                    $this->workflowSaved('Статус и ответственный обновлены');
                }),

            ActionGroup::make([
                Action::make('irrelevant')
                    ->label('Отметить неактуальным')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (): bool => $this->record->handling_status !== 'irrelevant')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('manager_note')->label('Причина')->rows(3)->required(),
                    ])
                    ->action(function (array $data): void {
                        $this->record->update([
                            'handling_status' => 'irrelevant',
                            'manager_note' => trim((string) $data['manager_note']),
                            'assigned_user_id' => $this->record->assigned_user_id ?: auth()->id(),
                            'next_follow_up_at' => null,
                            'handled_at' => now(),
                            'handled_by' => auth()->id(),
                        ]);
                        $this->workflowSaved('Звонок отмечен неактуальным');
                    }),
                EditAction::make()->label('Изменить исходные данные'),
            ]),
        ];
    }

    private function workflowSaved(string $message): void
    {
        $this->record->refresh()->load([
            'assignedUser', 'matchedUser', 'crmCounterparty', 'crmContact', 'handledByUser', 'order', 'contactRequest',
        ]);

        Notification::make()->title($message)->success()->send();
    }
}
