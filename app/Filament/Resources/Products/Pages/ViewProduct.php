<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\Actions\CreateCommercialDocumentAction;
use App\Filament\Resources\Products\Actions\ManualPricingAction;
use App\Filament\Resources\Products\Pages\Concerns\HasProductMarketResearchActions;
use App\Filament\Resources\Products\ProductResource;
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
            ManualPricingAction::make(),
            ...$this->productMarketResearchActions(),
            EditAction::make(),
        ];
    }
}
