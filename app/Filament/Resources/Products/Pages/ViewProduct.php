<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\Products\Actions\CreateCommercialDocumentAction;
use App\Filament\Resources\Products\Actions\ManualPricingAction;
use App\Filament\Resources\Products\Actions\RequestSupplierReserveAction;
use App\Filament\Resources\Products\Pages\Concerns\HasProductMarketResearchActions;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduct extends ViewRecord
{
    use HasProductMarketResearchActions;

    protected static string $resource = ProductResource::class;

    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateCommercialDocumentAction::make(),
            RequestSupplierReserveAction::make(),
            Action::make('openSupplierCorrespondence')
                ->label('Переписка с поставщиком')
                ->icon('heroicon-o-envelope-open')
                ->color('gray')
                ->visible(fn (): bool => $this->record->latestSupplierMailThread !== null)
                ->url(fn (): string => CrmMailThreadResource::getUrl('view', [
                    'record' => $this->record->latestSupplierMailThread,
                ])),
            ManualPricingAction::make(),
            ...$this->productMarketResearchActions(),
            EditAction::make(),
        ];
    }
}
