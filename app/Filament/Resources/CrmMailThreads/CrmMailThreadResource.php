<?php

namespace App\Filament\Resources\CrmMailThreads;

use App\Filament\Resources\CrmMailThreads\Pages\EditCrmMailThread;
use App\Filament\Resources\CrmMailThreads\Pages\ListCrmMailThreads;
use App\Filament\Resources\CrmMailThreads\Pages\ViewCrmMailThread;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\User;
use App\Services\CrmMail\CrmMailSender;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
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
use Throwable;

class CrmMailThreadResource extends Resource
{
    protected static ?string $model = CrmMailThread::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Почта';

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
        return parent::getEloquentQuery()->with([
            'mailbox', 'assignedUser', 'counterparty', 'contact', 'supplierOrderRequest.order',
        ]);
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
                TextEntry::make('mailbox.email')->label('Почтовый ящик'),
                TextEntry::make('last_message_at')->label('Последнее письмо')->dateTime('d.m.Y H:i', 'Europe/Minsk'),
                TextEntry::make('supplierOrderRequest.order.number')
                    ->label('Исходный заказ')
                    ->visible(fn (CrmMailThread $record): bool => $record->supplier_order_request_id !== null)
                    ->url(fn (CrmMailThread $record): string => OrderResource::getUrl('view', [
                        'record' => $record->supplierOrderRequest->order_id,
                    ]))
                    ->color('primary'),
                TextEntry::make('manager_note')->label('Итог / следующий шаг')->placeholder('Не заполнен')->columnSpanFull(),
            ]),
            Section::make('Переписка')
                ->description('Входящие и ответы сотрудников собраны в одной цепочке.')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('messages')->hiddenLabel()->columns(4)->schema([
                        TextEntry::make('direction')->label('Направление')->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'draft' => 'Черновик',
                                'outgoing' => 'Исходящее',
                                default => 'Входящее',
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'draft' => 'warning',
                                'outgoing' => 'success',
                                default => 'info',
                            }),
                        TextEntry::make('from_email')->label('От кого')->copyable(),
                        TextEntry::make('received_at')->label('Когда')->dateTime('d.m.Y H:i', 'Europe/Minsk'),
                        TextEntry::make('attachments_count')->label('Вложения')->formatStateUsing(fn (int $state): string => $state ? $state.' шт.' : 'Нет'),
                        TextEntry::make('recipients')->label('Кому')
                            ->state(fn (CrmMailMessage $record): string => collect($record->to ?? [])->pluck('email')->filter()->implode(', '))
                            ->placeholder('Не указано')->columnSpan(2),
                        TextEntry::make('copies')->label('Копия')
                            ->state(fn (CrmMailMessage $record): string => collect($record->cc ?? [])->pluck('email')->filter()->implode(', '))
                            ->placeholder('Нет')->columnSpan(2),
                        TextEntry::make('subject')->label('Тема')->weight('bold')->columnSpanFull(),
                        TextEntry::make('body_preview')->label('Сообщение')
                            ->state(fn (CrmMailMessage $record): string => trim($record->text_body ?: strip_tags((string) $record->html_body)) ?: 'Текст отсутствует')
                            ->columnSpanFull(),
                        TextEntry::make('attachment_names')->label('Прикреплённые файлы')
                            ->state(fn (CrmMailMessage $record): string => collect($record->metadata['attachments'] ?? [])->pluck('name')->filter()->implode(', '))
                            ->placeholder('Нет')->columnSpanFull(),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('60s')
            ->columns([
                TextColumn::make('last_message_at')->label('Дата')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
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
                ViewAction::make()->label('Открыть')
                    ->visible(fn (CrmMailThread $record): bool => ! $record->trashed()),
                ActionGroup::make([
                    Action::make('edit_draft')
                        ->label('Редактировать черновик')
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning')
                        ->visible(fn (CrmMailThread $record): bool => ! $record->trashed() && $record->status === 'draft')
                        ->fillForm(fn (CrmMailThread $record): array => static::draftData($record))
                        ->schema(static::draftFields(false))
                        ->modalWidth('6xl')
                        ->action(function (CrmMailThread $record, array $data): void {
                            app(SupplierRequestMailDraftService::class)->update(
                                $record,
                                static::mergeRecipients($data, 'recipients', 'address_book_to'),
                                $data['subject'],
                                $data['body'],
                                auth()->user(),
                                static::mergeRecipients($data, 'cc', 'address_book_cc'),
                                $data['attachments'] ?? [],
                                $data['attachment_file_names'] ?? [],
                            );
                            Notification::make()->success()->title('Черновик сохранён')->send();
                        }),
                    Action::make('send_draft')
                        ->label('Отправить поставщику')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->visible(fn (CrmMailThread $record): bool => ! $record->trashed() && $record->status === 'draft')
                        ->fillForm(fn (CrmMailThread $record): array => static::draftData($record))
                        ->schema(static::draftFields(true))
                        ->modalWidth('6xl')
                        ->requiresConfirmation()
                        ->modalHeading('Отправить письмо поставщику?')
                        ->action(function (CrmMailThread $record, array $data): void {
                            try {
                                $thread = app(SupplierRequestMailDraftService::class)->update(
                                    $record,
                                    static::mergeRecipients($data, 'recipients', 'address_book_to'),
                                    $data['subject'],
                                    $data['body'],
                                    auth()->user(),
                                    static::mergeRecipients($data, 'cc', 'address_book_cc'),
                                    $data['attachments'] ?? [],
                                    $data['attachment_file_names'] ?? [],
                                );
                                app(CrmMailSender::class)->sendDraft($thread, auth()->user());
                                Notification::make()->success()->title('Письмо поставщику отправлено')->send();
                            } catch (Throwable $exception) {
                                Notification::make()->danger()->title('Письмо не отправлено')->body($exception->getMessage())->send();
                            }
                        }),
                    Action::make('take')
                        ->label('Взять в работу')->icon('heroicon-o-hand-raised')->color('warning')
                        ->visible(fn (CrmMailThread $record): bool => ! $record->trashed()
                            && $record->status !== 'draft'
                            && ($record->assigned_user_id !== auth()->id() || $record->status === 'new'))
                        ->action(fn (CrmMailThread $record) => $record->takeFor((int) auth()->id())),
                    Action::make('close')
                        ->label('Закрыть')->icon('heroicon-o-check-circle')->color('success')
                        ->visible(fn (CrmMailThread $record): bool => ! $record->trashed()
                            && ! in_array($record->status, ['draft', 'closed'], true))
                        ->action(fn (CrmMailThread $record) => $record->update(['status' => 'closed', 'handled_at' => now()])),
                    Action::make('mark_spam')
                        ->label('В спам')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(fn (CrmMailThread $record): bool => ! $record->trashed()
                            && ! in_array($record->status, ['draft', 'spam'], true))
                        ->requiresConfirmation()
                        ->modalHeading('Пометить переписку как спам?')
                        ->modalDescription('Переписка исчезнет из рабочей почты и будет доступна во вкладке «Спам».')
                        ->action(fn (CrmMailThread $record) => $record->markAsSpam()),
                    Action::make('restore_spam')
                        ->label('Вернуть из спама')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('success')
                        ->visible(fn (CrmMailThread $record): bool => ! $record->trashed() && $record->status === 'spam')
                        ->action(fn (CrmMailThread $record) => $record->restoreFromSpam()),
                    EditAction::make()
                        ->visible(fn (CrmMailThread $record): bool => ! $record->trashed() && $record->status !== 'draft'),
                    Action::make('trash')
                        ->label('В корзину')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn (CrmMailThread $record): bool => ! $record->trashed())
                        ->requiresConfirmation()
                        ->modalHeading('Переместить переписку в корзину?')
                        ->modalDescription('Переписку можно будет восстановить. Новое письмо клиента также автоматически вернёт её в работу.')
                        ->action(fn (CrmMailThread $record) => $record->delete()),
                    Action::make('restore')
                        ->label('Восстановить')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('success')
                        ->visible(fn (CrmMailThread $record): bool => $record->trashed())
                        ->action(fn (CrmMailThread $record) => $record->restore()),
                    Action::make('delete_forever')
                        ->label('Удалить навсегда')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (CrmMailThread $record): bool => $record->trashed() && auth()->user()?->isAdmin() === true)
                        ->requiresConfirmation()
                        ->modalHeading('Удалить переписку навсегда?')
                        ->modalDescription('Будут окончательно удалены вся цепочка и сохранённые письма. Это действие нельзя отменить.')
                        ->action(fn (CrmMailThread $record) => $record->forceDelete()),
                ]),
            ]);
    }

    public static function statusColor(string $state): string
    {
        return match ($state) {
            'draft' => 'warning',
            'new', 'waiting_manager', 'spam' => 'danger',
            'in_progress' => 'warning',
            'waiting_customer' => 'info',
            'closed' => 'success',
            default => 'gray',
        };
    }

    /** @return array<int, mixed> */
    public static function draftFields(bool $recipientRequired): array
    {
        return [
            Section::make('Адресаты')
                ->description('Выберите контакты CRM либо введите новые адреса вручную.')
                ->columns(2)
                ->schema([
                    Select::make('address_book_to')
                        ->label('Адресная книга — кому')
                        ->options(fn (): array => static::addressBookOptions())
                        ->multiple()->searchable()->preload()
                        ->helperText('Контакты и компании из CRM.'),
                    TagsInput::make('recipients')
                        ->label('Кому')
                        ->placeholder('email@example.com')
                        ->helperText($recipientRequired
                            ? 'Нужен хотя бы один email. После ввода нажмите Enter.'
                            : 'Можно указать несколько адресов.'),
                    Select::make('address_book_cc')
                        ->label('Адресная книга — копия')
                        ->options(fn (): array => static::addressBookOptions())
                        ->multiple()->searchable()->preload(),
                    TagsInput::make('cc')
                        ->label('Копия (CC)')
                        ->placeholder('copy@example.com')
                        ->helperText('Получатели копии увидят остальных адресатов.'),
                ]),
            Section::make('Письмо')
                ->schema([
                    TextInput::make('subject')->label('Тема')->required()->maxLength(255),
                    Textarea::make('body')->label('Текст письма')->rows(12)->required()->maxLength(30000),
                    FileUpload::make('attachments')
                        ->label('Вложения')
                        ->disk('local')
                        ->directory('crm-mail-attachments')
                        ->visibility('private')
                        ->multiple()
                        ->maxFiles(10)
                        ->maxSize(15360)
                        ->storeFileNamesIn('attachment_file_names')
                        ->downloadable()
                        ->openable()
                        ->helperText('До 10 файлов, каждый не более 15 МБ.'),
                ]),
        ];
    }

    /** @return array<string, mixed> */
    public static function draftData(CrmMailThread $record): array
    {
        $draft = $record->messages()->where('direction', 'draft')->first();

        return [
            'recipients' => collect($draft?->to ?? [])->pluck('email')->filter()->values()->all()
                ?: array_values(array_filter([(string) $record->participant_email])),
            'cc' => collect($draft?->cc ?? [])->pluck('email')->filter()->values()->all(),
            'subject' => (string) $record->subject,
            'body' => (string) $draft?->text_body,
            'attachments' => collect($draft?->metadata['attachments'] ?? [])->pluck('path')->filter()->values()->all(),
            'attachment_file_names' => collect($draft?->metadata['attachments'] ?? [])->pluck('name', 'path')->filter()->all(),
        ];
    }

    /** @return array<string, string> */
    public static function addressBookOptions(): array
    {
        return CrmContactPoint::query()
            ->where('type', 'email')
            ->where('is_active', true)
            ->with(['contact:id,full_name', 'counterparty:id,name'])
            ->orderByDesc('is_primary')
            ->get()
            ->mapWithKeys(function (CrmContactPoint $point): array {
                $email = mb_strtolower(trim($point->value));
                $owner = $point->contact?->full_name ?: $point->counterparty?->name ?: 'Контакт CRM';

                return filter_var($email, FILTER_VALIDATE_EMAIL)
                    ? [$email => $owner.' — '.$email]
                    : [];
            })->all();
    }

    /** @return list<string> */
    public static function mergeRecipients(array $data, string $manualKey, string $bookKey): array
    {
        return collect(array_merge($data[$manualKey] ?? [], $data[$bookKey] ?? []))
            ->map(fn (mixed $email): string => mb_strtolower(trim((string) $email)))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()->values()->all();
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
