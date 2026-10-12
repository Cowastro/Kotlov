<?php

namespace App\Filament\Resources\TelephonyEmployees;

use App\Filament\Resources\TelephonyEmployees\Pages\EditTelephonyEmployee;
use App\Filament\Resources\TelephonyEmployees\Pages\ListTelephonyEmployees;
use App\Models\TelephonyEmployee;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelephonyEmployeeResource extends Resource
{
    protected static ?string $model = TelephonyEmployee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Менеджеры телефонии';

    protected static ?string $modelLabel = 'сотрудник телефонии';

    protected static ?string $pluralModelLabel = 'Менеджеры телефонии';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Телефония';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Привязка сотрудника A1')->columns(2)->schema([
                Select::make('user_id')->label('Пользователь KOTLOV')
                    ->options(fn (): array => User::query()->whereIn('role', ['admin', 'manager', 'sales_manager'])
                        ->orderBy('name')->pluck('name', 'id')->all())->searchable()->nullable()
                    ->helperText('По этой связи звонок назначается правильному менеджеру.'),
                Toggle::make('is_active')->label('Участвует в телефонии'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Сотрудник A1')->searchable()->sortable()->weight('bold'),
            TextColumn::make('short_number')->label('Внутренний')->placeholder('—')->copyable(),
            TextColumn::make('full_number')->label('Полный номер')->placeholder('—')->copyable(),
            TextColumn::make('group_name')->label('Отдел')->placeholder('—')->searchable(),
            TextColumn::make('user.name')->label('Пользователь KOTLOV')->placeholder('Не привязан'),
            IconColumn::make('is_active')->label('Активен')->boolean(),
            TextColumn::make('last_synced_at')->label('Синхронизация')->dateTime('d.m.Y H:i', 'Europe/Minsk')->placeholder('—'),
        ])->recordActions([EditAction::make()])->defaultSort('name');
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
            'index' => ListTelephonyEmployees::route('/'),
            'edit' => EditTelephonyEmployee::route('/{record}/edit'),
        ];
    }
}
