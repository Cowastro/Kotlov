<?php

namespace App\Filament\Resources\CrmMailThreads;

use App\Filament\Resources\CrmMailThreads\Pages\EditCrmMailThread;
use App\Filament\Resources\CrmMailThreads\Pages\ListCrmMailThreads;
use App\Filament\Resources\CrmMailThreads\Pages\ViewCrmMailThread;
use App\Models\CrmContact;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CrmMailThreadResource extends Resource
{
    protected static ?string $model = CrmMailThread::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Входящие письма';

    protected static ?string $modelLabel = 'переписка';

    protected static ?string $pluralModelLabel = 'Почта и лиды';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'Почта и лиды';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->needsAttention()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['mailbox', 'assignedUser', 'counterparty', 'contact']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Обработка лида')->columns(2)->schema([
                Select::make('status')->label('Статус')->options(CrmMailThread::STATUSES)->required(),
                Select::make('priority')->label('Приоритет')->options(CrmMailThread::PRIORITIES)->required(),
                Select::make('assigned_user_id')->label('Ответственный')
                    ->options(fn (): array => User::query()->whereIn('role', ['admin', 'manager', 'sales_manager'])
                        ->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->nullable(),
                Select::make('crm_counterparty_id')->label('Контрагент CRM')
                    ->relationship('counterparty', 'name')->searchable()->preload()->nullable(),
                Select::make('crm_contact_id')->label('Контактное лицо')
                    ->options(fn (): array => CrmContact::query()->with('counterparty')->orderBy('full_name')->get()
                        ->mapWithKeys(fn (CrmContact $contact): array => [
                            $contact->id => $contact->full_name.($contact->counterparty ? ' — '.$contact->counterparty->name : ''),
                        ])->all())
                    ->searchable()->nullable(),
                Textarea::make('manager_note')->label('Итог / следующий шаг')->rows(4)->maxLength(3000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Лид')->columns(4)->schema([
                TextEntry::make('participant_name')->label('Имя')->placeholder('Не указано')->weight('bold'),
                TextEntry::make('participant_email')->label('Email')->copyable()->url(fn (CrmMailThread $record): string => 'mailto:'.$record->participant_email),
                TextEntry::make('counterparty.name')->label('Контрагент CRM')->placeholder('Новый контакт'),
                TextEntry::make('assignedUser.name')->label('Ответственный')->placeholder('Не назначен'),
                TextEntry::make('status')->label('Статус')->badge()
                    ->formatStateUsing(fn (string $state): string => CrmMailThread::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => static::statusColor($state)),
                TextEntry::make('priority')->label('Приоритет')->badge()
                    ->formatStateUsing(fn (string $state): string => CrmMailThread::PRIORITIES[$state] ?? $state),
                TextEntry::make('mailbox.email')->label('Получено на'),
                TextEntry::make('last_message_at')->label('Последнее письмо')->dateTime('d.m.Y H:i', 'Europe/Minsk'),
                TextEntry::make('manager_note')->label('Итог / следующий шаг')->placeholder('Не заполнен')->columnSpanFull(),
            ]),
            Section::make('Переписка')
                ->description('Входящие и ответы сотрудников собраны в одной цепочке.')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('messages')->hiddenLabel()->columns(4)->schema([
                        TextEntry::make('direction')->label('Направление')->badge()
                            ->formatStateUsing(fn (string $state): string => $state === 'outgoing' ? 'Ответ сотрудника' : 'Входящее')
                            ->color(fn (string $state): string => $state === 'outgoing' ? 'success' : 'info'),
                        TextEntry::make('from_email')->label('От кого')->copyable(),
                        TextEntry::make('received_at')->label('Когда')->dateTime('d.m.Y H:i', 'Europe/Minsk'),
                        TextEntry::make('attachments_count')->label('Вложения')->formatStateUsing(fn (int $state): string => $state ? $state.' шт.' : 'Нет'),
                        TextEntry::make('subject')->label('Тема')->weight('bold')->columnSpanFull(),
                        TextEntry::make('body_preview')->label('Сообщение')
                            ->state(fn (CrmMailMessage $record): string => trim($record->text_body ?: strip_tags((string) $record->html_body)) ?: 'Текст отсутствует')
                            ->columnSpanFull(),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('last_message_at')->label('Получено')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
                TextColumn::make('participant_name')->label('Отправитель')->weight('bold')->searchable()
                    ->description(fn (CrmMailThread $record): string => $record->participant_email),
                TextColumn::make('subject')->label('Тема')->searchable()->limit(65)->wrap()
                    ->description(fn (CrmMailThread $record): string => $record->messages_count.' писем · непрочитанных '.$record->unread_count),
                TextColumn::make('counterparty.name')->label('CRM')->placeholder('Новый лид')
                    ->description(fn (CrmMailThread $record): ?string => $record->contact?->full_name),
                TextColumn::make('assignedUser.name')->label('Ответственный')->placeholder('Не назначен'),
                TextColumn::make('priority')->label('Приоритет')->badge()
                    ->formatStateUsing(fn (string $state): string => CrmMailThread::PRIORITIES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'urgent' => 'danger', 'high' => 'warning', 'low' => 'gray', default => 'info',
                    }),
                TextColumn::make('status')->label('Состояние')->badge()->sortable()
                    ->formatStateUsing(fn (string $state): string => CrmMailThread::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => static::statusColor($state)),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Статус')->options(CrmMailThread::STATUSES),
                SelectFilter::make('priority')->label('Приоритет')->options(CrmMailThread::PRIORITIES),
                SelectFilter::make('assigned_user_id')->label('Ответственный')->relationship('assignedUser', 'name')->searchable()->preload(),
                SelectFilter::make('mailbox_id')->label('Ящик')->relationship('mailbox', 'email'),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    Action::make('take')
                        ->label('Взять в работу')->icon('heroicon-o-hand-raised')->color('warning')
                        ->visible(fn (CrmMailThread $record): bool => $record->assigned_user_id !== auth()->id() || $record->status === 'new')
                        ->action(fn (CrmMailThread $record) => $record->update([
                            'assigned_user_id' => auth()->id(), 'status' => 'in_progress',
                        ])),
                    Action::make('close')
                        ->label('Закрыть')->icon('heroicon-o-check-circle')->color('success')
                        ->visible(fn (CrmMailThread $record): bool => $record->status !== 'closed')
                        ->action(fn (CrmMailThread $record) => $record->update(['status' => 'closed', 'handled_at' => now()])),
                    EditAction::make(),
                ]),
            ]);
    }

    public static function statusColor(string $state): string
    {
        return match ($state) {
            'new', 'waiting_manager' => 'danger',
            'in_progress' => 'warning',
            'waiting_customer' => 'info',
            'closed' => 'success',
            default => 'gray',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrmMailThreads::route('/'),
            'view' => ViewCrmMailThread::route('/{record}'),
            'edit' => EditCrmMailThread::route('/{record}/edit'),
        ];
    }
}
