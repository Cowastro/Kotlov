<?php

namespace App\Filament\Resources\PartnerBranches\Pages;

use App\Filament\Resources\PartnerBranches\PartnerBranchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPartnerBranches extends ListRecords
{
    protected static string $resource = PartnerBranchResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Добавить точку продаж')];
    }
}
