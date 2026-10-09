<?php

namespace App\Filament\Supplier\Pages;

use App\Filament\Supplier\Widgets\SupplierCatalogOverview;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Сводка поставщика';

    /** @return array<class-string<Widget>|WidgetConfiguration> */
    public function getWidgets(): array
    {
        return [
            SupplierCatalogOverview::class,
        ];
    }
}
