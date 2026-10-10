<?php

namespace App\Filament\Supplier\Resources\IntegrationProducts\Pages;

use App\Filament\Supplier\Resources\IntegrationProducts\IntegrationProductResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListIntegrationProducts extends ListRecords
{
    protected static string $resource = IntegrationProductResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTitle(): string
    {
        return 'Товары из 1С / API';
    }

    public function getSubheading(): ?string
    {
        return 'Исходные цены, остатки и связь с карточками KOTLOV. Доступны только источники назначенных вам поставщиков.';
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return IntegrationProductResource::getEloquentQuery()
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('product_id')
                ->orWhere(fn (Builder $query): Builder => $query->withoutUsablePrice()))
            ->exists() ? 'attention' : 'all';
    }

    public function getTabs(): array
    {
        $query = fn (): Builder => IntegrationProductResource::getEloquentQuery();
        $attention = fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
            ->whereNull('product_id')
            ->orWhere(fn (Builder $query): Builder => $query->withoutUsablePrice()));

        return [
            'attention' => Tab::make('Требуют внимания')
                ->modifyQueryUsing($attention)
                ->badge($attention($query())->count())
                ->badgeColor('danger'),
            'all' => Tab::make('Все')->badge($query()->count()),
            'in-stock' => Tab::make('В наличии')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->inStock())
                ->badge($query()->inStock()->count())
                ->badgeColor('success'),
            'linked' => Tab::make('Привязаны')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotNull('product_id'))
                ->badge($query()->whereNotNull('product_id')->count())
                ->badgeColor('success'),
            'unlinked' => Tab::make('Не привязаны')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNull('product_id'))
                ->badge($query()->whereNull('product_id')->count())
                ->badgeColor('warning'),
            'missing-price' => Tab::make('Без цены')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutUsablePrice())
                ->badge($query()->withoutUsablePrice()->count())
                ->badgeColor('danger'),
        ];
    }
}
