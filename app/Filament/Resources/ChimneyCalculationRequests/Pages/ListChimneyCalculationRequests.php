<?php

namespace App\Filament\Resources\ChimneyCalculationRequests\Pages;

use App\Filament\Pages\ChimneyCalculator;
use App\Filament\Resources\ChimneyCalculationRequests\ChimneyCalculationRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListChimneyCalculationRequests extends ListRecords
{
    protected static string $resource = ChimneyCalculationRequestResource::class;

    public function mount(): void
    {
        parent::mount();
        $this->redirect(ChimneyCalculator::getUrl(['requests' => 1]));
    }
}
