<?php

namespace App\Filament\Pages;

use App\Filament\Resources\OneCAgentTasks\OneCAgentTaskResource;
use App\Filament\Resources\OneCItems\OneCItemResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Services\Integrations\OneCIntegrationOverview;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class OneCIntegration extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Интеграция с 1С';

    protected static ?string $title = 'Интеграция с 1С';

    protected static ?string $slug = 'onec-integration';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.onec-integration';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isManager();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Интеграции';
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getViewData(): array
    {
        return ['overview' => app(OneCIntegrationOverview::class)->snapshot(),
            'itemsUrl' => OneCItemResource::getUrl(),
            'ordersUrl' => OrderResource::getUrl('index', ['tableFilters' => ['onec_order' => ['value' => 'in']]]),
            'bridgeUrl' => OneCAgentTaskResource::getUrl(),
            'fileSetupUrl' => auth()->user()?->isAdmin() ? OneCSetup::getUrl() : null,
        ];
    }
}
