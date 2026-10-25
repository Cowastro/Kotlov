<?php

namespace App\Filament\Resources\ChimneyCalculationRequests\Pages;

use App\Filament\Pages\ChimneyCalculator;
use App\Filament\Resources\ChimneyCalculationRequests\ChimneyCalculationRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewChimneyCalculationRequest extends ViewRecord
{
    protected static string $resource = ChimneyCalculationRequestResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->redirect(ChimneyCalculator::getUrl(['calculation' => $this->record->getKey()]));
    }
}
