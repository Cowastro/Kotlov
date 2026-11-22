<?php

namespace App\Filament\Resources\OneCItems;

use App\Filament\Resources\OneCItems\Pages\ListOneCItems;
use App\Filament\Resources\Products\ProductResource;
use App\Models\OneCItem;
use App\Services\Integrations\OneCBridge\OneCNomenclature;
use App\Services\Integrations\OneCBridge\OneCReferences;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
                TextColumn::make('article')->label('Артикул')->searchable()->toggleable()->fontFamily('mono'),
                TextColumn::make('kind')->label('Вид номенклатуры')->toggleable()
                    ->state(fn (OneCItem $r): ?string => $r->kind_key ? $refs->label('item_kind', $r->kind_key) : null),
                TextColumn::make('product.name')->label('Карточка на сайте')->wrap()->placeholder('не привязан')
                    ->url(fn (OneCItem $r): ?string => $r->product_id ? ProductResource::getUrl('edit', ['record' => $r->product_id]) : null)
                    ->color(fn (OneCItem $r): string => $r->product_id ? 'success' : 'gray'),
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
                        'yes' => $query->whereNotNull('product_id'),
                        'no' => $query->whereNull('product_id'),
                        default => $query,
                    }),
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
            ->defaultSort('description')
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return ['index' => ListOneCItems::route('/')];
    }
}
