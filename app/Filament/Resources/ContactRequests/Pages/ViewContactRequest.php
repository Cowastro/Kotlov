<?php

namespace App\Filament\Resources\ContactRequests\Pages;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Models\ContactRequest;
use App\Models\LeadChannel;
use App\Models\User;
use App\Services\LeadInbox;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

class ViewContactRequest extends ViewRecord
{
    protected static string $resource = ContactRequestResource::class;

    #[Locked]
    public int $historyPage = 1;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($url = ContactRequestResource::siteChatUrl($this->record)) {
            $this->redirect($url);
        }
    }

    public function olderMessages(): void
    {
        $this->historyPage++;
    }

    public function newerMessages(): void
    {
        $this->historyPage = max(1, $this->historyPage - 1);
    }

    public function getTitle(): string
    {
        return 'Обращение: '.$this->record->name;
    }

    public function getSubheading(): ?string
    {
        return 'Поступило '.$this->record->created_at?->timezone('Europe/Minsk')->format('d.m.Y в H:i');
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label('Ответить клиенту')
                ->icon('heroicon-o-paper-airplane')
                ->authorize(fn (): bool => ContactRequestResource::canEdit($this->record))
                ->visible(fn (): bool => app(LeadInbox::class)->replyChannel($this->record) !== null)
                ->modalDescription(function (): ?string {
                    $channel = app(LeadInbox::class)->replyChannel($this->record);

                    return $channel ? 'Ответ уйдёт в исходный чат через '.LeadChannel::LABELS[$channel->provider].'.' : null;
                })
                ->modalSubmitActionLabel('Отправить')
                ->fillForm(fn (): array => ['send_key' => (string) Str::uuid()])
                ->schema([
                    Textarea::make('body')->label('Сообщение')->rows(5)->required()->maxLength(1000)->trim(),
                    Hidden::make('send_key')->required()->rules(['uuid']),
                ])
                ->action(function (array $data, LeadInbox $inbox, Action $action): void {
                    ContactRequestResource::authorizeEdit($this->record);
                    $message = $inbox->reply($this->record, auth()->id(), $data['body'], $data['send_key']);
                    if (! $message) {
                        Notification::make()->title('Канал ответа отключён')->danger()->send();
                        $action->halt();
                    }

                    $this->historyPage = 1;
                    match ($message->state) {
                        'sent' => Notification::make()->title('Канал принял ответ')->success()->send(),
                        'failed' => Notification::make()->title('Канал отклонил ответ')->danger()->send(),
                        default => Notification::make()->title('Отправка не подтверждена')->warning()->send(),
                    };
                }),

            Action::make('callClient')
                ->label('Позвонить')
                ->icon('heroicon-o-phone')
                ->color('gray')
                ->visible(fn (): bool => filled($this->record->phone))
                ->url(fn (): string => 'tel:'.$this->record->phone),

            Action::make('takeInWork')
                ->label('Взять в работу')
                ->icon('heroicon-o-hand-raised')
                ->color('warning')
                ->visible(fn (): bool => in_array($this->record->status, ['new', 'read'], true))
                ->action(function (): void {
                    $this->record->update([
                        'status' => 'in_progress',
                        'responsible_user_id' => $this->record->responsible_user_id ?: auth()->id(),
                    ]);
                    $this->workflowSaved('Обращение взято в работу');
                }),

            Action::make('scheduleFollowUp')
                ->label('Напомнить')
                ->icon('heroicon-o-clock')
                ->color('primary')
                ->visible(fn (): bool => ! in_array($this->record->status, ['done', 'cancelled'], true))
                ->fillForm(fn (): array => [
                    'next_follow_up_at' => $this->record->next_follow_up_at ?: now()->addHour(),
                    'admin_notes' => $this->record->admin_notes,
                ])
                ->schema([
                    DateTimePicker::make('next_follow_up_at')
                        ->label('Когда напомнить')
                        ->seconds(false)
                        ->timezone('Europe/Minsk')
                        ->required(),
                    Textarea::make('admin_notes')
                        ->label('Что нужно сделать')
                        ->rows(3)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => 'waiting',
                        'responsible_user_id' => $this->record->responsible_user_id ?: auth()->id(),
                        'next_follow_up_at' => $data['next_follow_up_at'],
                        'admin_notes' => trim((string) $data['admin_notes']),
                    ]);
                    $this->workflowSaved('Напоминание сохранено');
                }),

            Action::make('complete')
                ->label('Завершить')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => ! in_array($this->record->status, ['done', 'cancelled'], true))
                ->fillForm(fn (): array => ['admin_notes' => $this->record->admin_notes])
                ->schema([
                    Textarea::make('admin_notes')
                        ->label('Итог обращения')
                        ->rows(3)
                        ->helperText('Коротко зафиксируйте результат, чтобы история не потерялась.'),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => 'done',
                        'responsible_user_id' => $this->record->responsible_user_id ?: auth()->id(),
                        'next_follow_up_at' => null,
                        'admin_notes' => filled($data['admin_notes'] ?? null) ? trim((string) $data['admin_notes']) : $this->record->admin_notes,
                    ]);
                    $this->workflowSaved('Обращение завершено');
                }),

            Action::make('manage')
                ->label('Статус и ответственный')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->fillForm(fn (): array => [
                    'status' => $this->record->status,
                    'responsible_user_id' => $this->record->responsible_user_id,
                    'next_follow_up_at' => $this->record->next_follow_up_at,
                    'admin_notes' => $this->record->admin_notes,
                ])
                ->schema([
                    Select::make('status')->label('Статус')->options(ContactRequest::$statuses)->required()->native(false),
                    Select::make('responsible_user_id')
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
                    DateTimePicker::make('next_follow_up_at')->label('Следующий контакт')->seconds(false)->timezone('Europe/Minsk'),
                    Textarea::make('admin_notes')->label('Комментарий / следующий шаг')->rows(4),
                ])
                ->action(function (array $data): void {
                    $finished = in_array($data['status'], ['done', 'cancelled'], true);
                    $this->record->update([
                        'status' => $data['status'],
                        'responsible_user_id' => $data['responsible_user_id'] ?? null,
                        'next_follow_up_at' => $finished ? null : ($data['next_follow_up_at'] ?? null),
                        'admin_notes' => filled($data['admin_notes'] ?? null) ? trim((string) $data['admin_notes']) : null,
                    ]);
                    $this->workflowSaved('Изменения сохранены');
                }),

            ActionGroup::make([
                Action::make('cancel')
                    ->label('Отметить неактуальным')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (): bool => $this->record->status !== 'cancelled')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('admin_notes')->label('Причина')->rows(3)->required(),
                    ])
                    ->action(function (array $data): void {
                        $this->record->update([
                            'status' => 'cancelled',
                            'responsible_user_id' => $this->record->responsible_user_id ?: auth()->id(),
                            'next_follow_up_at' => null,
                            'admin_notes' => trim((string) $data['admin_notes']),
                        ]);
                        $this->workflowSaved('Обращение отмечено неактуальным');
                    }),
                EditAction::make()->label('Изменить исходные данные'),
            ]),
        ];
    }

    private function workflowSaved(string $message): void
    {
        $this->record->refresh()->load('responsibleUser');

        Notification::make()->title($message)->success()->send();
    }
}
