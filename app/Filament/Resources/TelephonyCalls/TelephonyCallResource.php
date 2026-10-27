<?php

namespace App\Filament\Resources\TelephonyCalls;

use App\Filament\Resources\TelephonyCalls\Pages\EditTelephonyCall;
use App\Filament\Resources\TelephonyCalls\Pages\ListTelephonyCalls;
use App\Filament\Resources\TelephonyCalls\Pages\ViewTelephonyCall;
use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\TelephonyCall;
use App\Support\AdminPhoneLinks;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class TelephonyCallResource extends Resource
{
    protected static ?string $model = TelephonyCall::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?string $navigationLabel = 'Все звонки';

    protected static ?string $modelLabel = 'звонок';

    protected static ?string $pluralModelLabel = 'Телефония';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'Телефония';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = TelephonyCall::query()->needsAttention()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'assignedUser', 'matchedUser', 'crmCounterparty', 'crmContact', 'handledByUser', 'order', 'contactRequest',
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Работа со звонком')->columns(2)->schema([
                Select::make('handling_status')->label('Состояние лида')
                    ->options(TelephonyCall::HANDLING_STATUSES)->required(),
                Select::make('assigned_user_id')->label('Ответственный')
                    ->options(fn (): array => User::query()->whereIn('role', ['admin', 'manager', 'sales_manager'])
                        ->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->nullable(),
                Select::make('crm_counterparty_id')->label('Контрагент CRM')
                    ->relationship('crmCounterparty', 'name')->searchable()->preload()->nullable(),
                Select::make('crm_contact_id')->label('Контактное лицо CRM')
                    ->options(fn (): array => CrmContact::query()->with('counterparty')->orderBy('full_name')->get()
                        ->mapWithKeys(fn (CrmContact $contact): array => [
                            $contact->id => $contact->full_name.($contact->counterparty ? ' — '.$contact->counterparty->name : ''),
                        ])->all())
                    ->searchable()->nullable(),
                Select::make('manager_outcome')->label('Итог обзвона')
                    ->options(TelephonyCall::MANAGER_OUTCOMES)->nullable(),
                DateTimePicker::make('next_follow_up_at')->label('Следующий контакт')
                    ->seconds(false)->native(false)->nullable(),
                Textarea::make('manager_note')->label('Результат разговора / следующий шаг')
                    ->rows(5)->maxLength(3000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Управление звонком')
                    ->description('Текущий этап, ответственный и ближайшее действие')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->columnSpanFull()
                    ->compact()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('handling_status')
                            ->label('Статус работы')
                            ->badge()
                            ->color(fn (?string $state): string => self::handlingStatusColor($state))
                            ->icon(fn (?string $state): ?string => self::handlingStatusIcon($state))
                            ->formatStateUsing(fn (?string $state): string => TelephonyCall::HANDLING_STATUSES[$state] ?? ($state ?: '—')),
                        TextEntry::make('assignedUser.name')
                            ->label('Ответственный')
                            ->icon('heroicon-o-user-circle')
                            ->placeholder('Не назначен')
                            ->color(fn ($state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('next_follow_up_at')
                            ->label('Следующий контакт')
                            ->icon('heroicon-o-clock')
                            ->dateTime('d.m.Y H:i', 'Europe/Minsk')
                            ->placeholder('Не запланирован')
                            ->color(fn (TelephonyCall $record): string => $record->next_follow_up_at?->isPast() ? 'danger' : 'gray'),
                        TextEntry::make('manager_outcome')
                            ->label('Итог разговора')
                            ->badge()
                            ->placeholder('Не заполнен')
                            ->color(fn (?string $state): string => self::managerOutcomeColor($state))
                            ->formatStateUsing(fn (?string $state): string => TelephonyCall::MANAGER_OUTCOMES[$state] ?? ($state ?: '—')),
                    ]),

                Section::make('Клиент')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('contact_name')->label('Имя')->weight('bold')->columnSpanFull(),
                        TextEntry::make('customer_phone')
                            ->label('Телефон')
                            ->icon('heroicon-o-phone')
                            ->copyable()
                            ->placeholder('Не определён')
                            ->formatStateUsing(fn (?string $state): ?HtmlString => AdminPhoneLinks::render($state))
                            ->html(),
                        TextEntry::make('crmContact.position')->label('Должность')->placeholder('Не указана'),
                        TextEntry::make('crmCounterparty.name')->label('Контрагент CRM')->placeholder('Не определён'),
                        TextEntry::make('crmContact.full_name')->label('Контакт CRM')->placeholder('Не определён'),
                    ]),

                Section::make('Детали звонка')
                    ->icon('heroicon-o-phone-arrow-up-right')
                    ->columnSpan(2)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('started_at')->label('Начало')->dateTime('d.m.Y H:i:s', 'Europe/Minsk'),
                        TextEntry::make('direction')->label('Направление')->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'outbound' => 'Исходящий', 'internal' => 'Внутренний', default => 'Входящий',
                            })
                            ->color(fn (string $state): string => $state === 'inbound' ? 'info' : 'gray'),
                        TextEntry::make('status')->label('Результат телефонии')->badge()
                            ->color(fn (?string $state): string => self::callStatusColor($state))
                            ->formatStateUsing(fn (string $state): string => TelephonyCall::STATUSES[$state] ?? $state),
                        TextEntry::make('employee_phone')->label('Телефон сотрудника A1')->placeholder('—')->copyable(),
                        TextEntry::make('line_number')->label('Многоканальная линия')->placeholder('—')->copyable()
                            ->formatStateUsing(fn (?string $state): string => self::companyLineLabel($state)),
                        TextEntry::make('duration')->label('Общая длительность')
                            ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : gmdate('i:s', $state)),
                        TextEntry::make('conversation_duration')->label('Разговор')
                            ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : gmdate('i:s', $state)),
                        TextEntry::make('order.number')->label('Связанный заказ')->placeholder('Не найден'),
                        TextEntry::make('contactRequest.name')->label('Обращение с сайта')->placeholder('Не найдено')->columnSpanFull(),
                    ]),

                Section::make('Результат и следующий шаг')
                    ->description('Общая заметка для менеджеров')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('manager_note')
                            ->label('Что решили')
                            ->placeholder('Результат разговора ещё не записан')
                            ->prose()
                            ->columnSpan(2),
                        TextEntry::make('handledByUser.name')
                            ->label('Результат записал')
                            ->icon('heroicon-o-user-circle')
                            ->placeholder('—'),
                    ]),

                Section::make('Запись разговора')
                    ->icon('heroicon-o-play-circle')
                    ->columnSpanFull()
                    ->visible(fn (TelephonyCall $record): bool => filled($record->record_name))
                    ->schema([
                        TextEntry::make('record_name')
                            ->label('Прослушать')
                            ->formatStateUsing(fn (TelephonyCall $record): HtmlString => new HtmlString(sprintf(
                                '<audio controls preload="none" style="width: min(100%%, 680px)" src="%s"></audio>',
                                e(route('telephony.a1.recording', $record)),
                            )))
                            ->html(),
                    ]),

                Section::make('Технические данные')
                    ->icon('heroicon-o-code-bracket')
                    ->columnSpanFull()
                    ->compact()
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('external_uuid')->label('UUID A1')->copyable(),
                        TextEntry::make('employee_phone')->label('Линия сотрудника')->placeholder('—'),
                        TextEntry::make('failure_reason')->label('Причина ошибки')->placeholder('Нет'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('started_at')->label('Когда')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable()
                    ->description(fn (TelephonyCall $record): ?string => $record->finished_at?->timezone('Europe/Minsk')->format('завершён H:i:s')),
                TextColumn::make('direction')->label('Тип')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'outbound' => 'Исходящий', 'internal' => 'Внутренний', default => 'Входящий',
                    })->color(fn (string $state): string => $state === 'inbound' ? 'info' : 'gray'),
                TextColumn::make('status')->label('Результат')->badge()->sortable()
                    ->formatStateUsing(fn (string $state): string => TelephonyCall::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'answered' => 'success', 'ringing' => 'info', 'missed', 'busy', 'denied', 'failed' => 'danger', default => 'warning',
                    }),
                TextColumn::make('customer_phone')->label('Клиент')->searchable()->copyable()->weight('bold')
                    ->formatStateUsing(fn (?string $state): ?HtmlString => AdminPhoneLinks::render($state))
                    ->html()
                    ->description(fn (TelephonyCall $record): string => $record->crmCounterparty
                        ? $record->contact_name.' · '.$record->crmCounterparty->name
                        : $record->contact_name),
                TextColumn::make('order.number')->label('Связь')->placeholder('Без заказа')
                    ->description(fn (TelephonyCall $record): ?string => $record->contactRequest ? 'Есть обращение с сайта' : null),
                TextColumn::make('employee_phone')->label('Телефон сотрудника')->placeholder('—')->copyable(),
                TextColumn::make('line_number')->label('Линия')->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => self::companyLineLabel($state)),
                TextColumn::make('assignedUser.name')->label('Ответственный')->placeholder('Не назначен'),
                TextColumn::make('duration')->label('Время')->formatStateUsing(fn (?int $state): string => $state === null ? '—' : gmdate('i:s', $state)),
                TextColumn::make('handling_status')->label('Контроль')->badge()->sortable()
                    ->formatStateUsing(fn (string $state): string => TelephonyCall::HANDLING_STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'callback_required' => 'danger', 'unprocessed' => 'warning', 'in_progress' => 'info', 'handled' => 'success', default => 'gray',
                    }),
                TextColumn::make('manager_outcome')->label('Обратная связь')->badge()->sortable()
                    ->formatStateUsing(fn (?string $state): string => TelephonyCall::MANAGER_OUTCOMES[$state] ?? ($state ?: 'Не заполнена'))
                    ->color(fn (?string $state): string => match ($state) {
                        'interested', 'converted', 'order_created' => 'success',
                        'consultation_needed', 'offer_sent', 'callback_later' => 'info',
                        'no_answer' => 'warning',
                        'not_interested', 'wrong_number' => 'gray',
                        default => 'gray',
                    })
                    ->description(fn (TelephonyCall $record): ?string => collect([
                        filled($record->manager_note) ? Str::limit($record->manager_note, 90) : null,
                        $record->handledByUser?->name ? 'Менеджер: '.$record->handledByUser->name : null,
                        $record->next_follow_up_at ? 'Следующий контакт: '.$record->next_follow_up_at->timezone('Europe/Minsk')->format('d.m H:i') : null,
                    ])->filter()->join(' · ') ?: null)
                    ->tooltip(fn (TelephonyCall $record): ?string => $record->manager_note)
                    ->wrap(),
                TextColumn::make('record_name')->label('Запись')->formatStateUsing(fn (?string $state): string => $state ? 'Слушать' : '—')
                    ->icon(fn (?string $state): ?string => $state ? 'heroicon-o-play-circle' : null)
                    ->url(fn (TelephonyCall $record): ?string => $record->record_name ? route('telephony.a1.recording', $record) : null)
                    ->openUrlInNewTab()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Результат')->options(TelephonyCall::STATUSES),
                SelectFilter::make('handling_status')->label('Контроль')->options(TelephonyCall::HANDLING_STATUSES),
                SelectFilter::make('manager_outcome')->label('Итог обзвона')->options(TelephonyCall::MANAGER_OUTCOMES),
                SelectFilter::make('assigned_user_id')->label('Ответственный')->relationship('assignedUser', 'name')->searchable()->preload(),
                SelectFilter::make('crm_counterparty_id')->label('Контрагент')->relationship('crmCounterparty', 'name')->searchable()->preload(),
                SelectFilter::make('direction')->label('Направление')->options([
                    'inbound' => 'Входящий', 'outbound' => 'Исходящий', 'internal' => 'Внутренний',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('take')->label('Взять в работу')->icon('heroicon-o-hand-raised')->color('info')
                    ->visible(fn (TelephonyCall $record): bool => in_array($record->handling_status, ['unprocessed', 'callback_required'], true))
                    ->action(fn (TelephonyCall $record) => $record->update(['assigned_user_id' => auth()->id(), 'handling_status' => 'in_progress'])),
                Action::make('manager_feedback')->label('Результат обзвона')->icon('heroicon-o-chat-bubble-left-right')->color('success')
                    ->fillForm(fn (TelephonyCall $record): array => [
                        'manager_outcome' => $record->manager_outcome,
                        'manager_note' => $record->manager_note,
                        'next_follow_up_at' => $record->next_follow_up_at,
                        'order_id' => $record->order_id,
                        'convert_to_client' => false,
                        'full_name' => $record->crmContact?->full_name,
                        'company_name' => $record->crmCounterparty?->type === 'organization' ? $record->crmCounterparty->name : null,
                        'classification' => $record->crmCounterparty?->classification ?? 'retail',
                        'business_unit' => $record->crmCounterparty?->business_unit ?? 'retail',
                    ])
                    ->form([
                        Select::make('manager_outcome')->label('Итог разговора')
                            ->options(TelephonyCall::MANAGER_OUTCOMES)->required()->live(),
                        Textarea::make('manager_note')->label('О чём говорили и что делать дальше')
                            ->placeholder('Потребность клиента, договорённости, возражения, следующий шаг…')
                            ->helperText('Эту запись увидят другие менеджеры в журнале звонков и карточке клиента.')
                            ->rows(4)->maxLength(3000)->required(),
                        DateTimePicker::make('next_follow_up_at')->label('Когда связаться снова')
                            ->seconds(false)->native(false)
                            ->required(fn (Get $get): bool => in_array($get('manager_outcome'), ['callback_later', 'no_answer'], true))
                            ->visible(fn (Get $get): bool => in_array($get('manager_outcome'), ['callback_later', 'no_answer'], true)),
                        Select::make('order_id')->label('Связанный заказ')
                            ->relationship('order', 'number')
                            ->searchable()->preload()
                            ->required(fn (Get $get): bool => $get('manager_outcome') === 'order_created')
                            ->visible(fn (Get $get): bool => $get('manager_outcome') === 'order_created')
                            ->helperText('Заказ сохранится в связи со звонком, а звонок будет закрыт как обработанный.'),
                        Toggle::make('convert_to_client')->label('Конвертировать номер в клиента CRM')
                            ->helperText('Создаст карточку клиента и свяжет её с этим звонком.')
                            ->live()->visible(fn (TelephonyCall $record): bool => ! $record->crm_contact_id),
                        TextInput::make('full_name')->label('Имя клиента')->maxLength(255)
                            ->required(fn (Get $get): bool => (bool) $get('convert_to_client'))
                            ->visible(fn (Get $get): bool => (bool) $get('convert_to_client')),
                        TextInput::make('company_name')->label('Компания')->maxLength(255)
                            ->helperText('Можно оставить пустым для физического лица.')
                            ->visible(fn (Get $get): bool => (bool) $get('convert_to_client')),
                        Select::make('classification')->label('Категория клиента')->options(CrmCounterparty::CLASSIFICATIONS)
                            ->required(fn (Get $get): bool => (bool) $get('convert_to_client'))
                            ->visible(fn (Get $get): bool => (bool) $get('convert_to_client')),
                        Select::make('business_unit')->label('Направление')->options(CrmCounterparty::BUSINESS_UNITS)
                            ->required(fn (Get $get): bool => (bool) $get('convert_to_client'))
                            ->visible(fn (Get $get): bool => (bool) $get('convert_to_client')),
                        TextInput::make('position')->label('Должность')->maxLength(255)
                            ->visible(fn (Get $get): bool => (bool) $get('convert_to_client')),
                    ])
                    ->modalHeading('Результат обзвона')
                    ->modalDescription(fn (TelephonyCall $record): string => 'Номер: '.($record->customer_phone ?: 'не определён'))
                    ->modalSubmitActionLabel('Сохранить результат')
                    ->action(function (TelephonyCall $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $convert = (bool) ($data['convert_to_client'] ?? false) && ! $record->crm_contact_id;
                            $outcome = $convert ? 'converted' : $data['manager_outcome'];
                            if ($convert) {
                                self::createClientFromCall($record, $data, 'active');
                            }
                            $record->update([
                                'assigned_user_id' => $record->assigned_user_id ?: auth()->id(),
                                'handling_status' => self::handlingStatusForOutcome($outcome),
                                'manager_outcome' => $outcome,
                                'manager_note' => trim((string) $data['manager_note']),
                                'order_id' => $data['order_id'] ?? $record->order_id,
                                'next_follow_up_at' => in_array($outcome, ['callback_later', 'no_answer'], true)
                                    ? ($data['next_follow_up_at'] ?? null)
                                    : null,
                                'handled_at' => now(),
                                'handled_by' => auth()->id(),
                            ]);
                        });

                        Notification::make()->success()->title('Результат обзвона сохранён')
                            ->body(($data['convert_to_client'] ?? false) ? 'Номер конвертирован в клиента CRM.' : 'Комментарий доступен всей команде.')
                            ->send();
                    }),
                Action::make('create_crm_contact')->label('Создать клиента')->icon('heroicon-o-user-plus')->color('warning')
                    ->visible(fn (TelephonyCall $record): bool => ! $record->crm_contact_id && filled($record->customer_phone))
                    ->form([
                        TextInput::make('full_name')->label('Имя звонящего')->required()->maxLength(255),
                        TextInput::make('company_name')->label('Компания')->maxLength(255)
                            ->helperText('Оставьте пустым для розничного клиента — физического лица.'),
                        Select::make('classification')->label('Категория')->options(CrmCounterparty::CLASSIFICATIONS)
                            ->default('unclassified')->required(),
                        Select::make('business_unit')->label('Направление')->options(CrmCounterparty::BUSINESS_UNITS)
                            ->default('wholesale')->required(),
                        TextInput::make('position')->label('Должность')->maxLength(255),
                    ])
                    ->modalHeading('Создать клиента из звонка')
                    ->action(function (TelephonyCall $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            self::createClientFromCall($record, $data, 'lead');
                        });
                    }),
                ActionGroup::make([
                    Action::make('link_crm_contact')->label('Связать с существующим контактом')->icon('heroicon-o-link')
                        ->form([
                            Select::make('crm_contact_id')->label('Контакт')->required()->searchable()
                                ->options(fn (): array => CrmContact::query()->with('counterparty')->orderBy('full_name')->get()
                                    ->mapWithKeys(fn (CrmContact $contact): array => [
                                        $contact->id => $contact->full_name.($contact->counterparty ? ' — '.$contact->counterparty->name : ''),
                                    ])->all()),
                        ])->action(function (TelephonyCall $record, array $data): void {
                            $contact = CrmContact::query()->findOrFail($data['crm_contact_id']);
                            $record->update([
                                'crm_contact_id' => $contact->id,
                                'crm_counterparty_id' => $contact->counterparty_id,
                            ]);
                        }),
                    Action::make('handled')->label('Обработан')->icon('heroicon-o-check-circle')->color('success')
                        ->action(fn (TelephonyCall $record) => $record->update([
                            'handling_status' => 'handled', 'handled_at' => now(), 'handled_by' => auth()->id(),
                        ])),
                    Action::make('irrelevant')->label('Неактуален')->icon('heroicon-o-no-symbol')->color('gray')
                        ->requiresConfirmation()->action(fn (TelephonyCall $record) => $record->update([
                            'handling_status' => 'irrelevant', 'handled_at' => now(), 'handled_by' => auth()->id(),
                        ])),
                    EditAction::make(),
                ]),
            ])
            ->defaultSort('started_at', 'desc')->poll('20s')
            ->emptyStateHeading('Звонков пока нет')
            ->emptyStateDescription('После подключения Webhook A1 звонки появятся здесь автоматически.');
    }

    public static function handlingStatusForOutcome(string $outcome): string
    {
        return match ($outcome) {
            'callback_later', 'no_answer' => 'callback_required',
            'not_interested', 'wrong_number' => 'irrelevant',
            default => 'handled',
        };
    }

    private static function handlingStatusColor(?string $status): string
    {
        return match ($status) {
            'callback_required' => 'danger',
            'unprocessed' => 'warning',
            'in_progress' => 'info',
            'handled' => 'success',
            default => 'gray',
        };
    }

    private static function handlingStatusIcon(?string $status): ?string
    {
        return match ($status) {
            'callback_required' => 'heroicon-o-phone-arrow-up-right',
            'unprocessed' => 'heroicon-o-exclamation-circle',
            'in_progress' => 'heroicon-o-arrow-path',
            'handled' => 'heroicon-o-check-circle',
            'irrelevant' => 'heroicon-o-no-symbol',
            default => null,
        };
    }

    private static function managerOutcomeColor(?string $outcome): string
    {
        return match ($outcome) {
            'interested', 'converted', 'order_created' => 'success',
            'consultation_needed', 'offer_sent', 'callback_later' => 'info',
            'no_answer' => 'warning',
            default => 'gray',
        };
    }

    private static function callStatusColor(?string $status): string
    {
        return match ($status) {
            'answered' => 'success',
            'ringing' => 'info',
            'missed', 'busy', 'denied', 'failed' => 'danger',
            default => 'warning',
        };
    }

    private static function companyLineLabel(?string $phone): string
    {
        if (! $phone) {
            return '—';
        }

        $name = config("telephony.company_lines.{$phone}");

        return $name ? $name.' · '.$phone : $phone;
    }

    /** @param array<string, mixed> $data */
    private static function createClientFromCall(TelephonyCall $record, array $data, string $status): void
    {
        if ($record->crm_contact_id) {
            return;
        }

        $companyName = trim((string) ($data['company_name'] ?? ''));
        $note = filled($data['manager_note'] ?? null)
            ? 'Результат звонка: '.trim((string) $data['manager_note'])
            : null;
        $counterparty = CrmCounterparty::query()->create([
            'type' => $companyName !== '' ? 'organization' : 'person',
            'name' => $companyName !== '' ? $companyName : $data['full_name'],
            'classification' => $data['classification'],
            'business_unit' => $data['business_unit'],
            'status' => $status,
            'responsible_user_id' => auth()->id(),
            'source' => 'telephony',
            'last_contact_at' => $record->started_at ?? now(),
            'notes' => $note,
        ]);
        $contact = CrmContact::query()->create([
            'counterparty_id' => $counterparty->id,
            'full_name' => $data['full_name'],
            'position' => $data['position'] ?? null,
            'is_primary' => true,
            'is_active' => true,
            'notes' => $note,
        ]);
        CrmContactPoint::query()->create([
            'counterparty_id' => $counterparty->id,
            'contact_id' => $contact->id,
            'type' => 'phone',
            'value' => $record->customer_phone,
            'is_primary' => true,
            'is_active' => true,
        ]);
        $record->update([
            'crm_counterparty_id' => $counterparty->id,
            'crm_contact_id' => $contact->id,
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelephonyCalls::route('/'),
            'view' => ViewTelephonyCall::route('/{record}'),
            'edit' => EditTelephonyCall::route('/{record}/edit'),
        ];
    }
}
