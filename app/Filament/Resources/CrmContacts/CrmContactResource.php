<?php

namespace App\Filament\Resources\CrmContacts;

use App\Filament\Resources\CrmContacts\Pages\CreateCrmContact;
use App\Filament\Resources\CrmContacts\Pages\EditCrmContact;
use App\Filament\Resources\CrmContacts\Pages\ListCrmContacts;
use App\Filament\Resources\CrmContacts\Pages\ViewCrmContact;
use App\Models\CrmContact;
use App\Models\CrmCounterparty;
use App\Support\AdminPhoneLinks;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class CrmContactResource extends Resource
{
    protected static ?string $model = CrmContact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'Контактные лица';

    protected static ?string $modelLabel = 'контакт';

    protected static ?string $pluralModelLabel = 'Контактные лица';

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'CRM';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['counterparty', 'contactPoints']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Контактное лицо')->columns(3)->schema([
                TextInput::make('full_name')->label('Имя')->required()->maxLength(255)->columnSpan(2),
                TextInput::make('position')->label('Должность')->maxLength(255),
                Select::make('counterparty_id')->label('Контрагент')->relationship('counterparty', 'name')
                    ->searchable()->preload()->createOptionForm([
                        TextInput::make('name')->label('Название')->required(),
                        Select::make('classification')->label('Категория')
                            ->options(CrmCounterparty::CLASSIFICATIONS)->default('unclassified')->required(),
                    ])->columnSpan(2),
                Select::make('user_id')->label('Аккаунт сайта')->relationship('user', 'name')->searchable()->preload()
                    ->helperText('Заполняется, если этому человеку выдан доступ в B2B-кабинет.'),
                Toggle::make('is_primary')->label('Основное контактное лицо'),
                Toggle::make('is_active')->label('Активен')->default(true),
                TextInput::make('external_1c_id')->label('Идентификатор 1С')->maxLength(255),
                Textarea::make('notes')->label('Примечание')->rows(3)->columnSpanFull(),
            ]),
            Section::make('Телефоны и электронная почта')->schema([
                Repeater::make('contactPoints')->relationship()->label('Каналы связи')->columns(5)
                    ->schema([
                        Select::make('type')->label('Тип')->options(['phone' => 'Телефон', 'email' => 'Email'])
                            ->required()->default('phone'),
                        TextInput::make('value')->label('Телефон / email')->required()->maxLength(255)->columnSpan(2),
                        TextInput::make('label')->label('Комментарий')->placeholder('рабочий, бухгалтерия')->maxLength(100),
                        Toggle::make('is_primary')->label('Основной'),
                        Toggle::make('is_active')->label('Активен')->default(true),
                    ])->defaultItems(1)->addActionLabel('Добавить телефон или email')->reorderable(false),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Контакт')->columns(3)->schema([
                TextEntry::make('full_name')->label('Имя')->weight('bold'),
                TextEntry::make('position')->label('Должность')->placeholder('—'),
                TextEntry::make('counterparty.name')->label('Контрагент')->placeholder('Не привязан'),
                TextEntry::make('phones_list')->label('Телефоны')->placeholder('—')->copyable()
                    ->formatStateUsing(fn (?string $state): ?HtmlString => AdminPhoneLinks::renderList($state))->html(),
                TextEntry::make('emails_list')->label('Email')->placeholder('—')->copyable(),
                TextEntry::make('counterparty.responsibleUser.name')->label('Ответственный')->placeholder('Не назначен'),
                TextEntry::make('calls_count')->label('Звонков')->state(fn (CrmContact $record): int => $record->calls()->count()),
                TextEntry::make('notes')->label('Примечание')->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('full_name')->label('Контакт')->searchable()->sortable()->weight('bold')
                ->description(fn (CrmContact $record): ?string => $record->position),
            TextColumn::make('counterparty.name')->label('Контрагент')->searchable()->sortable()
                ->description(fn (CrmContact $record): ?string => $record->counterparty
                    ? (CrmCounterparty::CLASSIFICATIONS[$record->counterparty->classification] ?? null) : null),
            TextColumn::make('phones_list')->label('Телефоны')->searchable(query: fn (Builder $query, string $search): Builder => $query
                ->whereHas('contactPoints', fn (Builder $points): Builder => $points->where('type', 'phone')->where('value', 'like', "%{$search}%")))
                ->copyable()->placeholder('—')
                ->formatStateUsing(fn (?string $state): ?HtmlString => AdminPhoneLinks::renderList($state))->html(),
            TextColumn::make('emails_list')->label('Email')->placeholder('—')->toggleable(),
            TextColumn::make('counterparty.responsibleUser.name')->label('Ответственный')->placeholder('Не назначен'),
            IconColumn::make('is_active')->label('Активен')->boolean(),
            TextColumn::make('updated_at')->label('Обновлено')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
        ])->filters([
            SelectFilter::make('counterparty_id')->label('Контрагент')->relationship('counterparty', 'name')->searchable()->preload(),
        ])->recordActions([ViewAction::make(), EditAction::make()])->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrmContacts::route('/'),
            'create' => CreateCrmContact::route('/create'),
            'view' => ViewCrmContact::route('/{record}'),
            'edit' => EditCrmContact::route('/{record}/edit'),
        ];
    }
}
