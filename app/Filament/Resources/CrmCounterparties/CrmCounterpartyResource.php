<?php

namespace App\Filament\Resources\CrmCounterparties;

use App\Filament\Resources\CrmCounterparties\Pages\CreateCrmCounterparty;
use App\Filament\Resources\CrmCounterparties\Pages\EditCrmCounterparty;
use App\Filament\Resources\CrmCounterparties\Pages\ListCrmCounterparties;
use App\Filament\Resources\CrmCounterparties\Pages\ViewCrmCounterparty;
use App\Models\CrmCounterparty;
use App\Models\User;
use BackedEnum;
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
                TextInput::make('unp')->label('УНП / ИНН')->maxLength(32),
                Select::make('responsible_user_id')->label('Ответственный')
                    ->options(fn (): array => User::query()->whereIn('role', ['admin', 'manager', 'sales_manager'])
                        ->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->preload(),
                TextInput::make('external_1c_id')->label('Идентификатор 1С')->maxLength(255),
                TextInput::make('website')->label('Сайт')->url()->maxLength(255),
                Textarea::make('legal_address')->label('Адрес')->rows(2)->columnSpanFull(),
                Textarea::make('notes')->label('Общая информация / особенности работы')->rows(4)->columnSpanFull(),
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
                TextEntry::make('responsibleUser.name')->label('Ответственный')->placeholder('Не назначен'),
                TextEntry::make('contacts_count')->label('Контактных лиц')->state(fn (CrmCounterparty $record): int => $record->contacts()->count()),
                TextEntry::make('calls_count')->label('Звонков')->state(fn (CrmCounterparty $record): int => $record->calls()->count()),
                TextEntry::make('external_1c_id')->label('Идентификатор 1С')->placeholder('Не связан'),
                TextEntry::make('notes')->label('Примечание')->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
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
            TextColumn::make('unp')->label('УНП')->searchable()->placeholder('—')->copyable(),
            TextColumn::make('contacts_count')->label('Контакты')->counts('contacts')->sortable(),
            TextColumn::make('responsibleUser.name')->label('Ответственный')->placeholder('Не назначен'),
            TextColumn::make('status')->label('Статус')->badge()
                ->formatStateUsing(fn (string $state): string => CrmCounterparty::STATUSES[$state] ?? $state),
            TextColumn::make('updated_at')->label('Обновлено')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
        ])->filters([
            SelectFilter::make('classification')->label('Категория')->options(CrmCounterparty::CLASSIFICATIONS),
            SelectFilter::make('business_unit')->label('Направление')->options(CrmCounterparty::BUSINESS_UNITS),
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
