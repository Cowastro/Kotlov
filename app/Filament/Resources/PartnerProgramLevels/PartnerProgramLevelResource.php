<?php

namespace App\Filament\Resources\PartnerProgramLevels;

use App\Models\PartnerProgramLevel;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PartnerProgramLevelResource extends Resource
{
    protected static ?string $model = PartnerProgramLevel::class;

    protected static ?string $pluralModelLabel = 'Уровни партнёрской программы';

    public static function getNavigationGroup(): ?string
    {
        return 'B2B';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('rank')->label('Порядок')->required()->integer()
                ->minValue(1)->maxValue(4)->unique(ignoreRecord: true)->disabledOn('edit'),
            TextInput::make('name')->label('Название')->required()->maxLength(255),
            Toggle::make('is_active')->label('Активен')->default(true),
            Textarea::make('benefits')->label('Выгоды для компании')->required(),
            Textarea::make('installer_benefits')->label('Дополнительные выгоды для монтажников'),
            Repeater::make('rules')->label('Условия перехода (выполнить все)')
                ->defaultItems(0)->schema([
                    Select::make('metric')->label('Показатель')->options(PartnerProgramLevel::METRICS)->required()->live(),
                    TextInput::make('months')->label('Скользящий период, мес.')->required()->integer()->minValue(1),
                    TextInput::make('target')->label('Цель')->required()->numeric()->minValue(0)
                        ->rules(fn ($get) => [$get('metric') === 'orders' ? 'integer' : 'decimal:0,2']),
                ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('rank')->label('Порядок'),
            TextColumn::make('name')->label('Название'),
        ])->defaultSort('rank')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLevels::route('/'),
            'create' => Pages\CreateLevel::route('/create'),
            'edit' => Pages\EditLevel::route('/{record}/edit'),
        ];
    }
}
