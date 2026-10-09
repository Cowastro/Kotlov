<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\IntegrationHealthOverview;
use App\Filament\Widgets\RecentIntegrationRuns;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Центр управления';

    public function getColumns(): int|array
    {
        return [
            'md' => 1,
            'xl' => 2,
        ];
    }

    /** @return array<class-string<Widget>|WidgetConfiguration> */
    public function getWidgets(): array
    {
        return [
            IntegrationHealthOverview::class,
            RecentIntegrationRuns::class,
        ];
    }
}
