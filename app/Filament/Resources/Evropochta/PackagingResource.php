<?php

namespace App\Filament\Resources\Evropochta;

use App\Filament\Resources\Evropochta\Pages\ListPackagingProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Services\Shipping\EvropochtaPackaging;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PackagingResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = Product::class;

    protected static ?string $slug = 'evropochta-packaging';

    protected static ?string $navigationLabel = 'Упаковка для Европочты';

    protected static ?string $pluralModelLabel = 'Упаковка для Европочты';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    public static function getNavigationGroup(): ?string
    {
        return 'Доставки';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->active()->notArchived()->with('attributeValues');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->maxSelectableRecords(100)
            ->columns([
                TextColumn::make('sku')->label('Артикул')->searchable(),
                TextColumn::make('name')->label('Товар')->searchable()->wrap(),
                TextColumn::make('packaging_status')->label('Статус')->badge()
                    ->state(fn (Product $record): string => array_filter($record->shipping_parcel ?? []) !== [] ? 'Заполнено'
                        : (static::suggestion($record) ? 'К подтверждению' : 'Нужны данные')),
                TextColumn::make('product_measurements')->label('Данные карточки')
                    ->state(function (Product $record): ?string {
                        $parcel = app(EvropochtaPackaging::class)->measurements($record);
                        if (! isset($parcel['item_weight'], $parcel['item_dimensions'])) {
                            return null;
                        }

                        $source = $parcel['source'] === 'factory' ? 'Заводская упаковка' : 'Товар';

                        return $source.' · '.implode(' × ', $parcel['item_dimensions']).' см · '.$parcel['item_weight'].' кг';
                    })->placeholder('—')->wrap(),
                TextColumn::make('parcel_measurements')->label('Посылка')
                    ->state(function (Product $record): ?string {
                        $parcel = array_filter($record->shipping_parcel ?? []) ?: static::suggestion($record);
                        if (! isset($parcel['length'], $parcel['width'], $parcel['height'], $parcel['weight'])) {
                            return null;
                        }

                        $weight = (($parcel['weight_mode'] ?? null) === 'band' ? 'до ' : '').$parcel['weight'];

                        return "{$parcel['length']} × {$parcel['width']} × {$parcel['height']} см · {$weight} кг";
                    })->placeholder('—')->wrap(),
            ])
            ->filters([
                SelectFilter::make('category')->label('Категория')->relationship('category', 'name')->searchable(),
                TernaryFilter::make('packaging_filled')->label('Данные посылки')
                    ->trueLabel('Заполнено')->falseLabel('Не заполнено')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('shipping_parcel'),
                        false: fn (Builder $query) => $query->whereNull('shipping_parcel'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->recordActions([
                Action::make('editPackaging')->label('Карточка товара')
                    ->authorize(fn (Product $record): bool => ProductResource::canEdit($record))
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([
                BulkAction::make('approvePackaging')->label('Подтвердить коробки')
                    ->authorizeIndividualRecords(fn (Product $record): bool => ProductResource::canEdit($record))
                    ->requiresConfirmation()
                    ->modalDescription('Проверьте, что товар помещается с наполнителем, а готовая посылка укладывается в указанный вес. Подтверждение включает доставку для подходящих розничных заказов. Уже заполненные данные сохранятся.')
                    ->action(function (Collection $records, EvropochtaPackaging $packaging): void {
                        $approved = $records->filter(fn (Product $record): bool => $packaging->approve($record))->count();
                        Notification::make()->title("Подтверждено: {$approved}")
                            ->body('Пропущено: '.($records->count() - $approved))
                            ->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    private static function suggestion(Product $record): ?array
    {
        return app(EvropochtaPackaging::class)->suggest($record);
    }

    public static function getPages(): array
    {
        return ['index' => ListPackagingProducts::route('/')];
    }
}
