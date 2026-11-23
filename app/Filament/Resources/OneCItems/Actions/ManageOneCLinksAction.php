<?php

namespace App\Filament\Resources\OneCItems\Actions;

use App\Models\OneCItem;
use App\Models\Product;
use App\Services\Integrations\OneCBridge\OneCItemLinks;
use App\Services\StorefrontProductSearch;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;

class ManageOneCLinksAction
{
    public static function make(): Action
    {
        return Action::make('manage_links')
            ->label(fn (OneCItem $record): string => $record->characteristic_count ? 'Характеристики и привязки' : ($record->product_id ? 'Изменить привязку' : 'Привязать'))
            ->icon('heroicon-o-link')->color('primary')
            ->visible(fn (OneCItem $record): bool => ! $record->is_folder && ! $record->deletion_mark && (bool) auth()->user()?->isManager())
            ->modalHeading(fn (OneCItem $record): string => $record->code.' — '.$record->description)
            ->modalDescription('Выберите существующую карточку сайта для каждого исполнения. Очистите поле, чтобы отвязать. Цены и остатки не меняются; в 1С ничего не записывается.')
            ->modalWidth(Width::FourExtraLarge)->modalSubmitActionLabel('Сохранить привязки')
            ->fillForm(fn (OneCItem $record): array => app(OneCItemLinks::class)->formData($record))
            ->schema([
                Hidden::make('snapshot'),
                Repeater::make('links')->hiddenLabel()->addable(false)->deletable(false)->reorderable(false)
                    ->schema([
                        Hidden::make('external_id'), Hidden::make('name'), Hidden::make('article'), Hidden::make('current'),
                        Placeholder::make('onec_name')->label('Товар / характеристика 1С')
                            ->content(fn (Get $get): string => $get('name').' · Артикул: '.($get('article') ?: '—')),
                        Placeholder::make('previous')->label('Сейчас привязано')
                            ->content(fn (Get $get): string => $get('current') ?: 'Не привязано'),
                        Select::make('product_id')->label('Карточка сайта')->placeholder('Найдите по названию или артикулу')
                            ->searchable()->nullable()->live()->searchDebounce(400)
                            ->getSearchResultsUsing(fn (string $search): array => self::search($search))
                            ->getOptionLabelUsing(fn ($value): ?string => ($product = Product::find($value)) ? $product->sku.' — '.$product->name : null),
                        Placeholder::make('selected')->label('Выбрано')
                            ->content(fn (Get $get) => view('filament.onec-items.selected-product', ['product' => $get('product_id') ? Product::with('category')->find($get('product_id')) : null])),
                    ]),
                Placeholder::make('history')->label('Последние изменения')
                    ->content(fn (OneCItem $record) => view('filament.onec-items.link-history', ['changes' => DB::table('onec_item_link_changes as changes')
                        ->leftJoin('users', 'users.id', '=', 'changes.user_id')
                        ->where(fn ($q) => $q->where('external_id', $record->ref_key)->orWhere('external_id', 'like', $record->ref_key.'#%'))
                        ->orderByDesc('changes.id')->limit(10)->get(['changes.*', 'users.name as user_name'])])),
            ])
            ->action(function (OneCItem $record, array $data): void {
                app(OneCItemLinks::class)->save($record, $data, auth()->user());
                Notification::make()->success()->title('Привязки сохранены в обоих разделах')->send();
            });
    }

    public static function search(string $search): array
    {
        if (mb_strlen(trim($search)) < 2) {
            return [];
        }
        $query = Product::query()->where('is_archived', false);
        app(StorefrontProductSearch::class)->apply($query, trim($search), includeInternal: true);

        return $query->limit(30)->get(['id', 'sku', 'name'])->mapWithKeys(fn ($p) => [$p->id => $p->sku.' — '.$p->name])->all();
    }
}
