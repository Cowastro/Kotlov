<?php

namespace App\Filament\Resources\IntegrationCategories;

use App\Filament\Resources\IntegrationCategories\Pages\EditIntegrationCategory;
use App\Filament\Resources\IntegrationCategories\Pages\ListIntegrationCategories;
use App\Filament\Resources\IntegrationProducts\IntegrationProductResource;
use App\Models\IntegrationCategory;
use App\Models\IntegrationSource;
use BackedEnum;
use Filament\Actions\Action;
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
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['source.supplier', 'siteCategory'])
                ->withCount([
                    'products as in_stock_products_count' => fn (Builder $products) => $products->inStock(),
                ]))
            ->columns([
                TextColumn::make('source_partner')->label('Поставщик')
                    ->state(fn (IntegrationCategory $record): string => $record->source?->partnerName() ?? 'Источник не указан')
                    ->description(fn (IntegrationCategory $record): ?string => $record->source
                        ? self::channelLabel($record->source).' · '.$record->source->name
                        : null)
                    ->badge()
                    ->color('warning'),
                TextColumn::make('path')->label('Путь в каталоге поставщика')->searchable()->sortable()->wrap(),
                TextColumn::make('in_stock_products_count')->label('В наличии')->numeric()->sortable(),
                TextColumn::make('catalog_rule')->label('Правило kotlov.by')
                    ->state(fn (IntegrationCategory $record): string => $record->siteCategory?->name ?? 'Не назначено')
                    ->description(fn (IntegrationCategory $record): string => $record->category_id
                        ? 'Применяется только к непривязанным позициям без индивидуальной категории'
                        : 'Структуру сайта не меняет')
                    ->badge()
                    ->color(fn (IntegrationCategory $record): string => $record->category_id ? 'success' : 'gray')
                    ->wrap(),
                TextColumn::make('last_seen_at')->label('Получена')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('integration_source_id')
                    ->label('Поставщик / источник')
                    ->options(fn (): array => IntegrationSource::query()
                        ->with('supplier')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (IntegrationSource $source): array => [
                            $source->id => $source->partnerName().' — '.self::channelLabel($source).' · '.$source->name,
                        ])
                        ->all())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('category_id')->label('Категория kotlov.by')->relationship('siteCategory', 'name'),
            ])
            ->emptyStateIcon(Heroicon::OutlinedFolderMinus)
            ->emptyStateHeading('1С не передала группы номенклатуры')
            ->emptyStateDescription('Товары получены без ссылок на папки. Это не мешает привязке: используйте «Категорию сайта» в разделе «Привязка товаров». Если нужна исходная структура 1С, включите выгрузку групп номенклатуры и повторите обмен.')
            ->defaultSort('path')
            ->recordActions([
                Action::make('products')
                    ->label('Товары группы')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('info')
                    ->url(fn (IntegrationCategory $record): string => IntegrationProductResource::getUrl('index', [
                        'filters' => [
                            'integration_source_id' => ['value' => $record->integration_source_id],
                            'integration_category_id' => ['value' => $record->id],
                        ],
                    ])),
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

    private static function channelLabel(IntegrationSource $source): string
    {
        return match ($source->driver) {
            'commerceml' => '1С / CommerceML',
            'api' => 'API',
            'file' => 'Файл / прайс',
            default => strtoupper((string) $source->driver),
        };
    }
}
