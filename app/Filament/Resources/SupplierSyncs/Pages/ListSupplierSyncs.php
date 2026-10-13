<?php

namespace App\Filament\Resources\SupplierSyncs\Pages;

use App\Filament\Resources\SupplierSyncs\SupplierSyncResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListSupplierSyncs extends ListRecords
{
    protected static string $resource = SupplierSyncResource::class;

    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }

    public function getTitle(): string
    {
        return 'Технический реестр импортов';
    }

    public function getSubheading(): ?string
    {
        return 'Архив конфигураций старых адаптеров. Запуски и редактирование здесь отключены; актуальные результаты находятся в журнале синхронизаций.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('journal')
                ->label('Журнал синхронизаций')
                ->icon('heroicon-o-clock')
                ->color('primary')
                ->url('/admin/supplier-sync-runs'),

            Action::make('sources')
                ->label('Источники данных')
                ->icon('heroicon-o-circle-stack')
                ->color('gray')
                ->url('/admin/integration-sources'),
        ];
    }
}
