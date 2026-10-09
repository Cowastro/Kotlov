<?php

namespace App\Filament\Resources\IntegrationProducts;

use App\Filament\Resources\IntegrationProducts\Pages\EditIntegrationProduct;
use App\Filament\Resources\IntegrationProducts\Pages\ListIntegrationProducts;
use App\Models\IntegrationCategory;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class IntegrationProductResource extends Resource
{
    protected static ?string $model = IntegrationProduct::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Привязка товаров';

    protected static ?string $modelLabel = 'внешний товар';

    protected static ?string $pluralModelLabel = 'Привязка товаров';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return 'Интеграции';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['source', 'integrationCategory', 'product.category'])
            ->inStock();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Товар из внешней системы')
                ->schema([
                    Placeholder::make('source_name')->label('Источник')
                        ->content(fn (?IntegrationProduct $record): string => $record?->source?->name ?? '—'),
                    Placeholder::make('integration_category')
                        ->label('Группа источника')
                        ->content(fn (?IntegrationProduct $record): string => $record?->integrationCategory?->path ?? '—'),
                    TextInput::make('external_id')->label('Внешний ID')->disabled()->dehydrated(false),
                    TextInput::make('external_code')->label('Код 1С')->disabled()->dehydrated(false),
                    TextInput::make('external_sku')->label('Артикул')->disabled()->dehydrated(false),
                    TextInput::make('barcode')->label('Штрихкод')->disabled()->dehydrated(false),
                    TextInput::make('name')->label('Название')->disabled()->dehydrated(false)->columnSpanFull(),
                ])->columns(2),
            Section::make('Карточка kotlov.by')
                ->description('Выбор товара создаёт постоянную ручную привязку. Цена и остаток при этом не изменяются.')
                ->schema([
                    Select::make('product_id')
                        ->label('Товар сайта')
                        ->relationship('product', 'name')
                        ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->sku} — {$record->name}")
                        ->searchable(['sku', 'name'])
                        ->nullable(),
                    TextInput::make('match_status')->label('Текущий статус')->disabled()->dehydrated(false),
                    TextInput::make('match_method')->label('Метод')->disabled()->dehydrated(false),
                    TextInput::make('match_confidence')->label('Уверенность')->disabled()->dehydrated(false),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_partner')->label('Поставщик')
                    ->state(fn (IntegrationProduct $record): string => $record->source?->partnerName() ?? 'Источник не указан')
                    ->description(fn (IntegrationProduct $record): ?string => $record->source?->name)
                    ->badge()
                    ->color('warning')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy(
                            IntegrationSource::query()
                                ->select('name')
                                ->whereColumn('integration_sources.id', 'integration_products.integration_source_id'),
                            $direction
                        ))
                    ->size(TextSize::ExtraSmall),
                TextColumn::make('integrationCategory.path')->label('Группа источника')
                    ->searchable()->sortable()->limit(45)
                    ->tooltip(fn (IntegrationProduct $record): ?string => $record->integrationCategory?->path)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')->label('Товар во внешней системе')
                    ->searchable(['name', 'external_sku', 'external_code', 'external_id'])
                    ->limit(58)->lineClamp(2)->size(TextSize::Small)
                    ->tooltip(fn (IntegrationProduct $record): string => $record->name)
                    ->description(fn (IntegrationProduct $record): string => collect([
                        $record->external_sku ? "арт. {$record->external_sku}" : null,
                        $record->external_code ? "код {$record->external_code}" : null,
                    ])->filter()->implode(' · ') ?: $record->external_id),
                TextColumn::make('product.name')->label('Карточка сайта')->searchable()
                    ->state(fn (IntegrationProduct $record): ?string => $record->product?->name
                        ?? ($record->candidates[0]['name'] ?? null))
                    ->limit(48)->lineClamp(2)->size(TextSize::Small)
                    ->tooltip(fn (IntegrationProduct $record): ?string => $record->product?->name
                        ?? ($record->candidates[0]['name'] ?? null))
                    ->url(fn (IntegrationProduct $record): ?string => self::productUrl($record))
                    ->openUrlInNewTab()
                    ->icon(fn (IntegrationProduct $record): ?string => $record->product
                        ? 'heroicon-o-arrow-top-right-on-square'
                        : null)
                    ->color(fn (IntegrationProduct $record): string => $record->product ? 'primary' : 'gray')
                    ->description(fn (IntegrationProduct $record): ?string => $record->product?->sku
                        ?? ($record->candidates[0]['sku'] ?? null)),
                TextColumn::make('match_status')->label('Статус')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'matched' => 'Привязан',
                        'suggested' => 'Предложение',
                        'ambiguous' => 'Несколько вариантов',
                        'ignored' => 'Игнорируется',
                        default => 'Не найден',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'matched' => 'success',
                        'suggested' => 'info',
                        'ambiguous' => 'warning',
                        'ignored' => 'gray',
                        default => 'danger',
                    }),
                TextColumn::make('match_confidence')->label('Совпадение')->formatStateUsing(
                    fn ($state): string => $state === null ? '—' : round((float) $state * 100).'%'
                ),
                TextColumn::make('customer_price')->label('Цена клиенту с НДС')
                    ->state(fn (IntegrationProduct $record): float => $record->source
                        ? $record->source->priceIncludingTax((float) $record->price)
                        : (float) $record->price)
                    ->money('BYN')
                    ->description(fn (IntegrationProduct $record): string => sprintf(
                        'Из 1С: %s BYN %s%s',
                        number_format((float) $record->price, 2, '.', ' '),
                        $record->source?->sourcePriceTaxLabel() ?? 'режим не указан',
                        $record->source?->priceTaxMode() === IntegrationSource::PRICE_TAX_EXCLUSIVE
                            ? ' · НДС +'.number_format($record->source->vatRate(), 0).'%'
                            : ''
                    ))
                    ->size(TextSize::Small),
                TextColumn::make('stock_quantity')->label('Остаток 1С')->numeric()->toggleable()
                    ->size(TextSize::Small),
                TextColumn::make('last_seen_at')->label('Получен')->dateTime('d.m.Y H:i')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('integration_source_id')
                    ->label('Поставщик / источник')
                    ->placeholder('Все поставщики')
                    ->options(fn (): array => IntegrationSource::query()
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (IntegrationSource $source): array => [
                            $source->getKey() => $source->partnerName().' — '.$source->name,
                        ])
                        ->all())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('integration_category_id')->label('Группа поставщика')
                    ->placeholder('Все группы')
                    ->options(fn (): array => IntegrationCategory::query()->orderBy('path')->pluck('path', 'id')->all())
                    ->searchable(),
                SelectFilter::make('match_status')
                    ->label('Статус привязки')
                    ->placeholder('Все статусы')
                    ->options([
                        'matched' => 'Привязан',
                        'suggested' => 'Предложение',
                        'ambiguous' => 'Несколько вариантов',
                        'unmatched' => 'Не найден',
                        'ignored' => 'Не для сайта',
                    ]),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->defaultSort('last_seen_at', 'desc')
            ->recordActions([
                Action::make('acceptSuggestion')
                    ->label('Принять')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (IntegrationProduct $record): bool => $record->match_status === 'suggested'
                        && filled($record->candidates[0]['product_id'] ?? null))
                    ->requiresConfirmation()
                    ->action(function (IntegrationProduct $record): void {
                        $record->update([
                            'product_id' => $record->candidates[0]['product_id'],
                            'match_status' => 'matched',
                            'match_method' => 'manual_suggestion',
                            'match_confidence' => 1,
                            'matched_at' => now(),
                        ]);
                    }),
                EditAction::make()->label('Выбрать вручную'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('acceptSuggestions')
                        ->label('Принять выбранные предложения')
                        ->icon(Heroicon::OutlinedCheck)
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(function (IntegrationProduct $record): void {
                                $productId = $record->candidates[0]['product_id'] ?? null;
                                if ($record->match_status !== 'suggested' || ! $productId) {
                                    return;
                                }

                                $record->update([
                                    'product_id' => $productId,
                                    'match_status' => 'matched',
                                    'match_method' => 'manual_suggestion',
                                    'match_confidence' => 1,
                                    'matched_at' => now(),
                                ]);
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('ignore')
                        ->label('Пометить как не относящиеся к сайту')
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update([
                            'product_id' => null,
                            'match_status' => 'ignored',
                            'match_method' => 'manual_ignore',
                            'match_confidence' => null,
                            'matched_at' => null,
                        ]))
                        ->deselectRecordsAfterCompletion(),
                ]),
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
            'index' => ListIntegrationProducts::route('/'),
            'edit' => EditIntegrationProduct::route('/{record}/edit'),
        ];
    }

    private static function productUrl(IntegrationProduct $record): ?string
    {
        $categorySlug = $record->product?->category?->slug;
        $productSlug = $record->product?->slug;

        if (! $categorySlug || ! $productSlug) {
            return null;
        }

        return url('/'.$categorySlug.'/'.$productSlug);
    }
}
