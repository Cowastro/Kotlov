<?php

namespace App\Filament\Resources\ChimneyCalculationRequests\Pages;

use App\Filament\Pages\ChimneyCalculator;
use App\Filament\Resources\ChimneyCalculationRequests\ChimneyCalculationRequestResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListChimneyCalculationRequests extends ListRecords
{
    protected static string $resource = ChimneyCalculationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newCalculation')
                ->label('Новый расчёт')
                ->icon('heroicon-o-plus')
                ->url(ChimneyCalculator::getUrl()),
        ];
    }
}
