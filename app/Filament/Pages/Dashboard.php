<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CrmTodayCenter;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Центр управления';

    public function getColumns(): int|array
    {
        return 1;
    }

    /** @return array<class-string<Widget>|WidgetConfiguration> */
    public function getWidgets(): array
    {
        return [
            CrmTodayCenter::class,
        ];
    }
}
