<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Crm\AdminTodayDashboard;
use Filament\Widgets\Widget;

class CrmTodayCenter extends Widget
{
    public string $period = '7d';

    public string $status = 'open';

    public string $sortOrder = 'desc';

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.crm-today-center';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -100;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $viewer = auth()->user();

        return app(AdminTodayDashboard::class)->data(
            period: $this->period,
            status: $this->status,
            sort: $this->sortOrder,
            viewer: $viewer instanceof User ? $viewer : null,
        );
    }
}
