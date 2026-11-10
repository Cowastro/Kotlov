<?php

namespace App\Filament\Resources\CrmMailingLists;

use App\Filament\Resources\CrmMailingLists\Pages\CreateCrmMailingList;
use App\Filament\Resources\CrmMailingLists\Pages\EditCrmMailingList;
use App\Filament\Resources\CrmMailingLists\Pages\ListCrmMailingLists;
use App\Filament\Resources\CrmMailingLists\RelationManagers\MembersRelationManager;
use App\Models\CrmMailingList;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CrmMailingListResource extends Resource
{
    protected static ?string $model = CrmMailingList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Рассылки';

    protected static ?string $modelLabel = 'список рассылки';

    protected static ?string $pluralModelLabel = 'Рассылки';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'CRM';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Название списка')->required()->maxLength(255)->placeholder('Дилеры ЭкоКамин'),
            Textarea::make('notes')->label('Заметки')->rows(2)->maxLength(2000),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Список')->weight('bold')->searchable(),
            TextColumn::make('members_count')->label('Партнёров')->counts('members'),
            TextColumn::make('updated_at')->label('Обновлён')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
        ])->defaultSort('updated_at', 'desc')->recordActions([EditAction::make()->label('Открыть')]);
    }

    public static function getRelations(): array
    {
        return [MembersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrmMailingLists::route('/'),
            'create' => CreateCrmMailingList::route('/create'),
            'edit' => EditCrmMailingList::route('/{record}/edit'),
        ];
    }
}
