<?php

namespace App\Filament\Resources\IntegrationCategories;

use App\Filament\Resources\IntegrationCategories\Pages\EditIntegrationCategory;
use App\Filament\Resources\IntegrationCategories\Pages\ListIntegrationCategories;
use App\Models\IntegrationCategory;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IntegrationCategoryResource extends Resource
{
    protected static ?string $model = IntegrationCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static ?string $navigationLabel = 'Группы поставщиков';

    protected static ?string $modelLabel = 'группа поставщика';

    protected static ?string $pluralModelLabel = 'Группы поставщиков';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return 'Интеграции';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Папка поставщика')
                ->description('Исходное дерево сохраняется без изменения публичного каталога.')
                ->schema([
                    Placeholder::make('source_name')->label('Источник')
                        ->content(fn (?IntegrationCategory $record): string => $record?->source?->name ?? '—'),
                    Placeholder::make('path')->label('Полный путь')
                        ->content(fn (?IntegrationCategory $record): string => $record?->path ?? '—'),
                    Placeholder::make('external_id')->label('Внешний ID')
                        ->content(fn (?IntegrationCategory $record): string => $record?->external_id ?? '—'),
                ])->columns(2),
            Section::make('Соответствие каталогу kotlov.by')
                ->description('Можно назначить существующую категорию сайта. Товары при этом не перемещаются автоматически.')
                ->schema([
                    Select::make('category_id')
                        ->label('Категория kotlov.by')
                        ->relationship('siteCategory', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'products as in_stock_products_count' => fn (Builder $products) => $products->inStock(),
            ]))
            ->columns([
                TextColumn::make('source.name')->label('Источник')->badge()->sortable(),
                TextColumn::make('path')->label('Путь в каталоге поставщика')->searchable()->sortable()->wrap(),
                TextColumn::make('in_stock_products_count')->label('В наличии')->numeric()->sortable(),
                TextColumn::make('siteCategory.name')->label('Категория kotlov.by')->placeholder('Не назначена')->wrap(),
                TextColumn::make('last_seen_at')->label('Получена')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('integration_source_id')->label('Источник')->relationship('source', 'name'),
                SelectFilter::make('category_id')->label('Категория kotlov.by')->relationship('siteCategory', 'name'),
            ])
            ->defaultSort('path')
            ->recordActions([
                EditAction::make()->label('Сопоставить'),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIntegrationCategories::route('/'),
            'edit' => EditIntegrationCategory::route('/{record}/edit'),
        ];
    }
}
