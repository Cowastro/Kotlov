<?php

namespace App\Filament\Supplier\Widgets;

use App\Models\SupplierProduct;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SupplierCatalogOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $supplierIds = auth()->user()?->suppliers()->pluck('suppliers.id') ?? collect();
        $products = SupplierProduct::query()->whereIn('supplier_id', $supplierIds);

        return [
            Stat::make('Позиций', (clone $products)->count())
                ->description('В назначенных вам каталогах'),
            Stat::make('В наличии', (clone $products)->where('stock_quantity', '>', 0)->count())
                ->color('success'),
            Stat::make('Не привязаны', (clone $products)->whereNull('product_id')->count())
                ->description('Нужна проверка сопоставления')
                ->color('warning'),
        ];
    }
}
