<?php

namespace App\Filament\Supplier\Resources\SupplierProducts\Pages;

use App\Filament\Supplier\Resources\SupplierProducts\SupplierProductResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListSupplierProducts extends ListRecords
{
    protected static string $resource = SupplierProductResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTitle(): string
    {
        return 'Мои товары';
    }

    public function getSubheading(): ?string
    {
        return 'Цены, остатки, актуальность и связь с карточками KOTLOV. Вкладки могут пересекаться; доступны только назначенные вам поставщики.';
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return SupplierProductResource::getEloquentQuery()->needsAttention()->exists()
            ? 'attention'
            : 'all';
    }

    public function getTabs(): array
    {
        $query = fn (): Builder => SupplierProductResource::getEloquentQuery();

        return [
            'attention' => Tab::make('Требуют внимания')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->needsAttention())
                ->badge($query()->needsAttention()->count())
                ->badgeColor('danger'),
            'all' => Tab::make('Все')
                ->badge($query()->count()),
            'in-stock' => Tab::make('В наличии')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->available())
                ->badge($query()->available()->count())
                ->badgeColor('success'),
            'unlinked' => Tab::make('Не привязаны')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNull('product_id'))
                ->badge($query()->whereNull('product_id')->count())
                ->badgeColor('warning'),
            'stale' => Tab::make('Устарели')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->stale())
                ->badge($query()->stale()->count())
                ->badgeColor('danger'),
        ];
    }
}
