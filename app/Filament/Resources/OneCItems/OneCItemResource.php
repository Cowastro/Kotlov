<?php

namespace App\Filament\Resources\OneCItems;

use App\Filament\Resources\OneCItems\Actions\ManageOneCLinksAction;
use App\Filament\Resources\OneCItems\Pages\ListOneCItems;
use App\Filament\Resources\OneCPriceBatches\OneCPriceBatchResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\OneCItem;
use App\Services\Integrations\OneCBridge\OneCItemCosts;
use App\Services\Integrations\OneCBridge\OneCNomenclature;
use App\Services\Integrations\OneCBridge\OneCReferences;
use App\Services\Integrations\OneCBridge\OneCStock;
use App\Services\Integrations\OneCPriceBatches;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** «Номенклатура 1С»: every 1C item with its group and the site card it is linked to. Read-only. */
class OneCItemResource extends Resource
{
    protected static ?string $model = OneCItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Номенклатура 1С';

    protected static ?string $modelLabel = 'товар 1С';

    protected static ?string $pluralModelLabel = 'Номенклатура 1С';

    protected static ?string $slug = 'onec-items';

    protected static ?int $navigationSort = 13;

    public static function getNavigationGroup(): ?string
    {
        return 'Интеграции';
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $refs = app(OneCReferences::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('product:id,name'))
            ->columns([
                TextColumn::make('code')->label('Код')->searchable()->sortable()->copyable()->fontFamily('mono')
                    ->icon(fn (OneCItem $r): ?string => $r->is_folder ? 'heroicon-o-folder' : null),
                TextColumn::make('description')->label('Наименование')->searchable()->sortable()->wrap()
                    ->weight(fn (OneCItem $r): string => $r->is_folder ? 'bold' : 'normal')
                    ->description(fn (OneCItem $r): ?string => $r->path),
                TextColumn::make('stock_on_hand')->label('Основной: своб.')->sortable()->toggleable()
                    ->state(fn (OneCItem $record): ?string => $record->is_folder || ! OneCStock::fresh($record) ? null : OneCStock::label($record))
                    ->placeholder('—'),
                TextColumn::make('cost_gross')->label('Себестоимость 1С')->numeric(2)->alignRight()->sortable()->toggleable()->placeholder('—')
                    ->description(fn (OneCItem $r): ?string => $r->cost_gross ? ($r->cost_kind === OneCItemCosts::AVERAGE ? 'средняя 3 мес.' : $r->cost_date?->format('d.m.Y')) : null),
                TextColumn::make('retail_1c')->label('Розница 1С')->alignRight()->toggleable()
                    ->state(fn (OneCItem $r): ?float => $r->is_folder ? null : OneCItemCosts::retail1c($r))->numeric(2)->placeholder('нет цены')
                    ->description(fn (OneCItem $r): ?string => $r->is_folder ? null : OneCItemCosts::markup(OneCItemCosts::retail1c($r), $r))
                    ->color(fn (OneCItem $r): ?string => ($p = OneCItemCosts::retail1c($r)) && $r->cost_gross && $p < $r->cost_gross ? 'danger' : null),
                TextColumn::make('article')->label('Артикул')->searchable()->toggleable()->fontFamily('mono'),
                TextColumn::make('kind')->label('Вид номенклатуры')->toggleable()
                    ->state(fn (OneCItem $r): ?string => $r->kind_key ? $refs->label('item_kind', $r->kind_key) : null),
                TextColumn::make('product.name')->label('Карточка на сайте')->wrap()
                    ->placeholder(fn (OneCItem $r): string => $r->characteristic_count ? 'По характеристикам: '.$r->site_link_count.' / '.$r->characteristic_count : 'Не привязана')
                    ->url(fn (OneCItem $r): ?string => $r->product_id ? ProductResource::getUrl('edit', ['record' => $r->product_id]) : null)
                    ->color(fn (OneCItem $r): string => $r->product_id ? 'success' : 'gray'),
                TextColumn::make('link_status')->label('Привязка')->badge()
                    ->state(fn (OneCItem $r): string => $r->is_folder ? 'Группа' : ($r->link_conflict ? 'Конфликт' : ($r->characteristic_count ? 'Характеристики: '.$r->site_link_count.' / '.$r->characteristic_count : ($r->product_id ? 'Привязана' : 'Не привязана'))))
                    ->color(fn (OneCItem $r): string => $r->link_conflict ? 'danger' : ($r->site_link_count ? 'success' : 'gray')),
                IconColumn::make('created_from_site')->label('Создан с сайта')->boolean()->trueIcon('heroicon-o-sparkles')->falseIcon('')->toggleable(),
                TextColumn::make('same_name_count')->label('Дубли')->badge()->color('danger')
                    ->state(fn (OneCItem $r): ?string => $r->same_name_count > 1 ? '×'.$r->same_name_count : null)->toggleable(),
                IconColumn::make('deletion_mark')->label('Удалён')->boolean()->trueIcon('heroicon-o-trash')->falseIcon('')->trueColor('danger')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('group')->label('Группа (с подгруппами)')->searchable()
                    ->options(fn (): array => app(OneCNomenclature::class)->folderOptions())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereIn('parent_key', app(OneCNomenclature::class)->folderWithChildren($data['value'])) : $query),
                SelectFilter::make('linked')->label('Связь с сайтом')
                    ->options(['yes' => 'Привязан к карточке', 'no' => 'Не привязан'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'yes' => $query->where(fn ($q) => $q->whereNotNull('product_id')->orWhere('site_link_count', '>', 0)),
                        'no' => $query->whereNull('product_id')->where('site_link_count', 0),
                        default => $query,
                    }),
                Filter::make('link_conflicts')->label('Конфликты привязок')->query(fn (Builder $query): Builder => $query->where('link_conflict', true)),
                Filter::make('with_stock')->label('Есть свободный остаток')->query(fn (Builder $query): Builder => $query->where('stock_synced_at', '>=', now()->subHours(3))->whereRaw('stock_on_hand > stock_reserved')),
                Filter::make('no_price')->label('Нет цены в 1С')->query(fn (Builder $query): Builder => $query->where('is_folder', false)
                    ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('integration_products')
                        ->join('integration_sources', 'integration_sources.id', '=', 'integration_products.integration_source_id')
                        ->where('integration_sources.code', 'onec')->where('integration_products.price_byn', '>', 0)
                        ->where(fn ($w) => $w->whereColumn('integration_products.external_id', 'onec_items.ref_key')
                            ->orWhereRaw('integration_products.external_id like '.(DB::connection()->getDriverName() === 'sqlite'
                                ? "onec_items.ref_key || '#%'" : "concat(onec_items.ref_key, '#%')"))))),
                Filter::make('with_cost')->label('Есть себестоимость')->query(fn (Builder $query): Builder => $query->where('cost_gross', '>', 0)),
                Filter::make('characteristics')->label('С характеристиками')->query(fn (Builder $query): Builder => $query->where('characteristic_count', '>', 0)),
                Filter::make('from_site')->label('Созданы с сайта')->query(fn (Builder $query): Builder => $query->where('created_from_site', true)),
                Filter::make('duplicates')->label('Одинаковые названия в группе')->query(fn (Builder $query): Builder => $query->where('same_name_count', '>', 1)),
                TernaryFilter::make('is_folder')->label('Группы')->placeholder('Только товары')->trueLabel('Только группы')->falseLabel('Товары и группы')
                    ->queries(true: fn (Builder $query) => $query->where('is_folder', true), false: fn (Builder $query) => $query,
                        blank: fn (Builder $query) => $query->where('is_folder', false)),
                TernaryFilter::make('deletion_mark')->label('Помеченные на удаление')->placeholder('Без помеченных')
                    ->trueLabel('Только помеченные')->falseLabel('Все')
                    ->queries(true: fn (Builder $query) => $query->where('deletion_mark', true), false: fn (Builder $query) => $query,
                        blank: fn (Builder $query) => $query->where('deletion_mark', false)),
            ])
            ->recordActions([ManageOneCLinksAction::make()])
            ->toolbarActions([
                BulkAction::make('setPrices')->label('Задать цены по наценке')->icon('heroicon-o-calculator')
                    ->modalHeading('Цены от себестоимости 1С')
                    ->modalDescription('Розница и опт = себестоимость 1С с НДС + наценка, до рубля. Товары попадут в черновик «Цены для 1С»: там цены можно поправить вручную и отправить в 1С.')
                    ->schema([
                        TextInput::make('retail')->label('Наценка розницы, %')->numeric()->required()->default(35),
                        TextInput::make('wholesale')->label('Наценка опта, %')->numeric()->required()->default(15),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $batch = app(OneCPriceBatches::class)->addFromItems($records, (float) $data['retail'], (float) $data['wholesale'], auth()->user());
                        Notification::make()->success()->title('В черновике цен: '.$batch->items_count.' товаров')->body('Проверьте цены и нажмите «Отправить в 1С».')->send();

                        return redirect(OneCPriceBatchResource::getUrl('view', ['record' => $batch]));
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('description')
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return ['index' => ListOneCItems::route('/')];
    }
}
