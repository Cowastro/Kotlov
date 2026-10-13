<?php

namespace App\Filament\Resources\CrmMailThreads;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\CrmCounterparties\CrmCounterpartyResource;
use App\Filament\Resources\CrmMailThreads\Pages\EditCrmMailThread;
use App\Filament\Resources\CrmMailThreads\Pages\ListCrmMailThreads;
use App\Filament\Resources\CrmMailThreads\Pages\ViewCrmMailThread;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Category;
use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\User;
use App\Services\CommercialDocuments\QuickCommercialOfferSaver;
use App\Services\CommercialDocuments\QuickCommercialOfferService;
use App\Services\CrmMail\CrmMailMessagePreview;
use App\Services\CrmMail\CrmMailSender;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
            'mailbox', 'assignedUser', 'counterparty', 'contact', 'messages', 'commercialDocuments', 'supplierOrderRequest.order',
            'supplierOrderRequest.supplier.crmCounterparty',
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Обработка лида')->columns(2)->schema([
                Select::make('status')->label('Статус')->options(CrmMailThread::STATUSES)->required(),
                Select::make('priority')->label('Приоритет')->options(CrmMailThread::PRIORITIES)->required(),
                Select::make('sales_stage')->label('Стадия потенциальной сделки')
                    ->options(CrmMailThread::SALES_STAGES)->placeholder('Не является сделкой')->nullable(),
                DateTimePicker::make('next_follow_up_at')->label('Следующий контроль')
                    ->seconds(false)->native(false)->nullable(),
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
                Textarea::make('follow_up_note')->label('Что сделать при следующем контакте')
                    ->rows(3)->maxLength(3000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Лид')->columns(4)->schema([
                TextEntry::make('participant_name')->label('Имя')->placeholder('Не указано')->weight('bold'),
                TextEntry::make('participant_email')
                    ->label('Email')
                    ->placeholder('Не указан — добавьте в карточку поставщика')
                    ->copyable(fn (CrmMailThread $record): bool => $record->hasValidRecipient())
                    ->url(fn (CrmMailThread $record): ?string => $record->hasValidRecipient()
                        ? 'mailto:'.$record->participant_email
                        : null)
                    ->color(fn (CrmMailThread $record): string => $record->hasValidRecipient() ? 'primary' : 'danger'),
                TextEntry::make('counterparty.name')->label('Контрагент CRM')->placeholder('Новый контакт'),
                TextEntry::make('assignedUser.name')->label('Ответственный')->placeholder('Не назначен'),
                TextEntry::make('status')->label('Статус')->badge()
                    ->formatStateUsing(fn (string $state): string => CrmMailThread::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => static::statusColor($state)),
                TextEntry::make('priority')->label('Приоритет')->badge()
                    ->formatStateUsing(fn (string $state): string => CrmMailThread::PRIORITIES[$state] ?? $state),
                TextEntry::make('sales_stage')->label('Потенциальная сделка')->badge()
                    ->placeholder('Не отмечена')
                    ->formatStateUsing(fn (?string $state): string => CrmMailThread::SALES_STAGES[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => static::salesStageColor($state)),
                TextEntry::make('next_follow_up_at')->label('Следующий контроль')
                    ->dateTime('d.m.Y H:i', 'Europe/Minsk')->placeholder('Не назначен')
                    ->color(fn (CrmMailThread $record): string => static::followUpColor($record)),
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
                TextEntry::make('follow_up_note')->label('Задача контроля')->placeholder('Не заполнена')->columnSpanFull(),
            ]),
            Section::make('Переписка')
                ->description('Входящие и ответы сотрудников собраны в одной цепочке.')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('messages')->hiddenLabel()->schema([
                        ViewEntry::make('mail_preview')->hiddenLabel()
                            ->view('filament.crm-mail.message-preview', fn (CrmMailMessage $record): array => [
                                'message' => $record,
                                'safeHtml' => app(CrmMailMessagePreview::class)->compactHtml($record),
                                'originalHtml' => filled($record->html_body) ? (string) $record->html_body : null,
                            ])
                            ->columnSpanFull(),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('60s')
            ->recordClasses(fn (CrmMailThread $record): string => static::mailRowClasses($record))
            ->columns([
                TextColumn::make('last_message_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i', 'Europe/Minsk')
                    ->description(fn (CrmMailThread $record): string => static::mailReadLabel($record))
                    ->icon(fn (CrmMailThread $record): string => static::mailReadIcon($record))
                    ->color(fn (CrmMailThread $record): string => static::mailReadColor($record))
                    ->weight(fn (CrmMailThread $record): string => $record->unread_count > 0 ? 'bold' : 'medium')
                    ->sortable(),
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
                TextColumn::make('sales_stage')->label('Сделка / контроль')->badge()->sortable()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => CrmMailThread::SALES_STAGES[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => static::salesStageColor($state))
                    ->icon(fn (CrmMailThread $record): ?string => static::commercialOfferIcon($record))
                    ->iconColor(fn (CrmMailThread $record): string => static::commercialOfferIconColor($record))
                    ->tooltip(fn (CrmMailThread $record): ?string => static::commercialOfferTooltip($record))
                    ->description(fn (CrmMailThread $record): ?string => static::followUpLabel($record)),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Статус')->options(CrmMailThread::STATUSES),
                SelectFilter::make('sales_stage')->label('Стадия сделки')->options(CrmMailThread::SALES_STAGES),
                SelectFilter::make('priority')->label('Приоритет')->options(CrmMailThread::PRIORITIES),
                SelectFilter::make('assigned_user_id')->label('Ответственный')->relationship('assignedUser', 'name')->searchable()->preload(),
                SelectFilter::make('mailbox_id')->label('Ящик')->relationship('mailbox', 'email'),
            ])
            ->recordActions([
                ViewAction::make()->label('Открыть')
                    ->visible(fn (CrmMailThread $record): bool => ! $record->trashed()),
                Action::make('quick_quote')
                    ->label('Быстрое КП')
                    ->icon('heroicon-o-document-currency-dollar')
                    ->color('success')
                    ->visible(fn (CrmMailThread $record): bool => ! $record->trashed() && $record->status !== 'draft')
                    ->modalHeading('Быстрое коммерческое предложение')
                    ->modalDescription('Для разбора тема и текст письма передаются настроенному DeepSeek. Компания и товары будут предложены автоматически, а цены всегда берутся только из нашей базы.')
                    ->modalSubmitActionLabel('Продолжить к КП')
                    ->modalWidth('5xl')
                    ->fillForm(function (CrmMailThread $record): array {
                        $prepared = app(QuickCommercialOfferService::class)->prepare($record);

                        return array_merge($prepared['prefill'], [
                            'product_ids' => $prepared['default_product_ids'],
                            'analysis_summary' => $prepared['analysis_summary'],
                            'unmatched_items_summary' => static::unmatchedItemsSummary($prepared['unmatched_items']),
                            'save_client_to_crm' => true,
                        ]);
                    })
                    ->schema([
                        TextInput::make('client_company')->label('Компания')->required()->maxLength(255),
                        TextInput::make('client_name')->label('Контактное лицо')->maxLength(255),
                        TextInput::make('client_email')->label('Email')->email()->required()->maxLength(255),
                        TextInput::make('client_phone')->label('Телефон')->maxLength(80),
                        TextInput::make('client_unp')->label('УНП')->length(9),
                        TextInput::make('client_address')->label('Юридический адрес')->maxLength(500)->columnSpanFull(),
                        Textarea::make('analysis_summary')->label('Результат автоматического разбора')
                            ->disabled()->dehydrated(false)->rows(2)->columnSpanFull(),
                        Toggle::make('save_client_to_crm')->label('Сохранить компанию и контактное лицо в CRM')
                            ->helperText('Будут сохранены только проверенные вами поля формы, телефоны и email из письма.'),
                        Textarea::make('unmatched_items_summary')->label('Не найдено в каталоге')
                            ->disabled()->dehydrated(false)->rows(2)->columnSpanFull(),
                        Toggle::make('create_missing_products')->label('Создать черновики отсутствующих товаров')->live()
                            ->helperText('Карточки будут неактивными и не появятся на сайте до проверки.'),
                        Select::make('draft_category_id')->label('Категория для новых черновиков')
                            ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()->visible(fn (Get $get): bool => (bool) $get('create_missing_products'))
                            ->required(fn (Get $get): bool => (bool) $get('create_missing_products')),
                        Select::make('product_ids')
                            ->label('Товары для КП')
                            ->multiple()->required(fn (Get $get): bool => ! (bool) $get('create_missing_products'))->searchable()
                            ->options(fn (CrmMailThread $record): array => app(QuickCommercialOfferService::class)->prepare($record)['suggestions'])
                            ->getSearchResultsUsing(fn (string $search): array => app(QuickCommercialOfferService::class)->searchProducts($search))
                            ->getOptionLabelsUsing(fn (array $values): array => app(QuickCommercialOfferService::class)->productLabels($values))
                            ->helperText('Предложения получены из темы, текста и артикулов письма. Можно найти любой товар каталога.'),
                    ])
                    ->action(function (CrmMailThread $record, array $data) {
                        $prepared = app(QuickCommercialOfferService::class)->prepare($record);
                        $saver = app(QuickCommercialOfferSaver::class);
                        if ($data['save_client_to_crm'] ?? false) {
                            $saver->saveClient($record, $data, $prepared['analysis']);
                        }
                        $newProducts = ($data['create_missing_products'] ?? false)
                            ? $saver->createDraftProducts($prepared['unmatched_items'], (int) $data['draft_category_id'])
                            : [];
                        session()->put('commercial_document_prefill', array_merge($prepared['prefill'], [
                            'client_company' => $data['client_company'],
                            'client_name' => $data['client_name'] ?? null,
                            'client_email' => $data['client_email'],
                            'client_phone' => $data['client_phone'] ?? null,
                            'client_unp' => $data['client_unp'] ?? null,
                            'client_address' => $data['client_address'] ?? null,
                        ]));
                        session()->put('commercial_document_product_ids', array_values(array_unique(array_merge($data['product_ids'] ?? [], array_keys($newProducts)))));
                        session()->put('commercial_document_product_quantities', $prepared['product_quantities'] + $newProducts);
                        session()->put('commercial_document_create_mail_draft', true);

                        return redirect(CommercialDocumentResource::getUrl('create'));
                    }),
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
                        ->label('Отправить письмо')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->visible(fn (CrmMailThread $record): bool => ! $record->trashed() && $record->status === 'draft')
                        ->disabled(fn (CrmMailThread $record): bool => ! $record->hasValidRecipient())
                        ->tooltip(fn (CrmMailThread $record): ?string => $record->hasValidRecipient()
                            ? null
                            : 'Сначала укажите хотя бы одного получателя')
                        ->fillForm(fn (CrmMailThread $record): array => static::draftData($record))
                        ->schema(static::draftFields(true))
                        ->modalWidth('6xl')
                        ->modalSubmitActionLabel('Отправить письмо')
                        ->modalHeading('Отправить письмо')
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
                                Notification::make()->success()->title('Письмо отправлено')->send();
                            } catch (Throwable $exception) {
                                Notification::make()->danger()->title('Письмо не отправлено')->body($exception->getMessage())->send();
                            }
                        }),
                    Action::make('add_supplier_email')
                        ->label('Добавить email в карточку')
                        ->icon('heroicon-o-at-symbol')
                        ->color('danger')
                        ->visible(fn (CrmMailThread $record): bool => ! $record->trashed()
                            && $record->status === 'draft'
                            && $record->supplier_order_request_id !== null
                            && ! $record->hasValidRecipient()
                            && static::supplierEmailCardUrl($record) !== null)
                        ->url(fn (CrmMailThread $record): ?string => static::supplierEmailCardUrl($record))
                        ->openUrlInNewTab(),
                    Action::make('take')
                        ->label('Взять в работу')->icon('heroicon-o-hand-raised')->color('warning')
                        ->visible(fn (CrmMailThread $record): bool => ! $record->trashed()
                            && $record->status !== 'draft'
                            && ($record->assigned_user_id !== auth()->id() || $record->status === 'new'))
                        ->action(fn (CrmMailThread $record) => $record->takeFor((int) auth()->id())),
                    Action::make('mark_potential_deal')
                        ->label('В потенциальные сделки')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('info')
                        ->visible(fn (CrmMailThread $record): bool => ! $record->trashed()
                            && ! in_array($record->status, ['draft', 'spam'], true)
                            && blank($record->sales_stage))
                        ->action(fn (CrmMailThread $record) => $record->update([
                            'sales_stage' => 'new_inquiry',
                            'assigned_user_id' => $record->assigned_user_id ?: auth()->id(),
                            'next_follow_up_at' => now()->addWeekday()->setTime(10, 0),
                            'follow_up_note' => 'Уточнить потребность клиента и следующий шаг.',
                        ])),
                    Action::make('manage_deal')
                        ->label('Стадия и контроль')
                        ->icon('heroicon-o-calendar-days')
                        ->color('warning')
                        ->visible(fn (CrmMailThread $record): bool => ! $record->trashed() && filled($record->sales_stage))
                        ->fillForm(fn (CrmMailThread $record): array => [
                            'sales_stage' => $record->sales_stage,
                            'next_follow_up_at' => $record->next_follow_up_at,
                            'follow_up_note' => $record->follow_up_note,
                        ])
                        ->schema([
                            Select::make('sales_stage')->label('Стадия сделки')
                                ->options(CrmMailThread::SALES_STAGES)->required(),
                            DateTimePicker::make('next_follow_up_at')->label('Дата и время контроля')
                                ->seconds(false)->native(false),
                            Textarea::make('follow_up_note')->label('Что сделать')
                                ->rows(3)->maxLength(3000),
                        ])
                        ->action(function (CrmMailThread $record, array $data): void {
                            $finished = in_array($data['sales_stage'], ['won', 'lost'], true);
                            $record->update([
                                'sales_stage' => $data['sales_stage'],
                                'next_follow_up_at' => $finished ? null : ($data['next_follow_up_at'] ?? null),
                                'follow_up_note' => $data['follow_up_note'] ?? null,
                                'status' => $finished ? 'closed' : $record->status,
                                'handled_at' => $finished ? now() : $record->handled_at,
                            ]);
                        }),
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

    public static function salesStageColor(?string $stage): string
    {
        return match ($stage) {
            'new_inquiry' => 'danger',
            'qualification' => 'warning',
            'proposal_sent' => 'info',
            'negotiation' => 'primary',
            'won' => 'success',
            'lost' => 'gray',
            default => 'gray',
        };
    }

    public static function followUpLabel(CrmMailThread $record): ?string
    {
        if (! $record->next_follow_up_at || ! $record->isPotentialDeal()) {
            return null;
        }

        $prefix = $record->next_follow_up_at->isPast() ? 'Просрочено' : 'Контроль';

        return $prefix.': '.$record->next_follow_up_at->timezone('Europe/Minsk')->format('d.m H:i');
    }

    public static function followUpColor(CrmMailThread $record): string
    {
        if ($record->next_follow_up_at?->isPast() && $record->isPotentialDeal()) {
            return 'danger';
        }

        return 'gray';
    }

    public static function commercialOfferIcon(CrmMailThread $record): ?string
    {
        if ($record->commercialDocuments->contains(fn ($document): bool => $document->ai_assisted && $document->status === 'sent')) {
            return 'heroicon-o-sparkles';
        }

        return $record->commercialDocuments->contains(fn ($document): bool => $document->status === 'sent')
            ? 'heroicon-o-document-check'
            : null;
    }

    public static function commercialOfferIconColor(CrmMailThread $record): string
    {
        return $record->commercialDocuments->contains(fn ($document): bool => $document->ai_assisted && $document->status === 'sent')
            ? 'primary'
            : 'success';
    }

    public static function commercialOfferTooltip(CrmMailThread $record): ?string
    {
        if ($record->commercialDocuments->contains(fn ($document): bool => $document->ai_assisted && $document->status === 'sent')) {
            return 'КП подготовлено с помощью ИИ и отправлено клиенту';
        }

        return $record->commercialDocuments->contains(fn ($document): bool => $document->status === 'sent')
            ? 'Коммерческое предложение отправлено'
            : null;
    }

    public static function mailReadLabel(CrmMailThread $record): string
    {
        if ($record->unread_count > 0) {
            return 'Непрочитано: '.$record->unread_count;
        }

        return match ($record->status) {
            'new' => 'Новое · прочитано',
            'closed' => 'Закрыто · прочитано',
            default => 'Прочитано',
        };
    }

    public static function mailReadIcon(CrmMailThread $record): string
    {
        if ($record->unread_count > 0) {
            return 'heroicon-o-envelope';
        }

        return $record->status === 'closed'
            ? 'heroicon-o-check-circle'
            : 'heroicon-o-envelope-open';
    }

    public static function mailReadColor(CrmMailThread $record): string
    {
        if ($record->unread_count > 0) {
            return 'warning';
        }

        return match ($record->status) {
            'new' => 'danger',
            'closed' => 'success',
            default => 'gray',
        };
    }

    public static function mailRowClasses(CrmMailThread $record): string
    {
        if ($record->unread_count > 0) {
            return 'bg-warning-50 dark:bg-warning-950/40';
        }

        return match ($record->status) {
            'new' => 'bg-danger-50 dark:bg-danger-950/25',
            'closed' => 'bg-gray-100/80 opacity-70 dark:bg-gray-900/80',
            default => '',
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

    public static function supplierEmailCardUrl(CrmMailThread $record): ?string
    {
        $record->loadMissing('supplierOrderRequest.supplier.crmCounterparty');
        $supplier = $record->supplierOrderRequest?->supplier;

        if ($supplier?->crmCounterparty) {
            return CrmCounterpartyResource::getUrl('edit', [
                'record' => $supplier->crmCounterparty,
            ]);
        }

        return $supplier
            ? SupplierResource::getUrl('edit', ['record' => $supplier])
            : null;
    }

    public static function unmatchedItemsSummary(array $items): string
    {
        return collect($items)->map(fn (array $item): string => trim(
            (($item['sku'] ?? null) ? $item['sku'].' — ' : '').($item['query'] ?? '').' × '.($item['quantity'] ?? 1),
        ))->filter()->implode("\n") ?: 'Все распознанные позиции найдены в каталоге.';
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
