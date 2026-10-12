<?php

namespace App\Filament\Resources\TelephonyCalls;

use App\Filament\Resources\TelephonyCalls\Pages\EditTelephonyCall;
use App\Filament\Resources\TelephonyCalls\Pages\ListTelephonyCalls;
use App\Filament\Resources\TelephonyCalls\Pages\ViewTelephonyCall;
use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\TelephonyCall;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

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
            'assignedUser', 'matchedUser', 'crmCounterparty', 'crmContact', 'order', 'contactRequest',
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
                Textarea::make('manager_note')->label('Результат разговора / следующий шаг')
                    ->rows(5)->maxLength(3000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Звонок')->columns(3)->schema([
                TextEntry::make('started_at')->label('Начало')->dateTime('d.m.Y H:i:s', 'Europe/Minsk'),
                TextEntry::make('direction')->label('Направление')->formatStateUsing(fn (string $state): string => match ($state) {
                    'outbound' => 'Исходящий', 'internal' => 'Внутренний', default => 'Входящий',
                }),
                TextEntry::make('status')->label('Результат')->badge()
                    ->formatStateUsing(fn (string $state): string => TelephonyCall::STATUSES[$state] ?? $state),
                TextEntry::make('customer_phone')->label('Телефон клиента')->copyable()->placeholder('—'),
                TextEntry::make('contact_name')->label('Клиент'),
                TextEntry::make('crmCounterparty.name')->label('Контрагент CRM')->placeholder('Не определён'),
                TextEntry::make('crmContact.position')->label('Должность')->placeholder('—'),
                TextEntry::make('assignedUser.name')->label('Ответственный')->placeholder('Не назначен'),
                TextEntry::make('order.number')->label('Заказ')->placeholder('Не найден'),
                TextEntry::make('duration')->label('Общая длительность')->suffix(' сек')->placeholder('—'),
                TextEntry::make('conversation_duration')->label('Разговор')->suffix(' сек')->placeholder('—'),
                TextEntry::make('handling_status')->label('Состояние лида')->badge()
                    ->formatStateUsing(fn (string $state): string => TelephonyCall::HANDLING_STATUSES[$state] ?? $state),
                TextEntry::make('failure_reason')->label('Причина ошибки')->placeholder('—')->columnSpan(2),
                TextEntry::make('manager_note')->label('Результат / следующий шаг')->placeholder('Не заполнен')->columnSpanFull(),
                TextEntry::make('record_name')->label('Запись разговора')
                    ->formatStateUsing(fn (TelephonyCall $record): HtmlString => new HtmlString(sprintf(
                        '<audio controls preload="none" style="width: min(100%%, 680px)" src="%s"></audio>',
                        e(route('telephony.a1.recording', $record)),
                    )))
                    ->html()
                    ->visible(fn (TelephonyCall $record): bool => filled($record->record_name))
                    ->columnSpanFull(),
                TextEntry::make('external_uuid')->label('UUID A1')->copyable()->columnSpanFull(),
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
                    ->description(fn (TelephonyCall $record): string => $record->crmCounterparty
                        ? $record->contact_name.' · '.$record->crmCounterparty->name
                        : $record->contact_name),
                TextColumn::make('order.number')->label('Связь')->placeholder('Без заказа')
                    ->description(fn (TelephonyCall $record): ?string => $record->contactRequest ? 'Есть обращение с сайта' : null),
                TextColumn::make('assignedUser.name')->label('Ответственный')->placeholder('Не назначен')
                    ->description(fn (TelephonyCall $record): ?string => $record->employee_phone ? 'A1: '.$record->employee_phone : null),
                TextColumn::make('duration')->label('Время')->formatStateUsing(fn (?int $state): string => $state === null ? '—' : gmdate('i:s', $state)),
                TextColumn::make('handling_status')->label('Контроль')->badge()->sortable()
                    ->formatStateUsing(fn (string $state): string => TelephonyCall::HANDLING_STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'callback_required' => 'danger', 'unprocessed' => 'warning', 'in_progress' => 'info', 'handled' => 'success', default => 'gray',
                    }),
                TextColumn::make('record_name')->label('Запись')->formatStateUsing(fn (?string $state): string => $state ? 'Слушать' : '—')
                    ->icon(fn (?string $state): ?string => $state ? 'heroicon-o-play-circle' : null)
                    ->url(fn (TelephonyCall $record): ?string => $record->record_name ? route('telephony.a1.recording', $record) : null)
                    ->openUrlInNewTab()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Результат')->options(TelephonyCall::STATUSES),
                SelectFilter::make('handling_status')->label('Контроль')->options(TelephonyCall::HANDLING_STATUSES),
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
                            $companyName = trim((string) ($data['company_name'] ?? ''));
                            $counterparty = CrmCounterparty::query()->create([
                                'type' => $companyName !== '' ? 'organization' : 'person',
                                'name' => $companyName !== '' ? $companyName : $data['full_name'],
                                'classification' => $data['classification'],
                                'business_unit' => $data['business_unit'],
                                'status' => 'lead',
                                'responsible_user_id' => auth()->id(),
                                'source' => 'telephony',
                                'last_contact_at' => $record->started_at ?? now(),
                            ]);
                            $contact = CrmContact::query()->create([
                                'counterparty_id' => $counterparty->id,
                                'full_name' => $data['full_name'],
                                'position' => $data['position'] ?? null,
                                'is_primary' => true,
                                'is_active' => true,
                            ]);
                            CrmContactPoint::query()->create([
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
