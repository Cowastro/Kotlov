<?php

namespace App\Filament\Resources\CrmCounterparties;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\CrmCounterparties\Pages\CreateCrmCounterparty;
use App\Filament\Resources\CrmCounterparties\Pages\EditCrmCounterparty;
use App\Filament\Resources\CrmCounterparties\Pages\ListCrmCounterparties;
use App\Filament\Resources\CrmCounterparties\Pages\ViewCrmCounterparty;
use App\Models\CommercialDocument;
use App\Models\CrmCounterparty;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

class CrmCounterpartyResource extends Resource
{
    protected static ?string $model = CrmCounterparty::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Контрагенты';

    protected static ?string $modelLabel = 'контрагент';

    protected static ?string $pluralModelLabel = 'Контрагенты';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'CRM';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Карточка контрагента')->columns(3)->schema([
                TextInput::make('name')->label('Название / имя')->required()->maxLength(255)->columnSpan(2),
                Select::make('type')->label('Тип')->options(CrmCounterparty::TYPES)->required()->default('organization'),
                Select::make('classification')->label('Категория')->options(CrmCounterparty::CLASSIFICATIONS)
                    ->required()->default('unclassified'),
                Select::make('business_unit')->label('Направление')->options(CrmCounterparty::BUSINESS_UNITS)
                    ->required()->default('wholesale'),
                Select::make('status')->label('Статус')->options(CrmCounterparty::STATUSES)->required()->default('active'),
                TextInput::make('legal_name')->label('Юридическое название')->maxLength(255)->columnSpan(2),
                TextInput::make('public_name')->label('Публичное название')->maxLength(255)->columnSpan(2),
                TextInput::make('unp')->label('УНП / ИНН')->maxLength(32),
                Select::make('responsible_user_id')->label('Ответственный')
                    ->options(fn (): array => User::query()->whereIn('role', ['admin', 'manager', 'sales_manager'])
                        ->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->preload(),
                TextInput::make('website')->label('Сайт')->url()->maxLength(255),
                TextInput::make('business_region')->label('Бизнес-регион 1С')->maxLength(255),
                TextInput::make('one_c_manager_name')->label('Менеджер в 1С')->maxLength(255),
                Textarea::make('legal_address')->label('Адрес')->rows(2)->columnSpanFull(),
                Textarea::make('notes')->label('Общая информация / особенности работы')->rows(4)->columnSpanFull(),
            ]),
            Section::make('Общие телефоны и электронная почта')->schema([
                Repeater::make('contactPoints')->relationship()->label('Каналы связи организации')->columns(5)
                    ->schema([
                        Select::make('type')->label('Тип')->options(['phone' => 'Телефон', 'email' => 'Email'])->required(),
                        TextInput::make('value')->label('Телефон / email')->required()->maxLength(255)->columnSpan(2),
                        TextInput::make('label')->label('Комментарий')->maxLength(100),
                        Toggle::make('is_primary')->label('Основной'),
                        Toggle::make('is_active')->label('Активен')->default(true),
                    ])->defaultItems(0)->addActionLabel('Добавить телефон или email')->reorderable(false),
            ]),
            Section::make('Коммерческие условия')
                ->description('Эти значения автоматически подставляются в новые КП этого клиента и остаются доступными для проверки менеджером.')
                ->columns(3)
                ->schema([
                    TextInput::make('commercial_discount_percent')
                        ->label('Стандартная скидка, %')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(0),
                    Select::make('commercial_currency')
                        ->label('Валюта КП')
                        ->options(['BYN' => 'BYN'])
                        ->default('BYN')
                        ->required(),
                    TextInput::make('commercial_payment_terms')
                        ->label('Условия оплаты')
                        ->placeholder('Например: 50% предоплата, 50% перед отгрузкой')
                        ->maxLength(255),
                    TextInput::make('commercial_delivery_terms')
                        ->label('Условия и срок поставки')
                        ->placeholder('Например: 3–5 рабочих дней после оплаты')
                        ->maxLength(255)
                        ->columnSpan(2),
                    Textarea::make('commercial_note')
                        ->label('Внутренние указания менеджеру')
                        ->helperText('Не печатаются в PDF и не отправляются клиенту.')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Контрагент')->columns(3)->schema([
                TextEntry::make('name')->label('Название')->weight('bold'),
                TextEntry::make('classification')->label('Категория')->badge()
                    ->formatStateUsing(fn (string $state): string => CrmCounterparty::CLASSIFICATIONS[$state] ?? $state),
                TextEntry::make('business_unit')->label('Направление')
                    ->formatStateUsing(fn (string $state): string => CrmCounterparty::BUSINESS_UNITS[$state] ?? $state),
                TextEntry::make('legal_name')->label('Юридическое название')->placeholder('—'),
                TextEntry::make('unp')->label('УНП / ИНН')->placeholder('—'),
                TextEntry::make('phones_list')->label('Телефоны')->placeholder('—')->copyable(),
                TextEntry::make('emails_list')->label('Email')->placeholder('—')->copyable(),
                TextEntry::make('responsibleUser.name')->label('Ответственный')->placeholder('Не назначен'),
                TextEntry::make('source')->label('Источник лида')->badge()
                    ->formatStateUsing(fn (string $state): string => CrmCounterparty::SOURCES[$state] ?? $state),
                TextEntry::make('contacts_count')->label('Контактных лиц')->state(fn (CrmCounterparty $record): int => $record->contacts()->count()),
                TextEntry::make('calls_count')->label('Звонков')->state(fn (CrmCounterparty $record): int => $record->calls()->count()),
                TextEntry::make('mail_threads_count')->label('Переписок')->state(fn (CrmCounterparty $record): int => $record->mailThreads()->count()),
                TextEntry::make('commercial_documents_count')->label('КП и счетов')->state(fn (CrmCounterparty $record): int => $record->commercialDocuments()->count()),
                TextEntry::make('orders_count')->label('Заказов')->state(fn (CrmCounterparty $record): int => $record->orders()->count()),
                TextEntry::make('external_identities_count')->label('Связей с 1С')
                    ->state(fn (CrmCounterparty $record): int => $record->externalIdentities()->count()),
                TextEntry::make('one_c_manager_name')->label('Менеджер в 1С')->placeholder('Не указан'),
                TextEntry::make('notes')->label('Примечание')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Коммерческие условия')->columns(3)->schema([
                TextEntry::make('commercial_discount_percent')->label('Стандартная скидка')->suffix('%'),
                TextEntry::make('commercial_currency')->label('Валюта КП')->placeholder('BYN'),
                TextEntry::make('commercial_payment_terms')->label('Оплата')->placeholder('Общие условия'),
                TextEntry::make('commercial_delivery_terms')->label('Поставка')->placeholder('Общие условия')->columnSpan(2),
                TextEntry::make('commercial_note')->label('Указания менеджеру')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Коммерческие предложения и счета')->schema([
                RepeatableEntry::make('commercialDocuments')
                    ->label('')
                    ->schema([
                        TextEntry::make('number')
                            ->label('Документ')
                            ->weight('bold')
                            ->url(fn ($record): string => CommercialDocumentResource::getUrl('view', ['record' => $record])),
                        TextEntry::make('issued_at')->label('Дата')->date('d.m.Y'),
                        TextEntry::make('type')->label('Тип')->badge()
                            ->formatStateUsing(fn (string $state): string => CommercialDocument::TYPES[$state] ?? $state),
                        TextEntry::make('status')->label('Статус')->badge()
                            ->formatStateUsing(fn (string $state): string => CommercialDocument::STATUSES[$state] ?? $state),
                        TextEntry::make('total')->label('Сумма')->money('BYN'),
                        TextEntry::make('manager.name')->label('Менеджер')->placeholder('—'),
                    ])
                    ->columns(6),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $table->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['contactPoints', 'responsibleUser']));

        return $table->columns([
            TextColumn::make('name')->label('Контрагент')->searchable()->sortable()->weight('bold')
                ->description(fn (CrmCounterparty $record): ?string => $record->legal_name),
            TextColumn::make('classification')->label('Категория')->badge()->sortable()
                ->formatStateUsing(fn (string $state): string => CrmCounterparty::CLASSIFICATIONS[$state] ?? $state)
                ->color(fn (string $state): string => match ($state) {
                    'b2b_partner' => 'success', 'b2b_prospect' => 'info', 'retail' => 'warning',
                    'supplier' => 'primary', 'unclassified' => 'danger', default => 'gray',
                }),
            TextColumn::make('business_unit')->label('Направление')->badge()
                ->formatStateUsing(fn (string $state): string => CrmCounterparty::BUSINESS_UNITS[$state] ?? $state),
            TextColumn::make('source')->label('Источник')->badge()->sortable()
                ->formatStateUsing(fn (string $state): string => CrmCounterparty::SOURCES[$state] ?? $state)
                ->color(fn (string $state): string => match ($state) {
                    'email' => 'info', 'telephony' => 'success',
                    '1c_trade', '1c_accounting' => 'warning', default => 'gray',
                }),
            TextColumn::make('unp')->label('УНП')->searchable()->placeholder('—')->copyable(),
            TextColumn::make('phones_list')->label('Телефоны')->copyable()->placeholder('—')
                ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                    'contactPoints',
                    fn (Builder $points): Builder => $points->where('type', 'phone')->where('value', 'like', "%{$search}%"),
                )),
            TextColumn::make('emails_list')->label('Email')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('contacts_count')->label('Контакты')->counts('contacts')->sortable(),
            TextColumn::make('responsibleUser.name')->label('Ответственный')->placeholder('Не назначен'),
            TextColumn::make('status')->label('Статус')->badge()
                ->formatStateUsing(fn (string $state): string => CrmCounterparty::STATUSES[$state] ?? $state),
            TextColumn::make('updated_at')->label('Обновлено')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
        ])->filters([
            SelectFilter::make('classification')->label('Категория')->options(CrmCounterparty::CLASSIFICATIONS),
            SelectFilter::make('business_unit')->label('Направление')->options(CrmCounterparty::BUSINESS_UNITS),
            SelectFilter::make('status')->label('Статус')->options(CrmCounterparty::STATUSES),
            SelectFilter::make('source')->label('Источник')->options(CrmCounterparty::SOURCES),
            SelectFilter::make('responsible_user_id')->label('Ответственный')->relationship('responsibleUser', 'name')->searchable()->preload(),
        ])->recordActions([ViewAction::make(), EditAction::make()])->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrmCounterparties::route('/'),
            'create' => CreateCrmCounterparty::route('/create'),
            'view' => ViewCrmCounterparty::route('/{record}'),
            'edit' => EditCrmCounterparty::route('/{record}/edit'),
        ];
    }
}
