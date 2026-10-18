<?php

namespace App\Filament\Resources\SupplierSyncRuns\Pages;

use App\Filament\Resources\SupplierSyncRuns\SupplierSyncRunResource;
use App\Filament\Widgets\IntegrationHealthOverview;
use App\Filament\Widgets\RecentIntegrationRuns;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListSupplierSyncRuns extends ListRecords
{
    protected static string $resource = SupplierSyncRunResource::class;

    public function getTitle(): string
    {
        return 'Центр синхронизаций';
    }

    public function getSubheading(): ?string
    {
        return 'Единый контроль обмена с 1С и обновлений цен и остатков по всем поставщикам';
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            IntegrationHealthOverview::class,
            RecentIntegrationRuns::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
